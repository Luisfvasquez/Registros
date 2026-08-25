<?php

namespace Tests\Feature\Business;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_can_be_created()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('products.store'), [
            'name' => 'Producto Test',
            'sku' => 'SKU-001',
            'sale_price' => 150.5,
            'purchase_cost' => 90,
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', ['name' => 'Producto Test', 'sku' => 'SKU-001']);
    }

    public function test_a_product_can_be_created_with_an_existing_category()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();

        $response = $this->actingAs($user)->post(route('products.store'), [
            'category_id' => $category->id,
            'name' => 'Producto con categoría',
            'sale_price' => 10,
            'purchase_cost' => 5,
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', ['name' => 'Producto con categoría', 'category_id' => $category->id]);
    }

    public function test_sku_must_be_unique()
    {
        $user = User::factory()->create();
        Product::factory()->create(['sku' => 'DUP-001']);

        $response = $this->actingAs($user)->post(route('products.store'), [
            'name' => 'Otro producto',
            'sku' => 'DUP-001',
            'sale_price' => 10,
            'purchase_cost' => 5,
        ]);

        $response->assertSessionHasErrors('sku');
    }

    public function test_sku_uniqueness_ignores_the_product_being_updated()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['sku' => 'KEEP-001']);

        $response = $this->actingAs($user)->put(route('products.update', $product), [
            'name' => $product->name,
            'sku' => 'KEEP-001',
            'sale_price' => $product->sale_price,
            'purchase_cost' => $product->purchase_cost,
        ]);

        $response->assertSessionHasNoErrors();
    }

    public function test_a_product_can_be_deleted()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($user)->delete(route('products.destroy', $product));

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}
