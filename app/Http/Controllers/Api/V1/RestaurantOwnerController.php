<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Food;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Restaurant;
use App\Support\RestaurantProfileImageManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RestaurantOwnerController extends Controller
{
    public function dashboard(Request $request)
    {
        $restaurant = $this->currentRestaurant($request);

        return response()->json([
            'restaurant' => $restaurant,
            'orders_count' => $restaurant->orders()->count(),
            'pending_orders' => $restaurant->orders()->where('status', 'pending')->count(),
            'today_revenue' => $restaurant->orders()->whereDate('created_at', today())->sum('total'),
        ]);
    }

    public function showProfile(Request $request)
    {
        return response()->json([
            'restaurant' => $this->currentRestaurant($request)->load('owner:id,name,email,phone'),
        ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function profile(Request $request, RestaurantProfileImageManager $imageManager)
    {
        $restaurant = $this->currentRestaurant($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'status' => ['sometimes', 'nullable', 'in:open,closed'],
            'delivery_fee' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'minimum_order_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['sometimes', 'boolean'],
            'profile_image' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_profile_image' => ['sometimes', 'boolean'],
        ]);

        $profileImage = $validated['profile_image'] ?? null;
        $removeProfileImage = (bool) ($validated['remove_profile_image'] ?? false);
        $previousImage = $restaurant->profile_image;
        unset($validated['profile_image'], $validated['remove_profile_image']);

        if ($profileImage instanceof UploadedFile) {
            $validated['profile_image'] = $imageManager->store($profileImage);
        } elseif ($removeProfileImage) {
            $validated['profile_image'] = null;
        }

        $restaurant->fill($validated);
        $restaurant->save();

        if ($profileImage instanceof UploadedFile || $removeProfileImage) {
            $imageManager->delete($previousImage);
        }

        return response()->json($restaurant->fresh('owner:id,name,email,phone'), 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function orders(Request $request)
    {
        $restaurant = $this->currentRestaurant($request);

        return response()->json(
            $restaurant->orders()->with('customer', 'items')->latest()->paginate(20)
        );
    }

    public function updateOrderStatus(Request $request, Order $order)
    {
        $restaurant = $this->currentRestaurant($request);

        if ((int) $order->restaurant_id !== (int) $restaurant->id) {
            return response()->json(['message' => 'Order does not belong to this restaurant.'], 403);
        }

        $allowedTransitions = [
            'pending' => ['confirmed', 'rejected'],
            'confirmed' => ['preparing', 'cancelled'],
            'preparing' => ['ready_for_pickup', 'cancelled'],
            'ready_for_pickup' => ['picked_up'],
        ];

        $nextStatus = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_merge(...array_values($allowedTransitions)))],
        ])['status'];

        $currentStatus = $order->status;

        if (! isset($allowedTransitions[$currentStatus]) || ! in_array($nextStatus, $allowedTransitions[$currentStatus], true)) {
            return response()->json(['message' => 'Invalid order status transition.'], 422);
        }

        $order->status = $nextStatus;
        $order->save();

        NotificationLog::record($order->customer_id, 'order_status_updated', 'restaurant', [
            'order_id' => $order->id,
            'status' => $order->status,
            'restaurant_id' => $restaurant->id,
        ]);

        return response()->json($order->fresh('customer', 'restaurant', 'items'));
    }

    public function categories(Request $request)
    {
        return response()->json(
            $this->currentRestaurant($request)->categories()->withCount('foods')->latest()->paginate(20)
        );
    }

    public function storeCategory(Request $request)
    {
        $restaurant = $this->currentRestaurant($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $slug = $this->uniqueSlug(Category::query(), $validated['name']);

        $category = $restaurant->categories()->create([
            ...$validated,
            'slug' => $slug,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json($category, 201);
    }

    public function updateCategory(Request $request, Category $category)
    {
        $restaurant = $this->currentRestaurant($request);

        if ((int) $category->restaurant_id !== (int) $restaurant->id) {
            return response()->json(['message' => 'Category does not belong to this restaurant.'], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['name']) && $validated['name'] !== $category->name) {
            $validated['slug'] = $this->uniqueSlug(Category::query()->whereKeyNot($category->id), $validated['name']);
        }

        $category->fill($validated)->save();

        return response()->json($category->fresh());
    }

    public function foods(Request $request)
    {
        return response()->json(
            $this->currentRestaurant($request)->foods()->with('category')->latest()->paginate(20)
        );
    }

    public function storeFood(Request $request)
    {
        $restaurant = $this->currentRestaurant($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('restaurant_id', $restaurant->id)],
            'price' => ['required', 'numeric', 'min:0'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'preparation_time_minutes' => ['sometimes', 'integer', 'min:1', 'max:600'],
            'is_available' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
        ]);

        $food = $restaurant->foods()->create([
            ...$validated,
            'slug' => $this->uniqueSlug(Food::query(), $validated['name']),
            'preparation_time_minutes' => $validated['preparation_time_minutes'] ?? 15,
            'is_available' => $validated['is_available'] ?? true,
            'is_featured' => $validated['is_featured'] ?? false,
        ]);

        return response()->json($food, 201);
    }

    public function updateFood(Request $request, Food $food)
    {
        $restaurant = $this->currentRestaurant($request);

        if ((int) $food->restaurant_id !== (int) $restaurant->id) {
            return response()->json(['message' => 'Menu item does not belong to this restaurant.'], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'category_id' => ['sometimes', 'nullable', Rule::exists('categories', 'id')->where('restaurant_id', $restaurant->id)],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'image_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'preparation_time_minutes' => ['sometimes', 'integer', 'min:1', 'max:600'],
            'is_available' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['name']) && $validated['name'] !== $food->name) {
            $validated['slug'] = $this->uniqueSlug(Food::query()->whereKeyNot($food->id), $validated['name']);
        }

        $food->fill($validated)->save();

        return response()->json($food->fresh('category'));
    }

    private function currentRestaurant(Request $request): Restaurant
    {
        return $request->user()->restaurants()->firstOrFail();
    }

    private function uniqueSlug(Builder $query, string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $suffix = 2;

        while ((clone $query)->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        return $slug;
    }
}
