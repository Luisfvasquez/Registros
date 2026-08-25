<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_this_months_expenses_reduce_the_profit_metric()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();

        $sale = Document::create([
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
        $sale->expenses()->create(['description' => 'Envío', 'amount' => 10]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->where('metrics.sales_total', 100)
            ->where('metrics.expenses_total', 10)
            ->where('metrics.profit', 90));
    }
}
