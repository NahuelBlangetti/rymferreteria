<?php

namespace Tests\Feature;

use App\Filament\Pages\CrearVenta;
use App\Filament\Pages\PaymentSurchargeSettings;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\PaymentSurchargeSetting;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\PaymentMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentSurchargePosTest extends TestCase
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

    private function configureCredit(float $percentage = 15): void
    {
        PaymentSurchargeSetting::current()->update([
            'credit_percentage' => $percentage,
        ]);
        PaymentSurchargeSetting::forgetCache();
    }

    public function test_pos_reprices_the_cart_to_the_final_credit_amount(): void
    {
        $this->configureCredit(15);

        $user = $this->user();
        $product = $this->product(1000);
        $this->openRegister($user);

        $component = Livewire::actingAs($user)
            ->test(CrearVenta::class)
            ->set('printTicket', false)
            ->call('addToCart', $product->id);

        $this->assertEquals(1000.0, $component->get('cartItems.0.unit_price'));

        $component->call('togglePaymentMethod', 'credit');

        $this->assertEquals(1150.0, $component->get('cartItems.0.unit_price'));
        $this->assertEquals(1150.0, $component->get('cartItems.0.subtotal'));
        $this->assertEquals(1000.0, $component->get('cartItems.0.base_price'));
    }

    public function test_pos_records_the_final_credit_price_without_a_surcharge_line(): void
    {
        $this->configureCredit(15);

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
        $this->assertEquals(1150.0, (float) $sale->total);
        $this->assertEquals(1150.0, (float) $sale->items->first()->unit_price);
        $this->assertEquals(1150.0, (float) $sale->payments->first()->amount);
    }

    public function test_mixed_cash_and_credit_uses_the_credit_final_price_for_the_whole_cart(): void
    {
        $this->configureCredit(15);

        $user = $this->user();
        $product = $this->product(1000);
        $this->openRegister($user);

        Livewire::actingAs($user)
            ->test(CrearVenta::class)
            ->set('printTicket', false)
            ->call('addToCart', $product->id)
            ->call('togglePaymentMethod', 'cash')
            ->call('togglePaymentMethod', 'credit')
            ->call('confirmSale');

        $sale = Sale::query()->first();

        $this->assertEquals(PaymentMethods::MIXED, $sale->payment_method);
        $this->assertEquals(1150.0, (float) $sale->total);
        $this->assertEquals(575.0, (float) $sale->payments->firstWhere('method', 'cash')->amount);
        $this->assertEquals(575.0, (float) $sale->payments->firstWhere('method', 'credit')->amount);
    }

    public function test_settings_page_saves_percentages(): void
    {
        $user = $this->user();

        Livewire::actingAs($user)
            ->test(PaymentSurchargeSettings::class)
            ->fillForm([
                'cash_percentage' => 0,
                'cards_mode' => 'per_card',
                'debit_percentage' => 8,
                'credit_percentage' => 15,
                'rounding_step' => 0,
                'rounding_mode' => 'up',
            ])
            ->call('save');

        PaymentSurchargeSetting::forgetCache();
        $settings = PaymentSurchargeSetting::current();

        $this->assertEquals(8.0, (float) $settings->debit_percentage);
        $this->assertEquals(15.0, (float) $settings->credit_percentage);
    }

    public function test_settings_page_can_apply_one_percentage_to_debit_and_credit(): void
    {
        $user = $this->user();

        Livewire::actingAs($user)
            ->test(PaymentSurchargeSettings::class)
            ->fillForm([
                'cash_percentage' => 0,
                'cards_mode' => 'same',
                'cards_percentage' => 12,
                'rounding_step' => 0,
                'rounding_mode' => 'up',
            ])
            ->call('save');

        PaymentSurchargeSetting::forgetCache();
        $settings = PaymentSurchargeSetting::current();

        $this->assertEquals(0.0, (float) $settings->cash_percentage);
        $this->assertEquals(12.0, (float) $settings->debit_percentage);
        $this->assertEquals(12.0, (float) $settings->credit_percentage);
    }

    public function test_single_percentage_saves_even_if_the_per_card_fields_were_left_empty(): void
    {
        $user = $this->user();

        Livewire::actingAs($user)
            ->test(PaymentSurchargeSettings::class)
            ->fillForm([
                'cash_percentage' => 0,
                'cards_mode' => 'same',
                'cards_percentage' => 10,
                'debit_percentage' => '',
                'credit_percentage' => '',
                'rounding_step' => 0,
                'rounding_mode' => 'up',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        PaymentSurchargeSetting::forgetCache();
        $settings = PaymentSurchargeSetting::current();

        $this->assertEquals(10.0, (float) $settings->debit_percentage);
        $this->assertEquals(10.0, (float) $settings->credit_percentage);
    }

    public function test_transfer_is_always_charged_like_cash(): void
    {
        $user = $this->user();

        Livewire::actingAs($user)
            ->test(PaymentSurchargeSettings::class)
            ->fillForm([
                'cash_percentage' => 4,
                'cards_mode' => 'same',
                'cards_percentage' => 20,
                'rounding_step' => 0,
                'rounding_mode' => 'up',
            ])
            ->call('save');

        PaymentSurchargeSetting::forgetCache();
        $product = $this->product(1000);

        $this->assertEquals(1040.0, $product->priceFor(PaymentMethods::CASH));
        $this->assertEquals(1040.0, $product->priceFor(PaymentMethods::TRANSFER));
        $this->assertEquals(1200.0, $product->priceFor(PaymentMethods::CREDIT));
    }

    public function test_product_pages_and_settings_render(): void
    {
        $user = $this->user();

        Livewire::actingAs($user)
            ->test(PaymentSurchargeSettings::class)
            ->assertOk();

        Livewire::actingAs($user)
            ->test(ListProducts::class)
            ->assertOk();

        Livewire::actingAs($user)
            ->test(CreateProduct::class)
            ->assertOk()
            ->assertSee('valores por medio de pago');
    }

    public function test_product_exposes_final_prices_per_method(): void
    {
        $this->configureCredit(15);
        PaymentSurchargeSetting::current()->update(['debit_percentage' => 8]);
        PaymentSurchargeSetting::forgetCache();

        $product = $this->product(1000);

        $this->assertEquals(1000.0, $product->priceFor(PaymentMethods::CASH));
        $this->assertEquals(1080.0, $product->priceFor(PaymentMethods::DEBIT));
        $this->assertEquals(1150.0, $product->priceFor(PaymentMethods::CREDIT));
    }
}
