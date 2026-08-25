<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Models\Contact;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $contacts = Contact::query()
            ->when($request->string('search')->toString(), function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('document', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->string('type')->toString(), fn ($query, $type) => $query->where('type', $type))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('contacts/Index', [
            'contacts' => $contacts,
            'filters' => $request->only('search', 'type'),
        ]);
    }

    /**
     * Show a contact's full ledger: every order/budget that belongs to them and,
     * for orders, whether we owe them money (purchases) or they owe us (sales).
     */
    public function show(Contact $contact): Response
    {
        $documents = $contact->documents()
            ->latest('issue_date')
            ->latest('id')
            ->get()
            ->each(fn (Document $document) => $document->setAttribute('balance', $document->balance()));

        $invoices = $documents->where('document_type', 'factura');

        $receivable = $invoices
            ->where('operation_type', 'venta')
            ->whereIn('status', ['pendiente', 'parcial'])
            ->sum(fn (Document $document) => $document->balance());

        $payable = $invoices
            ->where('operation_type', 'compra')
            ->whereIn('status', ['pendiente', 'parcial'])
            ->sum(fn (Document $document) => $document->balance());

        return Inertia::render('contacts/Show', [
            'contact' => $contact,
            'documents' => $documents,
            'receivable' => $receivable,
            'payable' => $payable,
        ]);
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        Contact::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contacto creado.')]);

        return to_route('contacts.index');
    }

    public function update(ContactRequest $request, Contact $contact): RedirectResponse
    {
        $contact->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contacto actualizado.')]);

        return to_route('contacts.index');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contacto eliminado.')]);

        return to_route('contacts.index');
    }

    /**
     * Lightweight JSON search used for autocomplete when emitting a document.
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->string('q')->toString();

        $contacts = Contact::query()
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('document', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'document', 'phone_country_code', 'phone', 'type']);

        return response()->json($contacts);
    }
}
