<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RestaurantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RestaurantAuthController extends Controller
{
    /**
     * Step 1: Basic Info (Name, Email, Phone, Password, Avatar)
     */
    public function registerStepOne(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('restaurant_users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ]);

        $avatarPath = $request->hasFile('avatar') ? $request->file('avatar')->store('avatars', 'public') : null;

        $restaurantUser = RestaurantUser::create([
            'name' => $validated['name'],
            'email' => Str::lower($validated['email']),
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'], // Model cast handles hashing
            'avatar' => $avatarPath,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Step 1 completed successfully.',
            'restaurant_user' => $restaurantUser,
            'token' => $restaurantUser->createToken('restaurant-api')->plainTextToken,
        ], 201);
    }

    /**
     * Step 2: Images (Restaurant Logo & Cover Image)
     */
    public function registerStepTwo(Request $request): JsonResponse
    {
        /** @var RestaurantUser $user */
        $user = $request->user();

        $validated = $request->validate([
            'restaurant_logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ]);

        if ($request->hasFile('restaurant_logo')) {
            if ($user->restaurant_logo) Storage::disk('public')->delete($user->restaurant_logo);
            $user->restaurant_logo = $request->file('restaurant_logo')->store('restaurant_logos', 'public');
        }

        if ($request->hasFile('cover_image')) {
            if ($user->cover_image) Storage::disk('public')->delete($user->cover_image);
            $user->cover_image = $request->file('cover_image')->store('restaurant_covers', 'public');
        }

        $user->save();

        return response()->json([
            'message' => 'Step 2 completed successfully.',
            'restaurant_user' => $user->fresh(),
        ]);
    }

    /**
     * Step 3: Restaurant Details (Name, Description, Address) - Completes Registration
     */
    public function registerStepThree(Request $request): JsonResponse
    {
        /** @var RestaurantUser $user */
        $user = $request->user();

        $validated = $request->validate([
            'restaurant_name' => ['required', 'string', 'max:255'],
            'restaurant_description' => ['nullable', 'string'],
            'restaurant_address' => ['required', 'string'],
        ]);

        // Generate Unique Slug
        $baseSlug = Str::slug($validated['restaurant_name']);
        $slug = $baseSlug;
        $suffix = 2;
        while (RestaurantUser::where('slug', $slug)->where('id', '!=', $user->id)->exists()) {
            $slug = $baseSlug . '-' . $suffix++;
        }

        $user->update([
            'restaurant_name' => $validated['restaurant_name'],
            'restaurant_description' => $validated['restaurant_description'] ?? null,
            'restaurant_address' => $validated['restaurant_address'],
            'slug' => $slug,
            'restaurant_status' => 'active',
            'approval_status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Restaurant registration completed successfully.',
            'restaurant_user' => $user->fresh(),
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $restaurantUser = RestaurantUser::where('email', Str::lower($validated['email']))->first();

        if (! $restaurantUser || $restaurantUser->status !== 'active' || ! Hash::check($validated['password'], $restaurantUser->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return response()->json([
            'message' => 'Login successful.',
            'restaurant_user' => $restaurantUser,
            'token' => $restaurantUser->createToken('restaurant-api')->plainTextToken,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'restaurant_user' => $request->user(),
        ]);
    }

    /**
     * Update Profile (All information update)
     */
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var RestaurantUser $restaurantUser */
        $restaurantUser = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('restaurant_users', 'email')->ignore($restaurantUser->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],

            'restaurant_name' => ['sometimes', 'required', 'string', 'max:255'],
            'restaurant_description' => ['sometimes', 'nullable', 'string'],
            'restaurant_address' => ['sometimes', 'required', 'string'],
            'restaurant_logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],

            'current_password' => ['required_with:password', 'string'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (isset($validated['password']) && ! Hash::check($validated['current_password'], $restaurantUser->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        // Handle Avatar Update
        if ($request->hasFile('avatar')) {
            if ($restaurantUser->avatar) Storage::disk('public')->delete($restaurantUser->avatar);
            $restaurantUser->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        // Handle Logo Update
        if ($request->hasFile('restaurant_logo')) {
            if ($restaurantUser->restaurant_logo) Storage::disk('public')->delete($restaurantUser->restaurant_logo);
            $restaurantUser->restaurant_logo = $request->file('restaurant_logo')->store('restaurant_logos', 'public');
        }

        // Handle Cover Image Update
        if ($request->hasFile('cover_image')) {
            if ($restaurantUser->cover_image) Storage::disk('public')->delete($restaurantUser->cover_image);
            $restaurantUser->cover_image = $request->file('cover_image')->store('restaurant_covers', 'public');
        }

        // Update fields if provided
        if (isset($validated['name'])) $restaurantUser->name = $validated['name'];
        if (isset($validated['email'])) $restaurantUser->email = Str::lower($validated['email']);
        if (array_key_exists('phone', $validated)) $restaurantUser->phone = $validated['phone'];
        if (isset($validated['password'])) $restaurantUser->password = $validated['password'];

        if (isset($validated['restaurant_name'])) {
            $restaurantUser->restaurant_name = $validated['restaurant_name'];
            $restaurantUser->slug = Str::slug($validated['restaurant_name']);
        }
        if (array_key_exists('restaurant_description', $validated)) $restaurantUser->restaurant_description = $validated['restaurant_description'];
        if (isset($validated['restaurant_address'])) $restaurantUser->restaurant_address = $validated['restaurant_address'];

        $restaurantUser->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'restaurant_user' => $restaurantUser->fresh(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }



    /**
     * Change Password
     */
    public function changePassword(Request $request): JsonResponse
    {
        /** @var RestaurantUser $user */
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'], // 'password_confirmation' field required in request
        ]);

        // Check if current password is correct
        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided current password is incorrect.'],
            ]);
        }

        // Update password (model cast will automatically hash it)
        $user->password = $validated['password'];
        $user->save();

        // Optional: Revoke all existing tokens if you want to force re-login everywhere
        // $user->tokens()->delete();

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    /**
     * Delete Account
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        /** @var RestaurantUser $user */
        $user = $request->user();

        $request->validate([
            'password' => ['required', 'string'], // Security confirmation
        ]);

        // Verify password before deleting account
        if (! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        // Delete associated files (Avatar, Logo, Cover) if needed
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }
        if ($user->restaurant_logo) {
            Storage::disk('public')->delete($user->restaurant_logo);
        }
        if ($user->cover_image) {
            Storage::disk('public')->delete($user->cover_image);
        }

        // Delete all Sanctum tokens
        $user->tokens()->delete();

        // Delete the user record
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully.',
        ]);
    }



}
