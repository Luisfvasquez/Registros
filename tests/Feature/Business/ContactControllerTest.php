<?php

namespace Tests\Feature\Business;

use App\Models\Contact;
use App\Models\Document;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_contacts()
    {
        $response = $this->get(route('contacts.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_contacts()
    {
        $user = User::factory()->create();
        Contact::factory(3)->create();

        $response = $this->actingAs($user)->get(route('contacts.index'));

        $response->assertOk();
    }

    public function test_a_contact_can_be_created()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('contacts.store'), [
            'type' => 'cliente',
            'name' => 'Juan Pérez',
            'document' => '30-12345678-9',
            'phone_country_code' => '+54',
            'phone' => '3511234567',
            'email' => 'juan@example.com',
            'address' => 'Calle Falsa 123',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('contacts.index'));

        $this->assertDatabaseHas('contacts', [
            'name' => 'Juan Pérez',
            'document' => '30-12345678-9',
        ]);
    }

    public function test_a_contact_requires_a_name()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('contacts.store'), [
            'type' => 'cliente',
            'name' => '',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_a_contact_can_be_updated()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create(['name' => 'Nombre Viejo']);

        $response = $this->actingAs($user)->put(route('contacts.update', $contact), [
            'type' => $contact->type,
            'name' => 'Nombre Nuevo',
            'document' => $contact->document,
            'phone_country_code' => $contact->phone_country_code,
            'phone' => $contact->phone,
            'email' => $contact->email,
            'address' => $contact->address,
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('contacts.index'));

        $this->assertSame('Nombre Nuevo', $contact->fresh()->name);
    }

    public function test_a_contact_can_be_deleted()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();

        $response = $this->actingAs($user)->delete(route('contacts.destroy', $contact));

        $response->assertRedirect(route('contacts.index'));
        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
    }

    public function test_a_contacts_ledger_shows_what_they_owe_and_are_owed()
    {
        $user = User::factory()->create();
        $contact = Contact::factory()->create();

        // They bought from us and still owe us 60.
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
        $sale->payments()->create([
            'payment_method_id' => PaymentMethod::factory()->create()->id,
            'amount' => 40,
            'paid_at' => now()->toDateString(),
        ]);

        // We bought from them and still owe them 30.
        Document::create([
            'number' => 'FAC-00002',
            'operation_type' => 'compra',
            'document_type' => 'factura',
            'status' => 'pendiente',
            'contact_id' => $contact->id,
            'issue_date' => now()->toDateString(),
            'subtotal' => 30,
            'tax_total' => 0,
            'total' => 30,
        ]);

        $response = $this->actingAs($user)->get(route('contacts.show', $contact));

        $response->assertInertia(fn ($page) => $page
            ->where('receivable', 60)
            ->where('payable', 30)
            ->has('documents', 2));
    }

    public function test_contacts_can_be_searched_for_autocomplete()
    {
        $user = User::factory()->create();
        Contact::factory()->create(['name' => 'Empresa Alpha']);
        Contact::factory()->create(['name' => 'Empresa Beta']);

        $response = $this->actingAs($user)->getJson(route('contacts.search', ['q' => 'Alpha']));

        $response->assertOk()->assertJsonCount(1);
    }
}
