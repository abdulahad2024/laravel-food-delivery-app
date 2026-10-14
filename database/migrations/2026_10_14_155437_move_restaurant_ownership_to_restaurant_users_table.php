<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $ownerRoleId = DB::table('roles')->where('name', 'restaurant_owner')->value('id');

        $owners = DB::table('users')
            ->where(function ($query) use ($ownerRoleId): void {
                $query->whereIn('users.id', DB::table('restaurants')->select('owner_id'));

                if ($ownerRoleId !== null) {
                    $query->orWhereIn('users.id', DB::table('role_user')->where('role_id', $ownerRoleId)->select('user_id'));
                }
            })
            ->select('users.id', 'users.id as legacy_user_id', 'users.status as legacy_user_status', 'users.name', 'users.email', 'users.phone', 'users.email_verified_at', 'users.password', 'users.status', 'users.remember_token', 'users.created_at', 'users.updated_at')
            ->orderBy('users.id')
            ->get();

        foreach ($owners as $owner) {
            DB::table('restaurant_users')->insertOrIgnore((array) $owner);
        }

        if ($owners->isNotEmpty()) {
            DB::table('users')->whereIn('id', $owners->pluck('id'))->update(['status' => 'blocked']);
        }

        if ($ownerRoleId !== null) {
            DB::table('role_user')->where('role_id', $ownerRoleId)->delete();
        }

        Schema::table('restaurants', function (Blueprint $table): void {
            $table->dropForeign(['owner_id']);
        });

        Schema::table('restaurants', function (Blueprint $table): void {
            $table->foreign('owner_id')->references('id')->on('restaurant_users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table): void {
            $table->dropForeign(['owner_id']);
        });

        $restaurantUsers = DB::table('restaurant_users')->orderBy('id')->get();
        $roleId = DB::table('roles')->where('name', 'restaurant_owner')->value('id');
        if ($restaurantUsers->isNotEmpty() && $roleId === null) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'restaurant_owner',
                'display_name' => 'Restaurant Owner',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($restaurantUsers as $restaurantUser) {
            $userId = $restaurantUser->legacy_user_id;

            if ($userId !== null && DB::table('users')->where('id', $userId)->exists()) {
                DB::table('users')->where('id', $userId)->update([
                    'name' => $restaurantUser->name,
                    'email' => $restaurantUser->email,
                    'phone' => $restaurantUser->phone,
                    'email_verified_at' => $restaurantUser->email_verified_at,
                    'password' => $restaurantUser->password,
                    'status' => $restaurantUser->legacy_user_status ?? $restaurantUser->status,
                    'remember_token' => $restaurantUser->remember_token,
                    'updated_at' => $restaurantUser->updated_at,
                ]);
            } else {
                $userId = DB::table('users')->insertGetId([
                    'name' => $restaurantUser->name,
                    'email' => $restaurantUser->email,
                    'phone' => $restaurantUser->phone,
                    'email_verified_at' => $restaurantUser->email_verified_at,
                    'password' => $restaurantUser->password,
                    'status' => $restaurantUser->status,
                    'remember_token' => $restaurantUser->remember_token,
                    'created_at' => $restaurantUser->created_at,
                    'updated_at' => $restaurantUser->updated_at,
                ]);
            }

            DB::table('restaurants')->where('owner_id', $restaurantUser->id)->update(['owner_id' => $userId]);
            if ($roleId !== null) {
                DB::table('role_user')->insertOrIgnore([
                    'role_id' => $roleId,
                    'user_id' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('restaurants', function (Blueprint $table): void {
            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::dropIfExists('restaurant_users');
    }
};
