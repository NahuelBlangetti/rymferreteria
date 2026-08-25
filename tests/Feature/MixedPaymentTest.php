<?php

namespace Tests\Feature;

use App\Filament\Pages\CrearVenta;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use App\Services\Tickets\SaleTicketEscPosBuilder;
use App\Support\PaymentMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MixedPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    private function product(float $price = 1000): Product
    {
        $category = Category::create([
            'name' => 'Herramientas',
            'slug' => 'herramientas-'.uniqid(),
        ]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Martillo',
            'sale_price' => $price,
            'stock' => 100,
            'unit' => 'unidad',
            'active' => true,
        ]);
    }

    private function openRegister(User $user): CashRegister
    {
        return CashRegister::create([
            'user_id' => $user->id,
            'opening_amount' => 1000,
            'opened_at' => now(),
            'status' => 'open',
        ]);
    }

    public function test_pos_records_a_mixed_cash_and_transfer_sale(): void
    {
        $user = $this->user();
        $product = $this->product(1000);
        $this->openRegister($user);

        Livewire::actingAs($user)
            ->test(CrearVenta::class)
            ->set('printTicket', false)
            ->call('addToCart', $product->id)
            ->call('togglePaymentMethod', 'cash')
            ->call('togglePaymentMethod', 'transfer')
            ->call('confirmSale');

        $sale = Sale::query()->first();

        $this->assertNotNull($sale);
        $this->assertEquals(PaymentMethods::MIXED, $sale->payment_method);
        $this->assertEquals(1000.0, (float) $sale->total);
        $this->assertCount(2, $sale->payments);
        $this->assertEquals(500.0, (float) $sale->payments->firstWhere('method', 'cash')->amount);
        $this->assertEquals(500.0, (float) $sale->payments->firstWhere('method', 'transfer')->amount);
    }

    public function test_pos_rejects_mixed_payment_that_does_not_cover_the_total(): void
    {
        $user = $this->user();
        $product = $this->product(1000);
        $this->openRegister($user);

        Livewire::actingAs($user)
            ->test(CrearVenta::class)
            ->set('printTicket', false)
            ->call('addToCart', $product->id)
            ->call('togglePaymentMethod', 'cash')
            ->call('togglePaymentMethod', 'transfer')
            ->call('togglePaymentMethod', 'credit')
            ->set('paymentAmounts.cash', 100)
            ->set('paymentAmounts.transfer', 100)
            ->set('paymentAmounts.credit', 100)
            ->call('confirmSale');

        $this->assertEquals(0, Sale::count());
        $this->assertEquals(0, SalePayment::count());
    }

    public function test_pos_still_records_a_single_payment_method_sale(): void
    {
        $user = $this->user();
        $product = $this->product(1000);
        $this->openRegister($user);

        Livewire::actingAs($user)
            ->test(CrearVenta::class)
            ->set('printTicket', false)
            ->call('addToCart', $product->id)
            ->call('togglePaymentMethod', 'credit')
            ->call('confirmSale');

        $sale = Sale::query()->first();

        $this->assertNotNull($sale);
        $this->assertEquals(PaymentMethods::CREDIT, $sale->payment_method);
        $this->assertCount(1, $sale->payments);
        $this->assertEquals(1000.0, (float) $sale->payments->first()->amount);
        $this->assertEquals(PaymentMethods::CREDIT, $sale->payments->first()->method);
    }

    public function test_ticket_prints_each_part_of_a_mixed_payment(): void
    {
        $user = $this->user();
        $product = $this->product(1000);
        $register = $this->openRegister($user);

        $sale = Sale::create([
            'user_id' => $user->id,
            'cash_register_id' => $register->id,
            'payment_method' => PaymentMethods::MIXED,
            'subtotal' => 1000,
            'total' => 1000,
            'status' => 'completed',
        ]);

        $sale->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 1000,
            'quantity' => 1,
            'subtotal' => 1000,
        ]);

        $sale->storePayments([
            ['method' => 'cash', 'amount' => 400],
            ['method' => 'card', 'amount' => 600],
        ]);

        $ticket = app(SaleTicketEscPosBuilder::class)->build($sale->fresh(['items', 'payments']));

        $this->assertStringContainsString('Pago mixto', $ticket);
        $this->assertStringContainsString('Efectivo', $ticket);
        $this->assertStringContainsString('Tarjeta', $ticket);
        $this->assertStringContainsString('400,00', $ticket);
        $this->assertStringContainsString('600,00', $ticket);
    }

    public function test_toggling_a_second_method_splits_the_total_in_half(): void
    {
        $user = $this->user();
        $product = $this->product(999);
        $this->openRegister($user);

        $component = Livewire::actingAs($user)
            ->test(CrearVenta::class)
            ->call('addToCart', $product->id)
            ->call('togglePaymentMethod', 'cash')
            ->call('togglePaymentMethod', 'transfer');

        $this->assertEquals([
            'cash' => 499.5,
            'transfer' => 499.5,
        ], $component->get('paymentAmounts'));
        $this->assertTrue($component->instance()->paymentCoversTotal());
    }
}
