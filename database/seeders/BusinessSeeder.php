<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Document;
use App\Models\ExchangeRate;
use App\Models\PaymentMethod;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BusinessSeeder extends Seeder
{
    /**
     * Seed categories, payment methods, contacts, products and a handful of sample
     * documents (with items, expenses and payments) so the business modules have
     * realistic data to work against right away.
     */
    public function run(): void
    {
        ExchangeRate::create([
            'currency_from' => 'USD',
            'currency_to' => 'BS',
            'rate' => 172.5,
            'date' => now()->toDateString(),
            'is_active' => true,
        ]);

        // "Caja" and "Saco" are seeded as empty categories (no products assigned to them yet).
        Category::create(['name' => 'Saco']);
        Category::create(['name' => 'Caja']);
        $cesta = Category::create(['name' => 'Cesta']);

        $paymentMethods = collect(['Efectivo', 'Transferencia', 'Tarjeta', 'Pago móvil', 'Cheque'])
            ->map(fn (string $name) => PaymentMethod::create(['name' => $name]));

        $contacts = Contact::factory(10)->create();

        $vegetables = ['Tomate', 'Pimentón'];
        $variants = ['Maraña', 'Golilla', 'Mediano', 'Grande'];

        $products = collect();

        foreach ($vegetables as $vegetable) {
            foreach ($variants as $variant) {
                $cost = fake()->randomFloat(2, 5, 40);

                $products->push(Product::create([
                    'category_id' => $cesta->id,
                    'name' => "{$vegetable} {$variant}",
                    'sku' => Str::upper(Str::slug("{$vegetable}-{$variant}")),
                    'purchase_cost' => $cost,
                    'sale_price' => round($cost * fake()->randomFloat(2, 1.2, 1.6), 2),
                ]));
            }
        }

        $documentNumber = 1;

        foreach (range(1, 12) as $i) {
            $operationType = fake()->randomElement(['venta', 'compra']);
            $documentType = fake()->randomElement(['presupuesto', 'factura']);
            $prefix = $documentType === 'factura' ? 'FAC' : 'PRE';

            $document = Document::create([
                'number' => $prefix.'-'.str_pad((string) $documentNumber++, 5, '0', STR_PAD_LEFT),
                'operation_type' => $operationType,
                'document_type' => $documentType,
                'status' => 'pendiente',
                'contact_id' => $contacts->random()->id,
                'issue_date' => fake()->dateTimeBetween('-2 months', 'now'),
                'notes' => fake()->boolean(20) ? fake()->sentence() : null,
            ]);

            $subtotal = 0;

            foreach (range(1, fake()->numberBetween(1, 5)) as $sort) {
                $product = $products->random();
                $quantity = fake()->numberBetween(1, 6);
                $unitPrice = $operationType === 'compra' ? $product->purchase_cost : $product->sale_price;
                $lineSubtotal = round($quantity * $unitPrice, 2);
                $subtotal += $lineSubtotal;

                $document->items()->create([
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_rate' => 0,
                    'subtotal' => $lineSubtotal,
                    'sort_order' => $sort,
                ]);
            }

            if (fake()->boolean(25)) {
                $document->expenses()->create([
                    'description' => fake()->randomElement(['Envío', 'Comisión', 'Embalaje']),
                    'amount' => fake()->randomFloat(2, 5, 50),
                ]);
            }

            // Expenses are tracked separately for profit accounting and never affect the
            // document total shown to the contact.
            $total = $subtotal;

            $document->update([
                'subtotal' => $subtotal,
                'tax_total' => 0,
                'total' => $total,
            ]);

            if ($documentType === 'factura') {
                $paidSoFar = 0;
                $paymentCount = fake()->randomElement([0, 1, 1, 2]);

                for ($p = 1; $p <= $paymentCount; $p++) {
                    $remaining = round($total - $paidSoFar, 2);
                    if ($remaining <= 0) {
                        break;
                    }

                    $isLast = $p === $paymentCount;
                    $amount = ($isLast && fake()->boolean(50))
                        ? $remaining
                        : round(fake()->randomFloat(2, min(1, $remaining), $remaining), 2);

                    $document->payments()->create([
                        'payment_method_id' => $paymentMethods->random()->id,
                        'amount' => $amount,
                        'reference' => fake()->boolean(50) ? Str::upper(fake()->bothify('REF-####')) : null,
                        'paid_at' => fake()->dateTimeBetween($document->issue_date, 'now'),
                    ]);

                    $paidSoFar += $amount;
                }

                $document->syncPaymentStatus();
            }
        }
    }
}
