<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Food;
use App\Models\Restaurant;
use App\Models\RestaurantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RestaurantApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_restaurant_listing_returns_sample_data(): void
    {
        $owner = RestaurantUser::factory()->create([
            'email' => 'owner@example.com',
            'status' => 'active',
        ]);

        $restaurant = Restaurant::create([
            'owner_id' => $owner->id,
            'name' => 'Urban Bites',
            'slug' => 'urban-bites',
            'status' => 'open',
            'approval_status' => 'approved',
            'delivery_fee' => 3.99,
            'minimum_order_value' => 12.00,
            'is_active' => true,
        ]);

        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Bowls',
            'slug' => 'bowls',
            'is_active' => true,
        ]);

        Food::create([
            'restaurant_id' => $restaurant->id,
            'category_id' => $category->id,
            'name' => 'Protein Bowl',
            'slug' => 'protein-bowl',
            'description' => 'Lean protein with calories.',
            'price' => 14.50,
            'is_available' => true,
            'is_featured' => true,
            'preparation_time_minutes' => 15,
        ]);

        $response = $this->getJson('/api/v1/restaurants');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Urban Bites')
            ->assertJsonPath('data.0.description', null)
            ->assertJsonPath('data.0.profile_image_url', null);
    }

    public function test_admin_can_upload_a_restaurant_profile_image_and_public_profile_shows_all_details(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $owner = RestaurantUser::factory()->create();

        $response = $this->withToken($admin->createToken('test')->plainTextToken)
            ->post('/api/v1/admin/restaurants', [
                'owner_id' => $owner->id,
                'name' => 'Garden Kitchen',
                'description' => 'Local fresh food.',
                'phone' => '+15551234567',
                'email' => 'hello@garden.example.test',
                'latitude' => '40.7128',
                'longitude' => '-74.0060',
                'profile_image' => $this->fakeProfileImage('restaurant.png'),
                'status' => 'open',
                'approval_status' => 'approved',
                'delivery_fee' => '3.50',
                'minimum_order_value' => '12.00',
                'is_active' => '1',
            ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('name', 'Garden Kitchen')
            ->assertJsonPath('description', 'Local fresh food.')
            ->assertJsonPath('owner.id', $owner->id)
            ->assertJsonPath('profile_image_url', fn (string $url): bool => str_contains($url, '/storage/restaurants/'));

        $restaurant = Restaurant::where('name', 'Garden Kitchen')->firstOrFail();
        Storage::disk('public')->assertExists($restaurant->profile_image);

        $this->getJson('/api/v1/restaurants/'.$restaurant->id)
            ->assertOk()
            ->assertJsonPath('name', 'Garden Kitchen')
            ->assertJsonPath('email', 'hello@garden.example.test')
            ->assertJsonPath('description', 'Local fresh food.')
            ->assertJsonPath('latitude', 40.7128)
            ->assertJsonPath('longitude', -74.006)
            ->assertJsonPath('profile_image_url', $restaurant->profile_image_url);
    }

    private function fakeProfileImage(string $filename): UploadedFile
    {
        $contents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC', true);

        return UploadedFile::fake()->createWithContent($filename, $contents ?: throw new \RuntimeException('Invalid image fixture.'));
    }
}
