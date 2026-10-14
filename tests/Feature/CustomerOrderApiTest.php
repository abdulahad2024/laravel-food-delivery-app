<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Food;
use App\Models\Restaurant;
use App\Models\RestaurantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_preview_and_create_an_order(): void
    {
        $owner = RestaurantUser::factory()->create();

        $restaurant = Restaurant::create([
            'owner_id' => $owner->id,
            'name' => 'Green Bowl House',
            'slug' => 'green-bowl-house',
            'status' => 'open',
            'approval_status' => 'approved',
            'delivery_fee' => 3.5,
            'minimum_order_value' => 10.00,
            'is_active' => true,
        ]);

        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Bowls',
            'slug' => 'bowls',
            'is_active' => true,
        ]);

        $food = Food::create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'Avocado Bowl',
            'slug' => 'avocado-bowl',
            'description' => 'Fresh avocado salad.',
            'price' => 12.50,
            'is_available' => true,
            'preparation_time_minutes' => 15,
        ]);

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $token = $customer->createToken('test')->plainTextToken;

        $preview = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/orders/preview', [
                'restaurant_id' => $restaurant->id,
                'items' => [[
                    'food_id' => $food->id,
                    'quantity' => 2,
                ]],
            ]);

        $preview->assertOk()
            ->assertJsonPath('subtotal', 25)
            ->assertJsonPath('total', 28.5);

        $order = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/orders', [
                'restaurant_id' => $restaurant->id,
                'items' => [[
                    'food_id' => $food->id,
                    'quantity' => 2,
                ]],
                'notes' => 'No onions please',
            ]);

        $order->assertStatus(201)
            ->assertJsonPath('customer_id', $customer->id)
            ->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('order_items', [
            'name' => 'Avocado Bowl',
            'quantity' => 2,
        ]);
    }
}
