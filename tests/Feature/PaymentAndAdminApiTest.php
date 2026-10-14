<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Food;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAndAdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_confirm_payment_and_create_commission(): void
    {
        $owner = RestaurantUser::factory()->create();

        $restaurant = Restaurant::create([
            'owner_id' => $owner->id,
            'name' => 'Coffee Corner',
            'slug' => 'coffee-corner',
            'status' => 'open',
            'approval_status' => 'approved',
            'delivery_fee' => 2.50,
            'minimum_order_value' => 10.00,
            'is_active' => true,
        ]);

        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Lunch',
            'slug' => 'lunch',
            'is_active' => true,
        ]);

        $food = Food::create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'Burger',
            'slug' => 'burger',
            'description' => 'Classic burger.',
            'price' => 18.00,
            'is_available' => true,
            'preparation_time_minutes' => 20,
        ]);

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $order = Order::create([
            'customer_id' => $customer->id,
            'restaurant_id' => $restaurant->id,
            'order_number' => 'ORD-VERIFY-1',
            'status' => 'pending',
            'payment_status' => 'pending',
            'subtotal' => 18.00,
            'delivery_fee' => 2.50,
            'discount_amount' => 0,
            'total' => 20.50,
        ]);

        $order->items()->create([
            'food_id' => $food->id,
            'name' => 'Burger',
            'unit_price' => 18.00,
            'quantity' => 1,
            'subtotal' => 18.00,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$customer->createToken('test')->plainTextToken)
            ->postJson('/api/v1/orders/'.$order->id.'/payments/confirm', [
                'gateway' => 'stripe',
                'reference' => 'pay_ref_123',
                'amount' => 20.50,
            ]);

        $response->assertOk()
            ->assertJsonPath('order.payment_status', 'paid')
            ->assertJsonPath('commission.amount', 2.05);
    }

    public function test_admin_can_access_dashboard_metrics(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->getJson('/api/v1/admin/dashboard');

        $response->assertOk()->assertJsonStructure([
            'customers',
            'restaurants',
            'pending_restaurants',
            'orders',
            'gross_order_value',
            'revenue',
            'commission',
        ]);
    }
}
