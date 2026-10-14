<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantUser;
use App\Models\Role;
use App\Models\User;
use App\Support\RestaurantProfileImageManager;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function dashboard(Request $request)
    {
        if (! $request->user()?->hasRole('admin') && ! $request->user()?->hasRole('super_admin')) {
            return response()->json(['message' => 'You are not allowed to access admin data.'], 403);
        }

        return response()->json([
            'customers' => User::whereHas('roles', fn ($query) => $query->where('name', 'customer'))->count(),
            'restaurants' => Restaurant::count(),
            'pending_restaurants' => Restaurant::where('approval_status', 'pending')->count(),
            'orders' => Order::count(),
            'gross_order_value' => Order::sum('total') ?? 0,
            'revenue' => Order::where('payment_status', 'paid')->sum('total') ?? 0,
            'commission' => Order::where('payment_status', 'paid')->sum('total') * 0.10,
        ]);
    }

    public function restaurants(Request $request)
    {
        if (! $request->user()?->hasRole('admin') && ! $request->user()?->hasRole('super_admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $query = Restaurant::with('owner:id,name,email,phone');

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->approval_status);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json(
            $query->latest()->paginate(20)
        );
    }

    public function storeRestaurant(Request $request, RestaurantProfileImageManager $imageManager)
    {
        if (! $request->user()?->hasRole('admin') && ! $request->user()?->hasRole('super_admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'owner_id' => ['required', 'exists:restaurant_users,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:restaurants,slug'],
            'description' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['nullable', 'in:open,closed,draft'],
            'approval_status' => ['nullable', 'in:approved,rejected,pending'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'minimum_order_value' => ['nullable', 'numeric', 'min:0'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['nullable', 'boolean'],
            'profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $profileImage = $validated['profile_image'] ?? null;
        unset($validated['profile_image']);
        $validated['slug'] = $validated['slug'] ?? str($validated['name'])->slug()->value();
        $validated['approval_status'] = $validated['approval_status'] ?? 'pending';
        $validated['status'] = $validated['status'] ?? 'draft';

        $restaurant = Restaurant::create($validated);

        if ($profileImage instanceof UploadedFile) {
            $restaurant->profile_image = $imageManager->store($profileImage);
            $restaurant->save();
        }

        return response()->json($restaurant->fresh('owner:id,name,email,phone'), 201);
    }

    public function updateRestaurant(Request $request, Restaurant $restaurant, RestaurantProfileImageManager $imageManager)
    {
        if (! $request->user()?->hasRole('admin') && ! $request->user()?->hasRole('super_admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'owner_id' => ['sometimes', 'exists:restaurant_users,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'unique:restaurants,slug,'.$restaurant->id],
            'description' => ['sometimes', 'nullable', 'string'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'status' => ['sometimes', 'nullable', 'in:open,closed,draft'],
            'approval_status' => ['sometimes', 'nullable', 'in:approved,rejected,pending'],
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

        if (isset($validated['slug'])) {
            $validated['slug'] = str($validated['slug'])->slug()->value();
        }

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

        return response()->json($restaurant->fresh('owner:id,name,email,phone'));
    }

    public function destroyRestaurant(Request $request, Restaurant $restaurant, RestaurantProfileImageManager $imageManager)
    {
        if (! $request->user()?->hasRole('admin') && ! $request->user()?->hasRole('super_admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $profileImage = $restaurant->profile_image;
        $restaurant->delete();
        $imageManager->delete($profileImage);

        return response()->json(['message' => 'Restaurant deleted successfully.']);
    }

    public function approveRestaurant(Request $request, Restaurant $restaurant)
    {
        $user = $request->user();

        if (! $user || (! $user->hasRole('admin') && ! $user->hasRole('super_admin'))) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'approval_status' => ['required', 'in:approved,rejected,pending'],
            'status' => ['nullable', 'in:open,closed'],
        ]);

        $restaurant->approval_status = $validated['approval_status'];
        $restaurant->status = $validated['status'] ?? $restaurant->status;
        $restaurant->save();

        NotificationLog::record(null, 'restaurant_approval_updated', 'admin', [
            'restaurant_id' => $restaurant->id,
            'restaurant_user_id' => $restaurant->owner_id,
            'approval_status' => $restaurant->approval_status,
            'status' => $restaurant->status,
        ]);

        return response()->json($restaurant->fresh('owner'));
    }

    public function users(Request $request)
    {
        if (! $request->user()?->hasRole('admin') && ! $request->user()?->hasRole('super_admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $query = User::with('roles');

        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($relation) => $relation->where('name', $request->role));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function restaurantUsers(Request $request)
    {
        if (! $request->user()?->hasRole('admin') && ! $request->user()?->hasRole('super_admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $query = RestaurantUser::withCount('restaurants')->latest();

        if ($request->filled('q')) {
            $term = $request->string('q')->toString();
            $query->where(fn ($users) => $users
                ->where('name', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%')
                ->orWhere('phone', 'like', '%'.$term.'%'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return response()->json($query->paginate(20));
    }

    public function storeRestaurantUser(Request $request)
    {
        if (! $request->user()?->hasRole('admin') && ! $request->user()?->hasRole('super_admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('restaurant_users', 'email'), Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'blocked'])],
        ]);

        $restaurantUser = RestaurantUser::create([
            ...$validated,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json($restaurantUser->loadCount('restaurants'), 201);
    }

    public function updateRestaurantUser(Request $request, RestaurantUser $restaurantUser)
    {
        if (! $request->user()?->hasRole('admin') && ! $request->user()?->hasRole('super_admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $userEmailRule = Rule::unique('users', 'email');
        if ($restaurantUser->legacy_user_id !== null) {
            $userEmailRule->ignore($restaurantUser->legacy_user_id);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('restaurant_users', 'email')->ignore($restaurantUser->id),
                $userEmailRule,
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive', 'blocked'])],
        ]);

        if (($validated['password'] ?? null) === null) {
            unset($validated['password']);
        }

        $restaurantUser->fill($validated)->save();

        return response()->json($restaurantUser->fresh()->loadCount('restaurants'));
    }

    public function storeUser(Request $request)
    {
        if (! $request->user()?->hasRole('admin') && ! $request->user()?->hasRole('super_admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email'), Rule::unique('restaurant_users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'status' => ['nullable', 'in:active,inactive,blocked'],
            'role' => ['required', 'string', 'in:customer,rider,admin,super_admin'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
            'status' => $validated['status'] ?? 'active',
        ]);

        $user->assignRole($validated['role']);

        return response()->json($user->fresh('roles'), 201);
    }

    public function updateUser(Request $request, User $user)
    {
        if (! $request->user()?->hasRole('admin') && ! $request->user()?->hasRole('super_admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
                Rule::unique('restaurant_users', 'email'),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'status' => ['sometimes', 'nullable', 'in:active,inactive,blocked'],
            'role' => ['sometimes', 'required', 'string', 'in:customer,rider,admin,super_admin'],
        ]);

        $user->fill($validated);

        if (isset($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        if ($request->filled('role')) {
            $role = Role::firstOrCreate(
                ['name' => $validated['role']],
                ['display_name' => ucfirst(str_replace('_', ' ', $validated['role']))]
            );

            $user->roles()->sync([$role->id]);
        }

        return response()->json($user->fresh('roles'));
    }

    public function destroyUser(Request $request, User $user)
    {
        if (! $request->user()?->hasRole('admin') && ! $request->user()?->hasRole('super_admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ((int) $request->user()->id === (int) $user->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }

    public function updateUserRole(Request $request, User $user)
    {
        $currentUser = $request->user();

        if (! $currentUser || (! $currentUser->hasRole('admin') && ! $currentUser->hasRole('super_admin'))) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:customer,rider,admin,super_admin'],
        ]);

        $role = Role::firstOrCreate(
            ['name' => $validated['role']],
            ['display_name' => ucfirst(str_replace('_', ' ', $validated['role']))]
        );

        $user->roles()->sync([$role->id]);

        return response()->json($user->fresh('roles'));
    }
}
