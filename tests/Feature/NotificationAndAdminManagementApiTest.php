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

class NotificationAndAdminManagementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_receives_order_notifications_and_admin_can_list_users(): void
    {
        $owner = RestaurantUser::factory()->create();

        $restaurant = Restaurant::create([
            'owner_id' => $owner->id,
            'name' => 'Market Table',
            'slug' => 'market-table',
            'status' => 'open',
            'approval_status' => 'approved',
            'delivery_fee' => 3.50,
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
            'name' => 'Pesto Pasta',
            'slug' => 'pesto-pasta',
            'description' => 'Fresh basil pesto pasta.',
            'price' => 18.00,
            'is_available' => true,
            'preparation_time_minutes' => 20,
        ]);

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $order = Order::create([
            'customer_id' => $customer->id,
            'restaurant_id' => $restaurant->id,
            'order_number' => 'ORD-NOTIFY-1',
            'status' => 'pending',
            'payment_status' => 'paid',
            'subtotal' => 18.00,
            'delivery_fee' => 3.50,
            'discount_amount' => 0,
            'total' => 21.50,
        ]);

        $order->items()->create([
            'food_id' => $food->id,
            'name' => 'Pesto Pasta',
            'unit_price' => 18.00,
            'quantity' => 1,
            'subtotal' => 18.00,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$owner->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/restaurant/orders/'.$order->id.'/status', [
                'status' => 'confirmed',
            ])
            ->assertOk();

        $this->assertDatabaseHas('notification_logs', [
            'user_id' => $customer->id,
            'event' => 'order_status_updated',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$customer->createToken('test')->plainTextToken)
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonFragment([
                'event' => 'order_status_updated',
            ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->getJson('/api/v1/admin/users?role=customer')
            ->assertOk()
            ->assertJsonPath('data.0.roles.0.name', 'customer');
    }

    public function test_admin_can_create_update_and_delete_users_and_restaurants(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $userResponse = $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->postJson('/api/v1/admin/users', [
                'name' => 'Rider User',
                'email' => 'rider.user@example.com',
                'phone' => '+1234567890',
                'password' => 'secret123',
                'status' => 'active',
                'role' => 'rider',
            ]);

        $userResponse->assertStatus(201)
            ->assertJsonPath('name', 'Rider User')
            ->assertJsonPath('roles.0.name', 'rider');

        $createdUser = User::where('email', 'rider.user@example.com')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/admin/users/'.$createdUser->id, [
                'status' => 'inactive',
                'role' => 'customer',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'inactive')
            ->assertJsonPath('roles.0.name', 'customer');

        $owner = RestaurantUser::factory()->create();

        $restaurantResponse = $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->postJson('/api/v1/admin/restaurants', [
                'owner_id' => $owner->id,
                'name' => 'Metro Bites',
                'status' => 'open',
                'approval_status' => 'approved',
                'delivery_fee' => 3.50,
                'minimum_order_value' => 20.00,
                'is_active' => true,
            ]);

        $restaurantResponse->assertStatus(201)
            ->assertJsonPath('name', 'Metro Bites')
            ->assertJsonPath('owner.id', $owner->id);

        $restaurant = Restaurant::findOrFail($restaurantResponse->json('id'));

        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/admin/restaurants/'.$restaurant->id, [
                'status' => 'closed',
                'approval_status' => 'approved',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'closed');

        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->deleteJson('/api/v1/admin/users/'.$createdUser->id)
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->deleteJson('/api/v1/admin/restaurants/'.$restaurant->id)
            ->assertOk();

        $this->assertDatabaseMissing('users', ['email' => 'rider.user@example.com']);
        $this->assertDatabaseMissing('restaurants', ['id' => $restaurant->id]);
    }

    public function test_restaurant_owner_can_update_profile_and_admin_manages_restaurant_accounts_separately(): void
    {
        $owner = RestaurantUser::factory()->create();

        $restaurant = Restaurant::create([
            'owner_id' => $owner->id,
            'name' => 'North Wind',
            'slug' => 'north-wind',
            'status' => 'open',
            'approval_status' => 'approved',
            'delivery_fee' => 2.50,
            'minimum_order_value' => 15.00,
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$owner->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/restaurant/profile', [
                'status' => 'closed',
                'delivery_fee' => 4.00,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'closed')
            ->assertJsonPath('delivery_fee', 4.0);

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/admin/users/'.$customer->id.'/role', [
                'role' => 'restaurant_owner',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertDatabaseHas('role_user', [
            'user_id' => $customer->id,
        ]);

        $restaurantUserResponse = $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->postJson('/api/v1/admin/restaurant-users', [
                'name' => 'New Restaurant Owner',
                'email' => 'new.owner@example.test',
                'password' => 'strong-password',
                'status' => 'active',
            ]);

        $restaurantUserResponse->assertCreated()
            ->assertJsonPath('name', 'New Restaurant Owner');
        $this->assertDatabaseHas('restaurant_users', ['email' => 'new.owner@example.test']);

        $createdRestaurantUser = RestaurantUser::where('email', 'new.owner@example.test')->firstOrFail();
        $passwordHash = $createdRestaurantUser->password;

        $this->withHeader('Authorization', 'Bearer '.$admin->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/admin/restaurant-users/'.$createdRestaurantUser->id, [
                'password' => null,
                'status' => 'blocked',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'blocked');

        $this->assertSame($passwordHash, $createdRestaurantUser->fresh()->password);
    }
}
