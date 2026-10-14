<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Commission;
use App\Models\Coupon;
use App\Models\Food;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Restaurant;
use App\Models\RestaurantUser;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminWebPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_all_database_backed_panel_sections_and_guests_are_redirected(): void
    {
        $admin = $this->makeAdmin();
        $restaurant = $this->makeRestaurant();
        $customer = User::factory()->create(['status' => 'active']);
        $order = $this->makeOrder($customer, $restaurant);

        $this->get('/admin')->assertRedirect(route('login'));

        $this->actingAs($admin);

        $this->get('/admin')
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee($restaurant->name);

        foreach ([
            '/admin',
            '/admin/users',
            '/admin/restaurant-users',
            '/admin/restaurants',
            '/admin/menu',
            '/admin/orders',
            '/admin/payments',
            '/admin/refunds',
            '/admin/coupons',
            '/admin/settlements',
            '/admin/payout-ledger',
        ] as $path) {
            $this->get($path)->assertOk();
        }

        $this->get('/admin/orders?from=2026-10-01')->assertOk();
    }

    public function test_web_login_accepts_active_admins_and_rejects_non_admin_accounts(): void
    {
        $customer = User::factory()->create([
            'email' => 'panel.customer@example.test',
            'password' => 'password123',
            'status' => 'active',
        ]);
        $customer->assignRole('customer');

        $this->post('/login', [
            'email' => $customer->email,
            'password' => 'password123',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();

        $admin = $this->makeAdmin();

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_can_create_and_update_users_restaurants_and_menu_items(): void
    {
        Storage::fake('public');
        $admin = $this->makeAdmin();
        $owner = RestaurantUser::factory()->create(['status' => 'active']);

        $this->actingAs($admin)
            ->post('/admin/users', [
                'name' => 'Panel Customer',
                'email' => 'panel.customer@example.test',
                'phone' => '+15550001111',
                'password' => 'strong-password',
                'status' => 'active',
                'role' => 'customer',
            ])
            ->assertSessionHasNoErrors();

        $customer = User::where('email', 'panel.customer@example.test')->firstOrFail();
        $this->assertDatabaseHas('role_user', ['user_id' => $customer->id]);

        $this->put('/admin/users/'.$customer->id, [
            'name' => 'Updated Panel Customer',
            'email' => $customer->email,
            'phone' => '+15550002222',
            'status' => 'inactive',
            'role' => 'customer',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'name' => 'Updated Panel Customer',
            'phone' => '+15550002222',
            'status' => 'inactive',
        ]);

        $this->post('/admin/restaurants', [
            'owner_id' => $owner->id,
            'name' => 'Panel Restaurant',
            'email' => 'restaurant@example.test',
            'phone' => '555-0111',
            'delivery_fee' => 3.50,
            'minimum_order_value' => 10,
            'status' => 'draft',
            'approval_status' => 'pending',
            'profile_image' => $this->fakeProfileImage('panel-restaurant.png'),
        ])->assertSessionHasNoErrors();

        $restaurant = Restaurant::where('name', 'Panel Restaurant')->firstOrFail();
        $oldProfileImage = $restaurant->profile_image;
        Storage::disk('public')->assertExists($oldProfileImage);

        $this->put('/admin/restaurants/'.$restaurant->id, [
            'name' => 'Updated Panel Restaurant',
            'email' => 'restaurant@example.test',
            'phone' => '555-0222',
            'delivery_fee' => 4.25,
            'minimum_order_value' => 15,
            'status' => 'open',
            'approval_status' => 'approved',
            'is_active' => 0,
            'profile_image' => $this->fakeProfileImage('updated-panel-restaurant.png'),
        ])->assertSessionHasNoErrors();

        $newProfileImage = $restaurant->fresh()->profile_image;
        Storage::disk('public')->assertMissing($oldProfileImage);
        Storage::disk('public')->assertExists($newProfileImage);

        $this->get('/admin/restaurants')
            ->assertOk()
            ->assertSee('Updated Panel Restaurant profile image');

        $this->assertDatabaseHas('restaurants', [
            'id' => $restaurant->id,
            'name' => 'Updated Panel Restaurant',
            'status' => 'open',
            'approval_status' => 'approved',
            'is_active' => 0,
        ]);
        $this->assertSame('active', $owner->fresh()->status);

        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Lunch',
            'slug' => 'panel-lunch',
            'is_active' => true,
        ]);

        $this->post('/admin/menu', [
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'Panel Salad',
            'description' => 'Fresh salad',
            'price' => 9.25,
            'preparation_time_minutes' => 15,
        ])->assertSessionHasNoErrors();

        $food = Food::where('name', 'Panel Salad')->firstOrFail();

        $this->put('/admin/menu/'.$food->id, [
            'price' => 10.50,
            'is_available' => 0,
            'is_featured' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('foods', [
            'id' => $food->id,
            'price' => 10.50,
            'is_available' => 0,
            'is_featured' => 1,
        ]);
    }

    public function test_admin_can_create_and_update_separate_restaurant_accounts(): void
    {
        $this->actingAs($this->makeAdmin())
            ->post('/admin/restaurant-users', [
                'name' => 'Restaurant Account',
                'email' => 'restaurant.account@example.test',
                'phone' => '+15550003333',
                'password' => 'strong-password',
                'status' => 'active',
            ])
            ->assertSessionHasNoErrors();

        $restaurantUser = RestaurantUser::where('email', 'restaurant.account@example.test')->firstOrFail();
        $originalPassword = $restaurantUser->password;

        $this->put('/admin/restaurant-users/'.$restaurantUser->id, [
            'name' => 'Updated Restaurant Account',
            'email' => $restaurantUser->email,
            'phone' => '+15550004444',
            'password' => '',
            'status' => 'blocked',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('restaurant_users', [
            'id' => $restaurantUser->id,
            'name' => 'Updated Restaurant Account',
            'status' => 'blocked',
        ]);

        $this->assertSame($originalPassword, $restaurantUser->fresh()->password);

        $this->put('/admin/restaurant-users/'.$restaurantUser->id, [
            'name' => 'Updated Restaurant Account',
            'email' => $restaurantUser->email,
            'phone' => '+15550004444',
            'password' => 'updated-password',
            'status' => 'blocked',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(password_verify('updated-password', $restaurantUser->fresh()->password));
    }

    public function test_admin_order_updates_follow_lifecycle_and_write_customer_notifications(): void
    {
        $admin = $this->makeAdmin();
        $restaurant = $this->makeRestaurant();
        $customer = User::factory()->create(['status' => 'active']);
        $order = $this->makeOrder($customer, $restaurant, ['status' => 'ready_for_pickup']);

        $this->actingAs($admin)
            ->post('/admin/orders/'.$order->id.'/status', ['status' => 'picked_up'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'picked_up',
            'delivery_status' => 'picked_up',
        ]);
        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $customer->id,
            'event' => 'order_status_updated',
        ]);

        $this->post('/admin/orders/'.$order->id.'/status', ['status' => 'cancelled'])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'picked_up']);
    }

    public function test_admin_refund_review_synchronizes_order_payment_and_rejects_invalid_transitions(): void
    {
        $admin = $this->makeAdmin();
        $restaurant = $this->makeRestaurant();
        $customer = User::factory()->create(['status' => 'active']);
        $order = $this->makeOrder($customer, $restaurant, ['payment_status' => 'paid']);
        $refund = Refund::create([
            'order_id' => $order->id,
            'user_id' => $customer->id,
            'amount' => 12.50,
            'reason' => 'Order issue',
            'status' => 'requested',
        ]);

        $this->actingAs($admin)
            ->post('/admin/refunds/'.$refund->id.'/status', ['status' => 'approved'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('refunds', ['id' => $refund->id, 'status' => 'approved']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'refunded']);

        $this->post('/admin/refunds/'.$refund->id.'/status', ['status' => 'rejected'])
            ->assertSessionHasErrors('status');
        $this->assertDatabaseHas('refunds', ['id' => $refund->id, 'status' => 'approved']);

        $this->post('/admin/refunds/'.$refund->id.'/status', ['status' => 'paid'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('refunds', ['id' => $refund->id, 'status' => 'paid']);
    }

    public function test_admin_coupon_crud_and_settlement_updates_are_persisted_with_ledger_entries(): void
    {
        $admin = $this->makeAdmin();
        $restaurant = $this->makeRestaurant();
        $customer = User::factory()->create(['status' => 'active']);
        $order = $this->makeOrder($customer, $restaurant, ['payment_status' => 'paid']);
        $commission = Commission::create([
            'order_id' => $order->id,
            'restaurant_id' => $restaurant->id,
            'amount' => 2.00,
            'rate' => 10,
            'status' => 'pending',
        ]);
        $settlement = Settlement::create([
            'restaurant_id' => $restaurant->id,
            'order_id' => $order->id,
            'gross_amount' => 20,
            'commission_amount' => 2,
            'net_amount' => 18,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post('/admin/coupons', [
                'code' => 'panel10',
                'type' => 'percentage',
                'value' => 10,
                'minimum_order_value' => 20,
                'max_usage' => 100,
                'is_active' => 1,
            ])
            ->assertSessionHasNoErrors();

        $coupon = Coupon::where('code', 'PANEL10')->firstOrFail();
        $this->put('/admin/coupons/'.$coupon->id, [
            'code' => 'panel15',
            'type' => 'percentage',
            'value' => 15,
            'minimum_order_value' => 25,
            'max_usage' => 50,
            'is_active' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'code' => 'PANEL15', 'value' => 15]);

        $this->post('/admin/settlements/'.$settlement->id.'/status', ['status' => 'approved'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('commissions', ['id' => $commission->id, 'status' => 'approved']);
        $this->assertDatabaseHas('payout_ledgers', [
            'settlement_id' => $settlement->id,
            'status' => 'approved',
            'type' => 'settlement',
        ]);

        $this->post('/admin/settlements/'.$settlement->id.'/status', ['status' => 'paid'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payout_ledgers', [
            'settlement_id' => $settlement->id,
            'status' => 'paid',
            'type' => 'payout',
        ]);
        $this->get('/admin/settlements?q=Test%20Restaurant&status=paid')
            ->assertOk()
            ->assertSee($restaurant->name);
        $this->get('/admin/settlements/export?status=paid')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=utf-8');
        $this->get('/admin/payout-ledger/export?status=paid')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=utf-8');

        $this->delete('/admin/coupons/'.$coupon->id)->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    public function test_admin_cannot_assign_privileged_roles_or_access_panel_with_a_disabled_account(): void
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('admin');
        $otherAdmin = User::factory()->create(['status' => 'active']);
        $otherAdmin->assignRole('admin');

        $this->actingAs($admin)
            ->post('/admin/users', [
                'name' => 'Escalation Attempt',
                'email' => 'escalation@example.test',
                'password' => 'strong-password',
                'status' => 'active',
                'role' => 'super_admin',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'escalation@example.test']);
        $this->put('/admin/users/'.$otherAdmin->id, [
            'name' => $otherAdmin->name,
            'email' => $otherAdmin->email,
            'status' => 'inactive',
            'role' => 'customer',
        ])->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id, 'status' => 'active']);

        $admin->status = 'blocked';
        $admin->save();

        $this->actingAs($admin)->get('/admin')->assertForbidden();
    }

    private function makeAdmin(): User
    {
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('super_admin');

        return $admin;
    }

    private function makeRestaurant(): Restaurant
    {
        $owner = RestaurantUser::factory()->create(['status' => 'active']);

        return Restaurant::create([
            'owner_id' => $owner->id,
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant-'.$owner->id,
            'status' => 'open',
            'approval_status' => 'approved',
            'delivery_fee' => 3.50,
            'minimum_order_value' => 10,
            'is_active' => true,
        ]);
    }

    private function makeOrder(User $customer, Restaurant $restaurant, array $overrides = []): Order
    {
        return Order::create(array_merge([
            'customer_id' => $customer->id,
            'restaurant_id' => $restaurant->id,
            'order_number' => 'ORD-PANEL-'.$customer->id.'-'.uniqid(),
            'status' => 'pending',
            'payment_status' => 'pending',
            'subtotal' => 20,
            'delivery_fee' => 0,
            'discount_amount' => 0,
            'total' => 20,
        ], $overrides));
    }

    private function fakeProfileImage(string $filename): UploadedFile
    {
        $contents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC', true);

        return UploadedFile::fake()->createWithContent($filename, $contents ?: throw new \RuntimeException('Invalid image fixture.'));
    }
}
