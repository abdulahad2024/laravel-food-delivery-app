<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\RefundController;
use App\Http\Controllers\Api\V1\RestaurantAuthController;
use App\Http\Controllers\Api\V1\RiderController;
use App\Http\Controllers\Api\V1\SettlementController;
use App\Http\Controllers\ApiV1RestaurantController;
use App\Http\Controllers\AuthController;
use App\Http\Middleware\AuthenticateSanctumBearerToken;
use App\Http\Middleware\EnsurePlatformUser;
use App\Http\Middleware\EnsureRestaurantUser;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Restaurant Authentication & Multi-Step Routes
|--------------------------------------------------------------------------
*/
Route::prefix('v1/restaurant')->group(function () {
    // Public Auth Routes
    Route::post('/register/step-1', [RestaurantAuthController::class, 'registerStepOne']);
    Route::post('/login', [RestaurantAuthController::class, 'login']);

    // Protected Multi-Step & Profile Routes (Require Sanctum & Restaurant User Verification)
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/register/step-2', [RestaurantAuthController::class, 'registerStepTwo']);
        Route::post('/register/step-3', [RestaurantAuthController::class, 'registerStepThree']);

        Route::get('/me', [RestaurantAuthController::class, 'me']);
        Route::post('/profile/update', [RestaurantAuthController::class, 'updateProfile']); // Use POST for multipart file updates
        Route::post('/logout', [RestaurantAuthController::class, 'logout']);

        Route::post('/change-password', [RestaurantAuthController::class, 'changePassword']);
        Route::delete('/delete-account', [RestaurantAuthController::class, 'deleteAccount']);
    });
});

/*
|--------------------------------------------------------------------------
| General Platform & Admin Routes
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->group(function (): void {

    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware(AuthenticateSanctumBearerToken::class)->group(function (): void {

        Route::middleware(EnsurePlatformUser::class)->group(function (): void {
            Route::post('/auth/logout', [AuthController::class, 'logout']);
            Route::get('/auth/me', [AuthController::class, 'me']);

            Route::get('/coupons', [CouponController::class, 'index']);
            Route::post('/coupons/validate', [CouponController::class, 'validate']);
            Route::get('/notifications', [NotificationController::class, 'index']);

            Route::get('/orders', [OrderController::class, 'index']);
            Route::post('/orders/preview', [OrderController::class, 'preview']);
            Route::post('/orders', [OrderController::class, 'store']);
            Route::get('/orders/{order}', [OrderController::class, 'show']);
            Route::post('/orders/{order}/payments/confirm', [PaymentController::class, 'confirm']);
            Route::post('/orders/{order}/refunds', [RefundController::class, 'store']);

            Route::post('/rider/availability', [RiderController::class, 'availability']);
            Route::get('/rider/deliveries', [RiderController::class, 'deliveries']);
            Route::post('/rider/deliveries/{order}/accept', [RiderController::class, 'acceptDelivery']);
            Route::patch('/rider/deliveries/{order}/status', [RiderController::class, 'updateDeliveryStatus']);

            Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
            Route::get('/admin/restaurants', [AdminController::class, 'restaurants']);
            Route::post('/admin/restaurants', [AdminController::class, 'storeRestaurant']);
            Route::patch('/admin/restaurants/{restaurant}', [AdminController::class, 'updateRestaurant']);
            Route::delete('/admin/restaurants/{restaurant}', [AdminController::class, 'destroyRestaurant']);
            Route::get('/admin/users', [AdminController::class, 'users']);
            Route::post('/admin/users', [AdminController::class, 'storeUser']);
            Route::patch('/admin/users/{user}', [AdminController::class, 'updateUser']);
            Route::delete('/admin/users/{user}', [AdminController::class, 'destroyUser']);
            Route::patch('/admin/users/{user}/role', [AdminController::class, 'updateUserRole']);
            Route::get('/admin/restaurant-users', [AdminController::class, 'restaurantUsers']);
            Route::post('/admin/restaurant-users', [AdminController::class, 'storeRestaurantUser']);
            Route::patch('/admin/restaurant-users/{restaurantUser}', [AdminController::class, 'updateRestaurantUser']);
            Route::get('/admin/coupons', [CouponController::class, 'adminIndex']);
            Route::post('/admin/coupons', [CouponController::class, 'store']);
            Route::patch('/admin/coupons/{coupon}', [CouponController::class, 'update']);
            Route::delete('/admin/coupons/{coupon}', [CouponController::class, 'destroy']);
            Route::get('/admin/notifications', [NotificationController::class, 'adminIndex']);
            Route::get('/admin/refunds', [RefundController::class, 'index']);
            Route::patch('/admin/refunds/{refund}', [RefundController::class, 'updateStatus']);
            Route::patch('/admin/restaurants/{restaurant}/approval', [AdminController::class, 'approveRestaurant']);
            Route::get('/admin/settlements', [SettlementController::class, 'index']);
            Route::get('/admin/settlements/export', [SettlementController::class, 'export']);
            Route::get('/admin/settlements/summary', [SettlementController::class, 'summary']);
            Route::post('/admin/settlements/{order}/generate', [SettlementController::class, 'generate']);
            Route::get('/admin/payout-ledger', [SettlementController::class, 'ledger']);
            Route::get('/admin/payout-ledger/export', [SettlementController::class, 'ledgerExport']);
            Route::get('/admin/payout-ledger/summary', [SettlementController::class, 'ledgerSummary']);
            Route::patch('/admin/settlements/{settlement}', [SettlementController::class, 'updateStatus']);
        });
    });

    Route::get('/restaurants', [ApiV1RestaurantController::class, 'index']);
    Route::get('/restaurants/{restaurant}', [ApiV1RestaurantController::class, 'show']);
    Route::get('/restaurants/{restaurant}/menu', [ApiV1RestaurantController::class, 'menu']);
    Route::get('/foods/{food}', [ApiV1RestaurantController::class, 'foodDetail']);
});
