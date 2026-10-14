<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Food;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RestaurantOwnerAndRiderFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_restaurant_owner_can_confirm_an_order(): void
    {
        $owner = RestaurantUser::factory()->create();

        $restaurant = Restaurant::create([
            'owner_id' => $owner->id,
            'name' => 'Owner Bistro',
            'slug' => 'owner-bistro',
            'status' => 'open',
            'approval_status' => 'approved',
            'delivery_fee' => 4.25,
            'minimum_order_value' => 15.00,
            'is_active' => true,
        ]);

        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Specials',
            'slug' => 'specials',
            'is_active' => true,
        ]);

        $food = Food::create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'Curry Rice',
            'slug' => 'curry-rice',
            'description' => 'Warm rice and curry.',
            'price' => 14.00,
            'is_available' => true,
        ]);

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $order = Order::create([
            'customer_id' => $customer->id,
            'restaurant_id' => $restaurant->id,
            'order_number' => 'ORD-TEST-1',
            'status' => 'pending',
            'payment_status' => 'paid',
            'subtotal' => 14.00,
            'delivery_fee' => 4.25,
            'discount_amount' => 0,
            'total' => 18.25,
        ]);

        $order->items()->create([
            'food_id' => $food->id,
            'name' => 'Curry Rice',
            'unit_price' => 14.00,
            'quantity' => 1,
            'subtotal' => 14.00,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$owner->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/restaurant/orders/'.$order->id.'/status', [
                'status' => 'confirmed',
            ]);

        $response->assertOk()->assertJsonPath('status', 'confirmed');
    }

    public function test_restaurant_owner_can_show_edit_and_remove_its_restaurant_profile_image(): void
    {
        Storage::fake('public');
        $owner = RestaurantUser::factory()->create();
        $restaurant = Restaurant::create([
            'owner_id' => $owner->id,
            'name' => 'Profile Bistro',
            'slug' => 'profile-bistro',
            'description' => 'Before update',
            'status' => 'open',
            'approval_status' => 'approved',
            'delivery_fee' => 3.25,
            'minimum_order_value' => 10.00,
            'is_active' => true,
        ]);
        $token = $owner->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/restaurant/profile')
            ->assertOk()
            ->assertJsonPath('restaurant.name', 'Profile Bistro')
            ->assertJsonPath('restaurant.description', 'Before update')
            ->assertJsonPath('restaurant.profile_image_url', null);

        $this->withToken($token)
            ->patch('/api/v1/restaurant/profile', [
                'profile_image' => UploadedFile::fake()->create('not-an-image.txt', 1, 'text/plain'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('profile_image');

        $upload = $this->withToken($token)
            ->patch('/api/v1/restaurant/profile', [
                'name' => 'Updated Profile Bistro',
                'description' => 'Updated profile description',
                'phone' => '+15550001111',
                'latitude' => '40.7128',
                'longitude' => '-74.0060',
                'profile_image' => $this->fakeProfileImage('profile.png'),
            ], ['Accept' => 'application/json']);

        $upload->assertOk()
            ->assertJsonPath('name', 'Updated Profile Bistro')
            ->assertJsonPath('description', 'Updated profile description')
            ->assertJsonPath('phone', '+15550001111')
            ->assertJsonPath('profile_image_url', fn (string $url): bool => str_contains($url, '/storage/restaurants/'));

        $imagePath = $restaurant->fresh()->profile_image;
        Storage::disk('public')->assertExists($imagePath);

        $this->withToken($token)
            ->patchJson('/api/v1/restaurant/profile', [
                'remove_profile_image' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('profile_image', null)
            ->assertJsonPath('profile_image_url', null);

        Storage::disk('public')->assertMissing($imagePath);
    }

    public function test_rider_can_accept_and_update_delivery_status(): void
    {
        $restaurant = Restaurant::create([
            'owner_id' => RestaurantUser::factory()->create()->id,
            'name' => 'Fast Lane Foods',
            'slug' => 'fast-lane-foods',
            'status' => 'open',
            'approval_status' => 'approved',
            'delivery_fee' => 5.00,
            'minimum_order_value' => 10.00,
            'is_active' => true,
        ]);

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $rider = User::factory()->create();
        $rider->assignRole('rider');

        $order = Order::create([
            'customer_id' => $customer->id,
            'restaurant_id' => $restaurant->id,
            'rider_id' => null,
            'order_number' => 'ORD-TEST-2',
            'status' => 'ready_for_pickup',
            'delivery_status' => 'pending',
            'payment_status' => 'paid',
            'subtotal' => 20.00,
            'delivery_fee' => 5.00,
            'discount_amount' => 0,
            'total' => 25.00,
        ]);

        $accept = $this->withHeader('Authorization', 'Bearer '.$rider->createToken('test')->plainTextToken)
            ->postJson('/api/v1/rider/deliveries/'.$order->id.'/accept');

        $accept->assertOk()->assertJsonPath('rider_id', $rider->id);

        $pickup = $this->withHeader('Authorization', 'Bearer '.$rider->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/rider/deliveries/'.$order->id.'/status', [
                'delivery_status' => 'picked_up',
            ]);

        $pickup->assertOk()->assertJsonPath('delivery_status', 'picked_up');
    }

    private function fakeProfileImage(string $filename): UploadedFile
    {
        $contents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC', true);

        return UploadedFile::fake()->createWithContent($filename, $contents ?: throw new \RuntimeException('Invalid image fixture.'));
    }
}
