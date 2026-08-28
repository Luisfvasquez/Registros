<?php

namespace App\Http\Controllers;

use App\Http\Requests\DocumentRequest;
use App\Models\Contact;
use App\Models\Document;
use App\Models\ExchangeRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->listDocuments($request);
    }

    public function sales(Request $request): Response
    {
        return $this->listDocuments($request, 'venta');
    }

    public function purchases(Request $request): Response
    {
        return $this->listDocuments($request, 'compra');
    }

    /**
     * The sales/purchases screens are organised by contact: first a list of contacts that have
     * documents of the given operation type, then — when a `contact` id is supplied — that
     * contact's documents so several invoices can be picked and shared together.
     */
    private function listDocuments(Request $request, ?string $lockedOperationType = null): Response
    {
        $search = $request->string('search')->toString();

        $scopeOperation = fn ($query) => $query->when(
            $lockedOperationType,
            fn ($query, $value) => $query->where('operation_type', $value),
        );

        $contacts = Contact::query()
            ->whereHas('documents', $scopeOperation)
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('document', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->with(['documents' => fn ($query) => $scopeOperation($query)->with('payments:id,document_id,amount')])
            ->orderBy('name')
            ->get()
            ->map(function (Contact $contact) {
                $documents = $contact->documents;
                $invoices = $documents->where('document_type', 'factura');

                return [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'type' => $contact->type,
                    'document' => $contact->document,
                    'phone' => $contact->phone,
                    'phone_country_code' => $contact->phone_country_code,
                    'documents_count' => $documents->count(),
                    'invoices_count' => $invoices->count(),
                    'total' => (float) $documents->sum('total'),
                    'balance' => (float) $invoices
                        ->whereIn('status', ['pendiente', 'parcial'])
                        ->sum(fn (Document $document) => round((float) $document->total - (float) $document->payments->sum('amount'), 2)),
                ];
            })
            ->values();

        $selectedContact = null;
        $selectedDocuments = null;

        if ($contactId = $request->integer('contact')) {
            $selectedContact = Contact::findOrFail($contactId);

            $selectedDocuments = $selectedContact->documents()
                ->when($lockedOperationType, fn ($query, $value) => $query->where('operation_type', $value))
                ->with(['items', 'payments'])
                ->latest('issue_date')
                ->latest('id')
                ->get()
                ->each(function (Document $document) use ($selectedContact) {
                    $document->setAttribute('balance', $document->balance());
                    $document->setAttribute('paid_total', $document->paidTotal());
                    $document->setRelation('contact', $selectedContact);
                });
        }

        return Inertia::render('documents/Index', [
            'contacts' => $contacts,
            'selectedContact' => $selectedContact,
            'selectedDocuments' => $selectedDocuments,
            'filters' => $request->only(['search']),
            'lockedOperationType' => $lockedOperationType,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('documents/Form', [
            'document' => null,
            'defaults' => $request->only(['operation_type', 'document_type']),
        ]);
    }

    public function store(DocumentRequest $request): RedirectResponse
    {
        $document = DB::transaction(function () use ($request) {
            $data = $request->validated();

            $document = Document::create([
                'number' => $this->nextNumber($data['document_type']),
                'operation_type' => $data['operation_type'],
                'document_type' => $data['document_type'],
                'status' => 'pendiente',
                'contact_id' => $data['contact_id'],
                'issue_date' => $data['issue_date'],
                'exchange_rate' => ExchangeRate::where('is_active', true)->value('rate'),
                'notes' => $data['notes'] ?? null,
            ]);

            [$subtotal, $taxTotal] = $this->syncItems($document, $data['items']);
            $this->syncExpenses($document, $data['expenses'] ?? []);

            $document->update([
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'total' => $subtotal + $taxTotal,
            ]);

            if ($document->document_type === 'factura' && ! empty($data['payments'])) {
                $this->syncPayments($document, $data['payments']);
            }

            return $document;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Documento creado.')]);

        return to_route('documents.show', $document);
    }

    public function show(Document $document): Response
    {
        $document->load([
            'contact',
            'items.product',
            'expenses',
            'payments' => fn ($query) => $query->with('paymentMethod')->latest('paid_at'),
        ]);

        return Inertia::render('documents/Show', [
            'document' => $document,
            'paidTotal' => $document->paidTotal(),
            'balance' => $document->balance(),
        ]);
    }

    public function edit(Document $document): Response
    {
        $document->load(['contact', 'items.product', 'expenses']);

        return Inertia::render('documents/Form', [
            'document' => $document,
            'defaults' => [],
        ]);
    }

    public function update(DocumentRequest $request, Document $document): RedirectResponse
    {
        DB::transaction(function () use ($request, $document) {
            $data = $request->validated();

            $document->update([
                'operation_type' => $data['operation_type'],
                'document_type' => $data['document_type'],
                'contact_id' => $data['contact_id'],
                'issue_date' => $data['issue_date'],
                'notes' => $data['notes'] ?? null,
            ]);

            $document->items()->delete();
            [$subtotal, $taxTotal] = $this->syncItems($document, $data['items']);

            $document->expenses()->delete();
            $this->syncExpenses($document, $data['expenses'] ?? []);

            $document->update([
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'total' => $subtotal + $taxTotal,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Documento actualizado.')]);

        return to_route('documents.show', $document);
    }

    public function destroy(Document $document): RedirectResponse
    {
        if ($document->document_type === 'factura') {
            abort(422, 'Las órdenes no se pueden eliminar.');
        }

        $operationType = $document->operation_type;

        $document->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Documento eliminado.')]);

        return to_route($operationType === 'compra' ? 'documents.purchases.index' : 'documents.sales.index');
    }

    /**
     * Create a new invoice from an existing budget, copying its items and expenses over.
     */
    public function convertToInvoice(Document $document): RedirectResponse
    {
        if ($document->document_type !== 'presupuesto') {
            abort(422, 'Solo un presupuesto puede convertirse en factura.');
        }

        $invoice = DB::transaction(function () use ($document) {
            $invoice = Document::create([
                'number' => $this->nextNumber('factura'),
                'operation_type' => $document->operation_type,
                'document_type' => 'factura',
                'status' => 'pendiente',
                'contact_id' => $document->contact_id,
                'converted_from_id' => $document->id,
                'issue_date' => now()->toDateString(),
                'subtotal' => $document->subtotal,
                'tax_total' => $document->tax_total,
                'total' => $document->total,
                'exchange_rate' => $document->exchange_rate,
                'notes' => $document->notes,
            ]);

            foreach ($document->items as $item) {
                $invoice->items()->create([
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                    'subtotal' => $item->subtotal,
                    'sort_order' => $item->sort_order,
                ]);
            }

            foreach ($document->expenses as $expense) {
                $invoice->expenses()->create([
                    'description' => $expense->description,
                    'amount' => $expense->amount,
                ]);
            }

            $document->update(['status' => 'convertido']);

            return $invoice;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Presupuesto convertido a factura.')]);

        return to_route('documents.show', $invoice);
    }

    /**
     * @param  array<int, array{product_id?: int|null, description: string, quantity: float, unit_price: float, tax_rate?: float|null}>  $items
     * @return array{0: float, 1: float} [subtotal, taxTotal]
     */
    private function syncItems(Document $document, array $items): array
    {
        $subtotal = 0;
        $taxTotal = 0;

        foreach ($items as $index => $item) {
            $lineSubtotal = round($item['quantity'] * $item['unit_price'], 2);
            $lineTax = round($lineSubtotal * (($item['tax_rate'] ?? 0) / 100), 2);

            $document->items()->create([
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'tax_rate' => $item['tax_rate'] ?? 0,
                'subtotal' => $lineSubtotal,
                'sort_order' => $index,
            ]);

            $subtotal += $lineSubtotal;
            $taxTotal += $lineTax;
        }

        return [$subtotal, $taxTotal];
    }

    /**
     * Expenses are stored purely for internal profit accounting — they never affect
     * the document's total, which is what the contact is actually billed for.
     *
     * @param  array<int, array{description: string, amount: float}>  $expenses
     */
    private function syncExpenses(Document $document, array $expenses): void
    {
        foreach ($expenses as $expense) {
            $document->expenses()->create([
                'description' => $expense['description'],
                'amount' => $expense['amount'],
            ]);
        }
    }

    /**
     * @param  array<int, array{payment_method_id: int, amount: float, reference?: string|null}>  $payments
     */
    private function syncPayments(Document $document, array $payments): void
    {
        $sum = array_sum(array_column($payments, 'amount'));

        if ($sum > (float) $document->total) {
            throw ValidationException::withMessages([
                'payments' => __('La suma de los abonos no puede superar el total del documento (:total).', ['total' => number_format((float) $document->total, 2)]),
            ]);
        }

        foreach ($payments as $payment) {
            $document->payments()->create([
                'payment_method_id' => $payment['payment_method_id'],
                'amount' => $payment['amount'],
                'reference' => $payment['reference'] ?? null,
                'paid_at' => now()->toDateString(),
            ]);
        }

        $document->syncPaymentStatus();
    }

    private function nextNumber(string $documentType): string
    {
        $prefix = $documentType === 'factura' ? 'FAC' : 'PRE';
        $last = Document::where('document_type', $documentType)->max('id') ?? 0;

        return $prefix.'-'.str_pad((string) ($last + 1), 5, '0', STR_PAD_LEFT);
    }
}
