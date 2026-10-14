<?php

use App\Http\Controllers\AdminWebController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    if (! Auth::attempt($credentials, $request->boolean('remember'))) {
        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    if (! $request->user()?->can('access-admin-dashboard')) {
        Auth::logout();

        return redirect()->route('login')->withErrors([
            'email' => 'This account cannot access the administrator panel.',
        ]);
    }

    $request->session()->regenerate();

    return redirect()->intended('/admin');
})->middleware('throttle:5,1')->name('login.submit');

Route::post('/logout', function (Request $request) {
    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login');
})->name('logout');

Route::middleware(['auth', 'can:access-admin-dashboard'])->group(function () {
    Route::get('/admin', [AdminWebController::class, 'dashboard'])->name('admin.dashboard');

    Route::get('/admin/users', [AdminWebController::class, 'users'])->name('admin.users');
    Route::post('/admin/users', [AdminWebController::class, 'storeUser'])->name('admin.users.store');
    Route::put('/admin/users/{user}', [AdminWebController::class, 'updateUser'])->name('admin.users.update');

    Route::get('/admin/restaurant-users', [AdminWebController::class, 'restaurantUsers'])->name('admin.restaurant-users');
    Route::post('/admin/restaurant-users', [AdminWebController::class, 'storeRestaurantUser'])->name('admin.restaurant-users.store');
    Route::put('/admin/restaurant-users/{restaurantUser}', [AdminWebController::class, 'updateRestaurantUser'])->name('admin.restaurant-users.update');

    Route::get('/admin/restaurants', [AdminWebController::class, 'restaurants'])->name('admin.restaurants');
    Route::post('/admin/restaurants', [AdminWebController::class, 'storeRestaurant'])->name('admin.restaurants.store');
    Route::put('/admin/restaurants/{restaurant}', [AdminWebController::class, 'updateRestaurant'])->name('admin.restaurants.update');

    Route::get('/admin/menu', [AdminWebController::class, 'menu'])->name('admin.menu');
    Route::post('/admin/menu', [AdminWebController::class, 'storeFood'])->name('admin.menu.store');
    Route::put('/admin/menu/{food}', [AdminWebController::class, 'updateFood'])->name('admin.menu.update');

    Route::get('/admin/orders', [AdminWebController::class, 'orders'])->name('admin.orders');
    Route::post('/admin/orders/{order}/status', [AdminWebController::class, 'updateOrderStatus'])->name('admin.orders.status');

    Route::get('/admin/payments', [AdminWebController::class, 'payments'])->name('admin.payments');
    Route::get('/admin/refunds', [AdminWebController::class, 'refunds'])->name('admin.refunds');
    Route::post('/admin/refunds/{refund}/status', [AdminWebController::class, 'updateRefundStatus'])->name('admin.refunds.status');

    Route::get('/admin/coupons', [AdminWebController::class, 'coupons'])->name('admin.coupons');
    Route::post('/admin/coupons', [AdminWebController::class, 'storeCoupon'])->name('admin.coupons.store');
    Route::put('/admin/coupons/{coupon}', [AdminWebController::class, 'updateCoupon'])->name('admin.coupons.update');
    Route::delete('/admin/coupons/{coupon}', [AdminWebController::class, 'deleteCoupon'])->name('admin.coupons.destroy');

    Route::get('/admin/settlements', [AdminWebController::class, 'settlements'])->name('admin.settlements');
    Route::post('/admin/settlements/{settlement}/status', [AdminWebController::class, 'updateSettlementStatus'])->name('admin.settlements.status');
    Route::get('/admin/settlements/export', [AdminWebController::class, 'exportSettlements'])->name('admin.settlements.export');

    Route::get('/admin/payout-ledger', [AdminWebController::class, 'payoutLedger'])->name('admin.payout-ledger');
    Route::get('/admin/payout-ledger/export', [AdminWebController::class, 'exportPayoutLedger'])->name('admin.payout-ledger.export');
});
