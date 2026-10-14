<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Food;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Restaurant;
use App\Models\RestaurantUser;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponRefundSettlementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_apply_a_coupon_during_order_preview_and_checkout(): void
    {
        $owner = RestaurantUser::factory()->create();

        $restaurant = Restaurant::create([
            'owner_id' => $owner->id,
            'name' => 'Sunset Grill',
            'slug' => 'sunset-grill',
            'status' => 'open',
            'approval_status' => 'approved',
            'delivery_fee' => 2.50,
            'minimum_order_value' => 20.00,
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
            'name' => 'Salmon Bowl',
            'slug' => 'salmon-bowl',
            'description' => 'Healthy salmon bowl.',
            'price' => 25.00,
            'is_available' => true,
            'preparation_time_minutes' => 15,
        ]);

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        Coupon::create([
            'code' => 'SAVE5',
            'type' => 'fixed',
            'value' => 5.00,
            'minimum_order_value' => 20.00,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addWeek(),
            'is_active' => true,
        ]);

        $preview = $this->withHeader('Authorization', 'Bearer '.$customer->createToken('test')->plainTextToken)
            ->postJson('/api/v1/orders/preview', [
                'restaurant_id' => $restaurant->id,
                'items' => [[
                    'food_id' => $food->id,
                    'quantity' => 1,
                ]],
                'coupon_code' => 'SAVE5',
            ]);

        $preview->assertOk()
            ->assertJsonPath('subtotal', 25)
            ->assertJsonPath('discount_amount', 5)
            ->assertJsonPath('total', 22.5);

        $order = $this->withHeader('Authorization', 'Bearer '.$customer->createToken('test')->plainTextToken)
            ->postJson('/api/v1/orders', [
                'restaurant_id' => $restaurant->id,
                'items' => [[
                    'food_id' => $food->id,
                    'quantity' => 1,
                ]],
                'coupon_code' => 'SAVE5',
            ]);

        $order->assertStatus(201)
            ->assertJsonPath('discount_amount', 5)
            ->assertJsonPath('coupon_code', 'SAVE5');

        $this->assertDatabaseHas('orders', [
            'coupon_code' => 'SAVE5',
            'discount_amount' => 5.0,
        ]);
    }

    public function test_customer_can_request_refund_and_admin_can_generate_settlement(): void
    {
        $owner = RestaurantUser::factory()->create();

        $restaurant = Restaurant::create([
            'owner_id' => $owner->id,
            'name' => 'Harbor Cafe',
            'slug' => 'harbor-cafe',
            'status' => 'open',
            'approval_status' => 'approved',
            'delivery_fee' => 3.00,
            'minimum_order_value' => 10.00,
            'is_active' => true,
        ]);

        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Dinner',
            'slug' => 'dinner',
            'is_active' => true,
        ]);

        $food = Food::create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'Pasta',
            'slug' => 'pasta',
            'description' => 'Italian pasta.',
            'price' => 18.00,
            'is_available' => true,
            'preparation_time_minutes' => 25,
        ]);

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $order = Order::create([
            'customer_id' => $customer->id,
            'restaurant_id' => $restaurant->id,
            'order_number' => 'ORD-REFUND-1',
            'status' => 'delivered',
            'payment_status' => 'paid',
            'subtotal' => 18.00,
            'delivery_fee' => 3.00,
            'discount_amount' => 0,
            'total' => 21.00,
        ]);

        $order->items()->create([
            'food_id' => $food->id,
            'name' => 'Pasta',
            'unit_price' => 18.00,
            'quantity' => 1,
            'subtotal' => 18.00,
        ]);

        $refund = $this->withHeader('Authorization', 'Bearer '.$customer->createToken('test')->plainTextToken)
            ->postJson('/api/v1/orders/'.$order->id.'/refunds', [
                'amount' => 10.50,
                'reason' => 'Order arrived late',
            ]);

        $refund->assertStatus(201)
            ->assertJsonPath('refund.amount', 10.5)
            ->assertJsonPath('order.payment_status', 'refunded');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $settlement = $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->postJson('/api/v1/admin/settlements/'.$order->id.'/generate');

        $settlement->assertOk()
            ->assertJsonPath('restaurant_id', $restaurant->id)
            ->assertJsonPath('gross_amount', 21)
            ->assertJsonPath('commission_amount', 2.1)
            ->assertJsonPath('net_amount', 18.9);
    }

    public function test_admin_can_manage_coupons_and_view_settlement_summary(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->getJson('/api/v1/admin/coupons')
            ->assertOk();

        $createCoupon = $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->postJson('/api/v1/admin/coupons', [
                'code' => 'CORE25',
                'type' => 'percentage',
                'value' => 25,
                'minimum_order_value' => 50,
                'expires_at' => now()->addMonth()->toDateTimeString(),
                'max_usage' => 10,
                'is_active' => true,
            ]);

        $createCoupon->assertStatus(201)
            ->assertJsonPath('code', 'CORE25');

        $coupon = Coupon::findOrFail($createCoupon->json('id'));

        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/admin/coupons/'.$coupon->id, [
                'code' => 'CORE30',
                'type' => 'percentage',
                'value' => 30,
                'minimum_order_value' => 60,
                'expires_at' => now()->addMonths(2)->toDateTimeString(),
                'max_usage' => 15,
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('code', 'CORE30');

        $owner = RestaurantUser::factory()->create();

        $restaurant = Restaurant::create([
            'owner_id' => $owner->id,
            'name' => 'Payout Summary Kitchen',
            'slug' => 'payout-summary-kitchen',
            'status' => 'open',
            'approval_status' => 'approved',
            'delivery_fee' => 3.00,
            'minimum_order_value' => 10.00,
            'is_active' => true,
        ]);

        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Dinner',
            'slug' => 'dinner',
            'is_active' => true,
        ]);

        $food = Food::create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'Steak Bowl',
            'slug' => 'steak-bowl',
            'description' => 'Steak bowl.',
            'price' => 50.00,
            'is_available' => true,
            'preparation_time_minutes' => 20,
        ]);

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $order = Order::create([
            'customer_id' => $customer->id,
            'restaurant_id' => $restaurant->id,
            'order_number' => 'ORD-PAYOUT-SUMMARY',
            'status' => 'delivered',
            'payment_status' => 'paid',
            'subtotal' => 50.00,
            'delivery_fee' => 3.00,
            'discount_amount' => 0,
            'total' => 53.00,
        ]);

        $order->items()->create([
            'food_id' => $food->id,
            'name' => 'Steak Bowl',
            'unit_price' => 50.00,
            'quantity' => 1,
            'subtotal' => 50.00,
        ]);

        $settlement = $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->postJson('/api/v1/admin/settlements/'.$order->id.'/generate');

        $settlement->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->getJson('/api/v1/admin/settlements/summary')
            ->assertOk()
            ->assertJsonPath('summary.pending.count', 1)
            ->assertJsonPath('total_net_amount', 47.7);

        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->deleteJson('/api/v1/admin/coupons/'.$coupon->id)
            ->assertOk();
    }

    public function test_admin_can_approve_and_pay_settlement(): void
    {
        $owner = RestaurantUser::factory()->create();

        $restaurant = Restaurant::create([
            'owner_id' => $owner->id,
            'name' => 'Harbor Finance',
            'slug' => 'harbor-finance',
            'status' => 'open',
            'approval_status' => 'approved',
            'delivery_fee' => 3.00,
            'minimum_order_value' => 15.00,
            'is_active' => true,
        ]);

        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Dinner',
            'slug' => 'dinner',
            'is_active' => true,
        ]);

        $food = Food::create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'Burger',
            'slug' => 'burger',
            'description' => 'Loaded burger.',
            'price' => 40.00,
            'is_available' => true,
            'preparation_time_minutes' => 20,
        ]);

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $order = Order::create([
            'customer_id' => $customer->id,
            'restaurant_id' => $restaurant->id,
            'order_number' => 'ORD-SETTLE-1',
            'status' => 'delivered',
            'payment_status' => 'paid',
            'subtotal' => 40.00,
            'delivery_fee' => 3.00,
            'discount_amount' => 0,
            'total' => 43.00,
        ]);

        $order->items()->create([
            'food_id' => $food->id,
            'name' => 'Burger',
            'unit_price' => 40.00,
            'quantity' => 1,
            'subtotal' => 40.00,
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $settlement = $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->postJson('/api/v1/admin/settlements/'.$order->id.'/generate');

        $settlement->assertOk()
            ->assertJsonPath('status', 'pending');

        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/admin/settlements/'.$settlement->json('id'), [
                'status' => 'approved',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'approved');

        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/admin/settlements/'.$settlement->json('id'), [
                'status' => 'paid',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'paid');

        $this->assertDatabaseHas('commissions', [
            'order_id' => $order->id,
            'status' => 'paid',
        ]);
    }

    public function test_admin_can_reconcile_payout_ledger_and_settlement_status_changes(): void
    {
        $owner = RestaurantUser::factory()->create();

        $restaurant = Restaurant::create([
            'owner_id' => $owner->id,
            'name' => 'Ledger Lane',
            'slug' => 'ledger-lane',
            'status' => 'open',
            'approval_status' => 'approved',
            'delivery_fee' => 2.50,
            'minimum_order_value' => 15.00,
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
            'name' => 'Grilled Chicken',
            'slug' => 'grilled-chicken',
            'description' => 'Grilled chicken plate.',
            'price' => 35.00,
            'is_available' => true,
            'preparation_time_minutes' => 18,
        ]);

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $order = Order::create([
            'customer_id' => $customer->id,
            'restaurant_id' => $restaurant->id,
            'order_number' => 'ORD-LEDGER-1',
            'status' => 'delivered',
            'payment_status' => 'paid',
            'subtotal' => 35.00,
            'delivery_fee' => 2.50,
            'discount_amount' => 0,
            'total' => 37.50,
        ]);

        $order->items()->create([
            'food_id' => $food->id,
            'name' => 'Grilled Chicken',
            'unit_price' => 35.00,
            'quantity' => 1,
            'subtotal' => 35.00,
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->postJson('/api/v1/admin/settlements/'.$order->id.'/generate')
            ->assertOk()
            ->assertJsonPath('net_amount', 33.75);

        $settlement = Settlement::where('order_id', $order->id)->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->getJson('/api/v1/admin/payout-ledger/summary')
            ->assertOk()
            ->assertJsonPath('summary.pending.count', 1)
            ->assertJsonPath('summary.pending.amount', 33.75);

        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/admin/settlements/'.$settlement->id, [
                'status' => 'approved',
            ])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/admin/settlements/'.$settlement->id, [
                'status' => 'paid',
            ])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->getJson('/api/v1/admin/payout-ledger')
            ->assertOk()
            ->assertJsonFragment([
                'status' => 'paid',
            ]);

        $this->assertDatabaseHas('payout_ledgers', [
            'settlement_id' => $settlement->id,
            'status' => 'paid',
            'type' => 'payout',
        ]);
    }

    public function test_admin_can_export_settlement_and_payout_ledger_csv_reports(): void
    {
        $owner = RestaurantUser::factory()->create();

        $restaurant = Restaurant::create([
            'owner_id' => $owner->id,
            'name' => 'Export Reports',
            'slug' => 'export-reports',
            'status' => 'open',
            'approval_status' => 'approved',
            'delivery_fee' => 2.50,
            'minimum_order_value' => 15.00,
            'is_active' => true,
        ]);

        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Dinner',
            'slug' => 'dinner',
            'is_active' => true,
        ]);

        $food = Food::create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'Rice Bowl',
            'slug' => 'rice-bowl',
            'description' => 'Protein rice bowl.',
            'price' => 30.00,
            'is_available' => true,
            'preparation_time_minutes' => 20,
        ]);

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $order = Order::create([
            'customer_id' => $customer->id,
            'restaurant_id' => $restaurant->id,
            'order_number' => 'ORD-EXPORT-1',
            'status' => 'delivered',
            'payment_status' => 'paid',
            'subtotal' => 30.00,
            'delivery_fee' => 2.50,
            'discount_amount' => 0,
            'total' => 32.50,
        ]);

        $order->items()->create([
            'food_id' => $food->id,
            'name' => 'Rice Bowl',
            'unit_price' => 30.00,
            'quantity' => 1,
            'subtotal' => 30.00,
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->postJson('/api/v1/restaurant/settlements/'.$order->id.'/generate');

        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->get('/api/v1/admin/settlements/export')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->get('/api/v1/admin/payout-ledger/export')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_coupon_usage_limit_is_enforced_and_admin_can_review_refund_requests(): void
    {
        $owner = RestaurantUser::factory()->create();

        $restaurant = Restaurant::create([
            'owner_id' => $owner->id,
            'name' => 'Blue Bird Diner',
            'slug' => 'blue-bird-diner',
            'status' => 'open',
            'approval_status' => 'approved',
            'delivery_fee' => 2.50,
            'minimum_order_value' => 20.00,
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
            'name' => 'Chicken Wrap',
            'slug' => 'chicken-wrap',
            'description' => 'Crispy chicken wrap.',
            'price' => 25.00,
            'is_available' => true,
            'preparation_time_minutes' => 12,
        ]);

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $coupon = Coupon::create([
            'code' => 'LIMIT1',
            'type' => 'fixed',
            'value' => 5.00,
            'minimum_order_value' => 20.00,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addWeek(),
            'max_usage' => 1,
            'is_active' => true,
        ]);

        $firstOrder = Order::create([
            'customer_id' => $customer->id,
            'restaurant_id' => $restaurant->id,
            'coupon_id' => $coupon->id,
            'coupon_code' => $coupon->code,
            'coupon_discount_amount' => 5.00,
            'order_number' => 'ORD-LIMIT-1',
            'status' => 'delivered',
            'payment_status' => 'paid',
            'subtotal' => 25.00,
            'delivery_fee' => 2.50,
            'discount_amount' => 5.00,
            'total' => 22.50,
        ]);

        $firstOrder->items()->create([
            'food_id' => $food->id,
            'name' => 'Chicken Wrap',
            'unit_price' => 25.00,
            'quantity' => 1,
            'subtotal' => 25.00,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$customer->createToken('test')->plainTextToken)
            ->postJson('/api/v1/orders/preview', [
                'restaurant_id' => $restaurant->id,
                'items' => [[
                    'food_id' => $food->id,
                    'quantity' => 1,
                ]],
                'coupon_code' => 'LIMIT1',
            ])
            ->assertStatus(422);

        $refundOrder = Order::create([
            'customer_id' => $customer->id,
            'restaurant_id' => $restaurant->id,
            'order_number' => 'ORD-REFUND-APPROVAL',
            'status' => 'delivered',
            'payment_status' => 'paid',
            'subtotal' => 30.00,
            'delivery_fee' => 2.50,
            'discount_amount' => 0,
            'total' => 32.50,
        ]);

        $refundOrder->items()->create([
            'food_id' => $food->id,
            'name' => 'Chicken Wrap',
            'unit_price' => 25.00,
            'quantity' => 1,
            'subtotal' => 25.00,
        ]);

        $refund = Refund::create([
            'order_id' => $refundOrder->id,
            'user_id' => $customer->id,
            'amount' => 12.50,
            'reason' => 'Late delivery',
            'status' => 'requested',
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/admin/refunds/'.$refund->id, [
                'status' => 'approved',
            ])
            ->assertOk()
            ->assertJsonPath('refund.status', 'approved')
            ->assertJsonPath('order.payment_status', 'refunded');
    }
}
