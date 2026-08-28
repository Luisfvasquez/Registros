<?php

namespace Tests\Feature\Business;

use App\Models\Contact;
use App\Models\Document;
use App\Models\ExchangeRate;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_document_can_be_created_and_totals_are_computed_on_the_server()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($user)->post(route('documents.store'), [
            'operation_type' => 'venta',
            'document_type' => 'factura',
            'contact_id' => $contact->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $product->id, 'description' => 'Item A', 'quantity' => 2, 'unit_price' => 100, 'tax_rate' => 21],
                ['description' => 'Item B', 'quantity' => 1, 'unit_price' => 50, 'tax_rate' => 0],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $document = Document::first();

        $response->assertRedirect(route('documents.show', $document));

        // subtotal = 2*100 + 1*50 = 250; tax = 200*0.21 = 42; total = 292
        $this->assertSame('250.00', $document->subtotal);
        $this->assertSame('42.00', $document->tax_total);
        $this->assertSame('292.00', $document->total);
        $this->assertSame('pendiente', $document->status);
        $this->assertCount(2, $document->items);
    }

    public function test_documents_are_always_created_as_orders_even_when_a_budget_is_requested()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();

        $response = $this->actingAs($user)->post(route('documents.store'), [
            'operation_type' => 'venta',
            'document_type' => 'presupuesto',
            'contact_id' => $contact->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Item A', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $document = Document::first();

        $this->assertSame('factura', $document->document_type);
        $this->assertStringStartsWith('FAC-', $document->number);
    }

    public function test_the_create_form_can_be_opened_with_a_contact_preselected()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();

        $response = $this->actingAs($user)->get(route('documents.create', [
            'contact' => $contact->id,
            'operation_type' => 'venta',
        ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('documents/Form')
            ->where('contact.id', $contact->id)
            ->where('defaults.operation_type', 'venta')
        );
    }

    public function test_the_create_form_has_no_contact_when_none_is_requested()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('documents.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('documents/Form')
            ->where('contact', null)
        );
    }

    public function test_expenses_are_tracked_separately_and_never_affect_the_document_total()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();

        $response = $this->actingAs($user)->post(route('documents.store'), [
            'operation_type' => 'compra',
            'document_type' => 'factura',
            'contact_id' => $contact->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Item A', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 0],
            ],
            'expenses' => [
                ['description' => 'Envío', 'amount' => 15],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $document = Document::first();

        $this->assertSame('100.00', $document->subtotal);
        $this->assertSame('100.00', $document->total);
        $this->assertCount(1, $document->expenses);
        $this->assertSame(15.0, $document->expensesTotal());
    }

    public function test_the_active_exchange_rate_is_snapshotted_on_the_document()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();
        ExchangeRate::create([
            'currency_from' => 'USD',
            'currency_to' => 'BS',
            'rate' => 180.25,
            'date' => now()->toDateString(),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('documents.store'), [
            'operation_type' => 'venta',
            'document_type' => 'factura',
            'contact_id' => $contact->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Item A', 'quantity' => 1, 'unit_price' => 10, 'tax_rate' => 0],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $document = Document::first();

        $this->assertSame('180.25', $document->exchange_rate);
    }

    public function test_at_least_one_item_is_required()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();

        $response = $this->actingAs($user)->post(route('documents.store'), [
            'operation_type' => 'venta',
            'document_type' => 'factura',
            'contact_id' => $contact->id,
            'issue_date' => now()->toDateString(),
            'items' => [],
        ]);

        $response->assertSessionHasErrors('items');
    }

    public function test_a_budget_can_be_converted_to_an_invoice()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();
        $budget = Document::create([
            'number' => 'PRE-00001',
            'operation_type' => 'venta',
            'document_type' => 'presupuesto',
            'status' => 'pendiente',
            'contact_id' => $contact->id,
            'issue_date' => now()->toDateString(),
            'subtotal' => 100,
            'tax_total' => 0,
            'total' => 100,
        ]);
        $budget->items()->create([
            'description' => 'Item A',
            'quantity' => 1,
            'unit_price' => 100,
            'tax_rate' => 0,
            'subtotal' => 100,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)->post(route('documents.convert', $budget));

        $invoice = Document::where('document_type', 'factura')->first();

        $response->assertRedirect(route('documents.show', $invoice));

        $this->assertSame('convertido', $budget->fresh()->status);
        $this->assertSame($budget->id, $invoice->converted_from_id);
        $this->assertSame('100.00', $invoice->total);
        $this->assertCount(1, $invoice->items);
    }

    public function test_an_invoice_cannot_be_converted_again()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();
        $invoice = Document::create([
            'number' => 'FAC-00001',
            'operation_type' => 'venta',
            'document_type' => 'factura',
            'status' => 'pendiente',
            'contact_id' => $contact->id,
            'issue_date' => now()->toDateString(),
            'subtotal' => 100,
            'tax_total' => 0,
            'total' => 100,
        ]);

        $response = $this->actingAs($user)->post(route('documents.convert', $invoice));

        $response->assertStatus(422);
    }

    public function test_an_order_cannot_be_deleted()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();
        $invoice = Document::create([
            'number' => 'FAC-00001',
            'operation_type' => 'venta',
            'document_type' => 'factura',
            'status' => 'pendiente',
            'contact_id' => $contact->id,
            'issue_date' => now()->toDateString(),
            'subtotal' => 100,
            'tax_total' => 0,
            'total' => 100,
        ]);

        $response = $this->actingAs($user)->delete(route('documents.destroy', $invoice));

        $response->assertStatus(422);
        $this->assertModelExists($invoice);
    }

    public function test_a_budget_can_still_be_deleted()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();
        $budget = Document::create([
            'number' => 'PRE-00001',
            'operation_type' => 'venta',
            'document_type' => 'presupuesto',
            'status' => 'pendiente',
            'contact_id' => $contact->id,
            'issue_date' => now()->toDateString(),
            'subtotal' => 100,
            'tax_total' => 0,
            'total' => 100,
        ]);

        $response = $this->actingAs($user)->delete(route('documents.destroy', $budget));

        $response->assertRedirect(route('documents.sales.index'));
        $this->assertModelMissing($budget);
    }

    public function test_the_sales_view_only_lists_contacts_with_sale_documents()
    {
        $user = User::factory()->create();
        $seller = Contact::factory()->create(['name' => 'Cliente Venta']);
        $buyer = Contact::factory()->create(['name' => 'Proveedor Compra']);

        Document::factory()->create([
            'operation_type' => 'venta',
            'contact_id' => $seller->id,
            'total' => 100,
        ]);
        Document::factory()->create([
            'operation_type' => 'compra',
            'contact_id' => $buyer->id,
            'total' => 100,
        ]);

        $response = $this->actingAs($user)->get(route('documents.sales.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('lockedOperationType', 'venta')
            ->has('contacts', 1)
            ->where('contacts.0.id', $seller->id)
            ->where('selectedContact', null)
        );
    }

    public function test_the_purchases_view_only_lists_contacts_with_purchase_documents()
    {
        $user = User::factory()->create();
        $seller = Contact::factory()->create();
        $buyer = Contact::factory()->create();

        Document::factory()->create([
            'operation_type' => 'venta',
            'contact_id' => $seller->id,
            'total' => 100,
        ]);
        Document::factory()->create([
            'operation_type' => 'compra',
            'contact_id' => $buyer->id,
            'total' => 100,
        ]);

        $response = $this->actingAs($user)->get(route('documents.purchases.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('lockedOperationType', 'compra')
            ->has('contacts', 1)
            ->where('contacts.0.id', $buyer->id)
        );
    }

    public function test_selecting_a_contact_returns_only_that_contacts_documents_for_the_operation()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();
        $other = Contact::factory()->create();

        $sale = Document::factory()->create([
            'operation_type' => 'venta',
            'document_type' => 'factura',
            'contact_id' => $contact->id,
            'total' => 100,
        ]);
        // Same contact but a purchase — must not show up in the sales drill-down.
        Document::factory()->create([
            'operation_type' => 'compra',
            'contact_id' => $contact->id,
            'total' => 100,
        ]);
        // Another contact's sale — must not show up either.
        Document::factory()->create([
            'operation_type' => 'venta',
            'contact_id' => $other->id,
            'total' => 100,
        ]);

        $response = $this->actingAs($user)->get(route('documents.sales.index', ['contact' => $contact->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('selectedContact.id', $contact->id)
            ->has('selectedDocuments', 1)
            ->where('selectedDocuments.0.id', $sale->id)
        );
    }

    public function test_contact_summary_balance_only_counts_outstanding_invoices()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();

        $invoice = Document::factory()->create([
            'operation_type' => 'venta',
            'document_type' => 'factura',
            'status' => 'parcial',
            'contact_id' => $contact->id,
            'total' => 100,
        ]);
        $invoice->payments()->create([
            'payment_method_id' => PaymentMethod::factory()->create()->id,
            'amount' => 30,
            'paid_at' => now()->toDateString(),
        ]);
        // A budget never counts toward the outstanding balance.
        Document::factory()->create([
            'operation_type' => 'venta',
            'document_type' => 'presupuesto',
            'status' => 'pendiente',
            'contact_id' => $contact->id,
            'total' => 500,
        ]);

        $response = $this->actingAs($user)->get(route('documents.sales.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('contacts.0.balance', 70)
            ->where('contacts.0.documents_count', 2)
        );
    }
}
