<?php

namespace Tests\Feature\Business;

use App\Models\Contact;
use App\Models\Document;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createInvoice(float $total = 100): Document
    {
        return Document::create([
            'number' => 'FAC-'.fake()->unique()->numerify('#####'),
            'operation_type' => 'venta',
            'document_type' => 'factura',
            'status' => 'pendiente',
            'contact_id' => Contact::factory()->create()->id,
            'issue_date' => now()->toDateString(),
            'subtotal' => $total,
            'tax_total' => 0,
            'total' => $total,
        ]);
    }

    public function test_a_partial_payment_marks_the_invoice_as_parcial()
    {
        $user = User::factory()->create();
        $invoice = $this->createInvoice(100);
        $paymentMethod = PaymentMethod::factory()->create();

        $response = $this->actingAs($user)->post(route('documents.payments.store', $invoice), [
            'amount' => 40,
            'payment_method_id' => $paymentMethod->id,
            'paid_at' => now()->toDateString(),
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $invoice->refresh();
        $this->assertSame('parcial', $invoice->status);
        $this->assertSame(40.0, $invoice->paidTotal());
        $this->assertSame(60.0, $invoice->balance());
    }

    public function test_paying_the_full_balance_marks_the_invoice_as_pagado()
    {
        $user = User::factory()->create();
        $invoice = $this->createInvoice(100);
        $paymentMethod = PaymentMethod::factory()->create();

        $this->actingAs($user)->post(route('documents.payments.store', $invoice), [
            'amount' => 100,
            'payment_method_id' => $paymentMethod->id,
            'paid_at' => now()->toDateString(),
        ]);

        $this->assertSame('pagado', $invoice->fresh()->status);
        $this->assertSame(0.0, $invoice->fresh()->balance());
    }

    public function test_a_payment_cannot_exceed_the_remaining_balance()
    {
        $user = User::factory()->create();
        $invoice = $this->createInvoice(100);
        $paymentMethod = PaymentMethod::factory()->create();

        $response = $this->actingAs($user)->post(route('documents.payments.store', $invoice), [
            'amount' => 150,
            'payment_method_id' => $paymentMethod->id,
            'paid_at' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertSame('pendiente', $invoice->fresh()->status);
    }

    public function test_budgets_cannot_receive_payments()
    {
        $user = User::factory()->create();
        $paymentMethod = PaymentMethod::factory()->create();
        $budget = Document::create([
            'number' => 'PRE-00001',
            'operation_type' => 'venta',
            'document_type' => 'presupuesto',
            'status' => 'pendiente',
            'contact_id' => Contact::factory()->create()->id,
            'issue_date' => now()->toDateString(),
            'subtotal' => 100,
            'tax_total' => 0,
            'total' => 100,
        ]);

        $response = $this->actingAs($user)->post(route('documents.payments.store', $budget), [
            'amount' => 10,
            'payment_method_id' => $paymentMethod->id,
            'paid_at' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
    }

    public function test_deleting_a_payment_recalculates_the_invoice_status()
    {
        $user = User::factory()->create();
        $invoice = $this->createInvoice(100);
        $paymentMethod = PaymentMethod::factory()->create();

        $payment = $invoice->payments()->create([
            'payment_method_id' => $paymentMethod->id,
            'amount' => 100,
            'paid_at' => now()->toDateString(),
        ]);
        $invoice->syncPaymentStatus();
        $this->assertSame('pagado', $invoice->fresh()->status);

        $response = $this->actingAs($user)->delete(route('documents.payments.destroy', [$invoice, $payment]));

        $response->assertRedirect();
        $this->assertSame('pendiente', $invoice->fresh()->status);
    }

    public function test_a_split_payment_can_be_registered_at_document_creation_across_multiple_methods()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();
        $cash = PaymentMethod::factory()->create(['name' => 'Efectivo']);
        $card = PaymentMethod::factory()->create(['name' => 'Tarjeta']);

        $response = $this->actingAs($user)->post(route('documents.store'), [
            'operation_type' => 'venta',
            'document_type' => 'factura',
            'contact_id' => $contact->id,
            'issue_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Item A', 'quantity' => 1, 'unit_price' => 50, 'tax_rate' => 0],
            ],
            'payments' => [
                ['payment_method_id' => $cash->id, 'amount' => 20],
                ['payment_method_id' => $card->id, 'amount' => 30],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $document = Document::first();

        $this->assertSame('pagado', $document->status);
        $this->assertCount(2, $document->payments);
        $this->assertSame(50.0, $document->paidTotal());
    }
}
