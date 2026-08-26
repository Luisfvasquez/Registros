<?php

namespace Tests\Feature\Business;

use App\Models\Contact;
use App\Models\Document;
use App\Models\ExchangeRate;
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
            'document_type' => 'presupuesto',
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

    public function test_expenses_are_tracked_separately_and_never_affect_the_document_total()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();

        $response = $this->actingAs($user)->post(route('documents.store'), [
            'operation_type' => 'compra',
            'document_type' => 'presupuesto',
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
            'document_type' => 'presupuesto',
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
            'document_type' => 'presupuesto',
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

    public function test_the_sales_view_only_lists_sale_documents()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();
        $sale = Document::create([
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
        $purchase = Document::create([
            'number' => 'PRE-00002',
            'operation_type' => 'compra',
            'document_type' => 'presupuesto',
            'status' => 'pendiente',
            'contact_id' => $contact->id,
            'issue_date' => now()->toDateString(),
            'subtotal' => 100,
            'tax_total' => 0,
            'total' => 100,
        ]);

        $response = $this->actingAs($user)->get(route('documents.sales.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('lockedOperationType', 'venta')
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $sale->id)
        );
    }

    public function test_the_purchases_view_only_lists_purchase_documents()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();
        Document::create([
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
        $purchase = Document::create([
            'number' => 'PRE-00002',
            'operation_type' => 'compra',
            'document_type' => 'presupuesto',
            'status' => 'pendiente',
            'contact_id' => $contact->id,
            'issue_date' => now()->toDateString(),
            'subtotal' => 100,
            'tax_total' => 0,
            'total' => 100,
        ]);

        $response = $this->actingAs($user)->get(route('documents.purchases.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('lockedOperationType', 'compra')
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $purchase->id)
        );
    }
}
