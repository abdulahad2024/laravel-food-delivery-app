<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_users', function (Blueprint $table) {
            $table->id();

            // User Information
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('avatar')->nullable(); // প্রোফাইল পিকচার
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('status')->default('active');

            // Restaurant Information (একই টেবিলে)
            $table->string('restaurant_name');
            $table->string('slug')->unique();
            $table->text('restaurant_description')->nullable();
            $table->text('restaurant_address')->nullable();
            $table->string('restaurant_logo')->nullable(); // রেস্টুরেন্ট লোগো
            $table->string('cover_image')->nullable();    // কভার ইমেজ

            $table->string('restaurant_status')->default('draft');
            $table->string('approval_status')->default('pending');
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('minimum_order_value', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);

            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_users');
    }
};
