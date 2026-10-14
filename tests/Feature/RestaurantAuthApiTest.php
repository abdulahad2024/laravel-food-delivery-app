<?php

namespace Tests\Feature;

use App\Models\RestaurantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RestaurantAuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_restaurant_signup_creates_an_independent_account_and_pending_restaurant(): void
    {
        $response = $this->postJson('/api/v1/restaurant/auth/register', [
            'name' => 'Restaurant Owner',
            'email' => 'OWNER@example.test',
            'phone' => '+15551234567',
            'password' => 'restaurant-password',
            'password_confirmation' => 'restaurant-password',
            'restaurant_name' => 'Fresh Table',
        ]);

        $response->assertCreated()
            ->assertJsonPath('restaurant_user.email', 'owner@example.test')
            ->assertJsonPath('restaurant_user.restaurants.0.name', 'Fresh Table')
            ->assertJsonPath('restaurant_user.restaurants.0.approval_status', 'pending')
            ->assertJsonMissingPath('restaurant_user.legacy_user_id')
            ->assertJsonMissingPath('restaurant_user.legacy_user_status');

        $restaurantUser = RestaurantUser::where('email', 'owner@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('restaurant-password', $restaurantUser->password));
        $this->assertDatabaseMissing('users', ['email' => 'owner@example.test']);
        $this->assertDatabaseHas('restaurants', [
            'owner_id' => $restaurantUser->id,
            'name' => 'Fresh Table',
            'status' => 'draft',
        ]);

        $this->withToken($response->json('token'))
            ->getJson('/api/v1/restaurant/auth/me')
            ->assertOk()
            ->assertJsonPath('restaurant_user.id', $restaurantUser->id);

        $this->withToken($response->json('token'))
            ->postJson('/api/v1/restaurant/auth/logout')
            ->assertOk();

        $this->withToken($response->json('token'))
            ->getJson('/api/v1/restaurant/auth/me')
            ->assertUnauthorized();
    }

    public function test_restaurant_login_rejects_inactive_accounts(): void
    {
        $restaurantUser = RestaurantUser::factory()->create([
            'email' => 'inactive-owner@example.test',
            'password' => 'restaurant-password',
            'status' => 'inactive',
        ]);

        $this->postJson('/api/v1/restaurant/auth/login', [
            'email' => $restaurantUser->email,
            'password' => 'restaurant-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_platform_and_restaurant_signups_reject_emails_used_by_the_other_account_type(): void
    {
        User::factory()->create(['email' => 'platform@example.test']);

        $this->postJson('/api/v1/restaurant/auth/register', [
            'name' => 'Restaurant Owner',
            'email' => 'platform@example.test',
            'password' => 'restaurant-password',
            'password_confirmation' => 'restaurant-password',
            'restaurant_name' => 'Fresh Table',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        RestaurantUser::factory()->create(['email' => 'restaurant@example.test']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Platform Customer',
            'email' => 'restaurant@example.test',
            'password' => 'platform-password',
            'password_confirmation' => 'platform-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_restaurant_and_platform_tokens_cannot_cross_authentication_boundaries(): void
    {
        $restaurantUser = RestaurantUser::factory()->create();
        $restaurantToken = $restaurantUser->createToken('test')->plainTextToken;
        $platformUser = User::factory()->create();
        $platformToken = $platformUser->createToken('test')->plainTextToken;

        $this->withToken($platformToken)
            ->getJson('/api/v1/restaurant/auth/me')
            ->assertForbidden();

        $this->withToken($restaurantToken)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden();
    }
}
