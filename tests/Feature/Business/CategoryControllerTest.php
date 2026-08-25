<?php

namespace Tests\Feature\Business;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_can_be_searched_for_the_picker()
    {
        $user = User::factory()->create();
        Category::factory()->create(['name' => 'Electrónica']);
        Category::factory()->create(['name' => 'Ferretería']);

        $response = $this->actingAs($user)->getJson(route('categories.index', ['q' => 'Elec']));

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_a_category_can_be_created_inline()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('categories.store'), [
            'name' => 'Nueva categoría',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('categories', ['name' => 'Nueva categoría']);
    }

    public function test_a_category_name_must_be_unique()
    {
        $user = User::factory()->create();
        Category::factory()->create(['name' => 'Repetida']);

        $response = $this->actingAs($user)->postJson(route('categories.store'), [
            'name' => 'Repetida',
        ]);

        $response->assertUnprocessable();
    }
}
