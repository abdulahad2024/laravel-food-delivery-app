<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Food;
use App\Models\Restaurant;
use App\Models\RestaurantUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roleNames = ['super_admin', 'admin', 'customer', 'restaurant_staff', 'rider'];

        foreach ($roleNames as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
            ], [
                'display_name' => ucfirst(str_replace('_', ' ', $roleName)),
            ]);
        }

        $admin = User::firstOrCreate([
            'email' => 'admin@foodflow.test',
        ], [
            'name' => 'FoodFlow Admin',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $admin->assignRole('super_admin');

        $owner = RestaurantUser::firstOrCreate([
            'email' => 'owner@foodflow.test',
        ], [
            'name' => 'Restaurant Owner',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $customer = User::firstOrCreate([
            'email' => 'customer@foodflow.test',
        ], [
            'name' => 'Demo Customer',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $customer->assignRole('customer');

        $restaurant = Restaurant::firstOrCreate([
            'slug' => 'urban-bites',
        ], [
            'owner_id' => $owner->id,
            'name' => 'Urban Bites',
            'description' => 'Healthy bowls, grilled meals and fast delivery.',
            'phone' => '+1234567890',
            'email' => 'hello@urbanbites.test',
            'status' => 'open',
            'approval_status' => 'approved',
            'delivery_fee' => 3.99,
            'minimum_order_value' => 12.00,
            'is_active' => true,
        ]);

        $category = Category::firstOrCreate([
            'restaurant_id' => $restaurant->id,
            'slug' => 'signature-bowls',
        ], [
            'name' => 'Signature Bowls',
            'description' => 'Fresh and filling bowls',
            'is_active' => true,
        ]);

        Food::firstOrCreate([
            'restaurant_id' => $restaurant->id,
            'slug' => 'protein-power-bowl',
        ], [
            'category_id' => $category->id,
            'name' => 'Protein Power Bowl',
            'description' => 'Chicken, rice, greens, and avocado.',
            'price' => 14.50,
            'image_url' => 'https://example.com/bowl.jpg',
            'is_available' => true,
            'is_featured' => true,
            'preparation_time_minutes' => 18,
        ]);
    }
}
