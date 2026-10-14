<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Commission;
use App\Models\Coupon;
use App\Models\Food;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PayoutLedger;
use App\Models\Refund;
use App\Models\Restaurant;
use App\Models\RestaurantUser;
use App\Models\Role;
use App\Models\Settlement;
use App\Models\User;
use App\Support\RestaurantProfileImageManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminWebController extends Controller
{
    private const ROLES = ['customer', 'rider', 'admin', 'super_admin'];

    private const ORDER_TRANSITIONS = [
        'pending' => ['confirmed', 'rejected'],
        'confirmed' => ['preparing', 'cancelled'],
        'preparing' => ['ready_for_pickup', 'cancelled'],
        'ready_for_pickup' => ['picked_up'],
        'picked_up' => ['delivered'],
    ];

    private const SETTLEMENT_TRANSITIONS = [
        'pending' => ['approved'],
        'approved' => ['paid'],
    ];

    public function dashboard(): View
    {
        $start = now()->subDays(6)->startOfDay();
        $end = now()->endOfDay();
        $dailySales = Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as day, SUM(total) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $salesChart = collect(range(6, 0))->map(function (int $daysAgo) use ($dailySales): array {
            $date = now()->subDays($daysAgo);
            $amount = (float) ($dailySales->get($date->toDateString()) ?? 0);

            return [
                'label' => $date->format('D'),
                'amount' => $amount,
                'height' => $dailySales->isEmpty() ? 0 : min(100, ($amount / max(1, (float) $dailySales->max())) * 100),
            ];
        });

        return view('admin.dashboard', [
            'customers' => User::whereHas('roles', fn ($query) => $query->where('name', 'customer'))->count(),
            'restaurants' => Restaurant::count(),
            'pendingRestaurants' => Restaurant::where('approval_status', 'pending')->count(),
            'orders' => Order::count(),
            'grossSales' => Order::sum('total'),
            'revenue' => Order::where('payment_status', 'paid')->sum('total'),
            'commission' => Commission::sum('amount'),
            'pendingRefunds' => Refund::whereIn('status', ['pending', 'requested'])->count(),
            'pendingSettlements' => Settlement::where('status', 'pending')->count(),
            'salesChart' => $salesChart,
            'recentOrders' => Order::with(['customer:id,name', 'restaurant:id,name'])->latest()->take(8)->get(),
        ]);
    }

    public function users(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(self::ROLES)],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'blocked'])],
        ]);
        $query = User::with('roles')->latest();

        if (! empty($filters['q'])) {
            $query->where(fn ($users) => $users
                ->where('name', 'like', '%'.$filters['q'].'%')
                ->orWhere('email', 'like', '%'.$filters['q'].'%')
                ->orWhere('phone', 'like', '%'.$filters['q'].'%'));
        }

        if (! empty($filters['role'])) {
            $query->whereHas('roles', fn ($roles) => $roles->where('name', $filters['role']));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return view('admin.users', [
            'users' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
            'roles' => auth()->user()->hasRole('super_admin')
                ? self::ROLES
                : array_diff(self::ROLES, ['admin', 'super_admin']),
        ]);
    }

    public function restaurantUsers(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'blocked'])],
        ]);
        $query = RestaurantUser::withCount('restaurants')->latest();

        if (! empty($filters['q'])) {
            $query->where(fn ($users) => $users
                ->where('name', 'like', '%'.$filters['q'].'%')
                ->orWhere('email', 'like', '%'.$filters['q'].'%')
                ->orWhere('phone', 'like', '%'.$filters['q'].'%'));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return view('admin.restaurant-users', [
            'restaurantUsers' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function storeRestaurantUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('restaurant_users', 'email'), Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'status' => ['required', Rule::in(['active', 'inactive', 'blocked'])],
        ]);

        RestaurantUser::create($validated);

        return back()->with('success', 'Restaurant account created successfully.');
    }

    public function updateRestaurantUser(RestaurantUser $restaurantUser, Request $request): RedirectResponse
    {
        $userEmailRule = Rule::unique('users', 'email');
        if ($restaurantUser->legacy_user_id !== null) {
            $userEmailRule->ignore($restaurantUser->legacy_user_id);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('restaurant_users', 'email')->ignore($restaurantUser->id),
                $userEmailRule,
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8'],
            'status' => ['required', Rule::in(['active', 'inactive', 'blocked'])],
        ]);

        if (($validated['password'] ?? null) === null) {
            unset($validated['password']);
        }

        $restaurantUser->fill($validated)->save();

        return back()->with('success', 'Restaurant account updated successfully.');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email'), Rule::unique('restaurant_users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'status' => ['required', Rule::in(['active', 'inactive', 'blocked'])],
            'role' => ['required', Rule::in($this->assignableRoles())],
        ]);

        DB::transaction(function () use ($validated): void {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => $validated['password'],
                'status' => $validated['status'],
            ]);

            $user->assignRole($validated['role']);
        });

        return back()->with('success', 'User created successfully.');
    }

    public function updateUser(User $user, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
                Rule::unique('restaurant_users', 'email'),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['active', 'inactive', 'blocked'])],
            'role' => ['required', Rule::in($this->assignableRoles())],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        if ($user->is(auth()->user()) && ($validated['status'] !== 'active' || ! $user->hasRole($validated['role']))) {
            return back()->withErrors(['user' => 'You cannot deactivate or demote your own account.'])->withInput();
        }

        if (($user->hasRole('admin') || $user->hasRole('super_admin')) && ! auth()->user()->hasRole('super_admin')) {
            return back()->withErrors(['user' => 'Only a super admin can update administrator accounts.']);
        }

        DB::transaction(function () use ($validated, $user): void {
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->phone = $validated['phone'] ?? null;
            $user->status = $validated['status'];

            if (! empty($validated['password'])) {
                $user->password = $validated['password'];
            }

            $user->save();
            $role = Role::firstOrCreate(
                ['name' => $validated['role']],
                ['display_name' => ucfirst(str_replace('_', ' ', $validated['role']))]
            );
            $user->roles()->sync([$role->id]);
        });

        return back()->with('success', 'User updated successfully.');
    }

    public function restaurants(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['open', 'closed', 'draft'])],
            'approval_status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
        ]);
        $query = Restaurant::with('owner:id,name,email')->withCount(['foods', 'orders'])->latest();

        if (! empty($filters['q'])) {
            $query->where(fn ($restaurants) => $restaurants
                ->where('name', 'like', '%'.$filters['q'].'%')
                ->orWhere('email', 'like', '%'.$filters['q'].'%')
                ->orWhereHas('owner', fn ($owner) => $owner->where('name', 'like', '%'.$filters['q'].'%')));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['approval_status'])) {
            $query->where('approval_status', $filters['approval_status']);
        }

        return view('admin.restaurants', [
            'restaurants' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
            'owners' => RestaurantUser::where('status', 'active')
                ->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function storeRestaurant(Request $request, RestaurantProfileImageManager $imageManager): RedirectResponse
    {
        $validated = $request->validate([
            'owner_id' => ['required', 'exists:restaurant_users,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status' => ['required', Rule::in(['open', 'closed', 'draft'])],
            'approval_status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'delivery_fee' => ['required', 'numeric', 'min:0'],
            'minimum_order_value' => ['required', 'numeric', 'min:0'],
        ]);
        $profileImage = $validated['profile_image'] ?? null;
        unset($validated['profile_image']);
        $restaurant = DB::transaction(function () use ($validated): Restaurant {
            $baseSlug = str($validated['name'])->slug()->value();
            $slug = $baseSlug;
            $suffix = 2;

            while (Restaurant::where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }

            return Restaurant::create($validated + ['slug' => $slug, 'is_active' => true]);
        });

        if ($profileImage instanceof UploadedFile) {
            $restaurant->profile_image = $imageManager->store($profileImage);
            $restaurant->save();
        }

        return back()->with('success', 'Restaurant created successfully.');
    }

    public function updateRestaurant(Restaurant $restaurant, Request $request, RestaurantProfileImageManager $imageManager): RedirectResponse
    {
        $validated = $request->validate([
            'owner_id' => ['sometimes', 'required', 'exists:restaurant_users,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'profile_image' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_profile_image' => ['sometimes', 'boolean'],
            'status' => ['required', Rule::in(['open', 'closed', 'draft'])],
            'approval_status' => ['required', Rule::in(['pending', 'approved', 'rejected'])],
            'delivery_fee' => ['required', 'numeric', 'min:0'],
            'minimum_order_value' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
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

        $restaurant->fill($validated)->save();

        if ($profileImage instanceof UploadedFile || $removeProfileImage) {
            $imageManager->delete($previousImage);
        }

        return back()->with('success', 'Restaurant updated successfully.');
    }

    public function menu(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'restaurant_id' => ['nullable', 'integer', 'exists:restaurants,id'],
            'availability' => ['nullable', Rule::in(['available', 'unavailable'])],
        ]);
        $query = Food::with(['restaurant:id,name', 'category:id,name'])->latest();

        if (! empty($filters['q'])) {
            $query->where('name', 'like', '%'.$filters['q'].'%');
        }

        if (! empty($filters['restaurant_id'])) {
            $query->where('restaurant_id', $filters['restaurant_id']);
        }

        if (isset($filters['availability'])) {
            $query->where('is_available', $filters['availability'] === 'available');
        }

        return view('admin.menu', [
            'foods' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
            'restaurants' => Restaurant::orderBy('name')->get(['id', 'name']),
            'categories' => Category::with('restaurant:id,name')->orderBy('name')->get(['id', 'restaurant_id', 'name']),
        ]);
    }

    public function storeFood(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'restaurant_id' => ['required', 'exists:restaurants,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'preparation_time_minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'image_url' => ['nullable', 'url', 'max:255'],
        ]);

        if (! empty($validated['category_id']) && ! Category::whereKey($validated['category_id'])
            ->where('restaurant_id', $validated['restaurant_id'])->exists()) {
            return back()->withErrors(['category_id' => 'Choose a category belonging to the selected restaurant.'])->withInput();
        }

        $baseSlug = str($validated['name'])->slug()->value();
        $slug = $baseSlug;
        $suffix = 2;

        while (Food::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix++;
        }

        Food::create($validated + [
            'slug' => $slug,
            'is_available' => true,
            'is_featured' => false,
        ]);

        return back()->with('success', 'Menu item created successfully.');
    }

    public function updateFood(Food $food, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'price' => ['required', 'numeric', 'min:0'],
            'is_available' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
        ]);

        $food->fill($validated)->save();

        return back()->with('success', 'Menu item updated successfully.');
    }

    public function orders(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'payment_status' => ['nullable', 'string', 'max:50'],
            'restaurant_id' => ['nullable', 'integer', 'exists:restaurants,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', Rule::when($request->filled('from'), ['after_or_equal:from'])],
        ]);
        $query = Order::with(['customer:id,name,email', 'restaurant:id,name', 'rider:id,name'])->latest();

        if (! empty($filters['q'])) {
            $query->where(fn ($orders) => $orders
                ->where('order_number', 'like', '%'.$filters['q'].'%')
                ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', '%'.$filters['q'].'%'))
                ->orWhereHas('restaurant', fn ($restaurant) => $restaurant->where('name', 'like', '%'.$filters['q'].'%')));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (! empty($filters['restaurant_id'])) {
            $query->where('restaurant_id', $filters['restaurant_id']);
        }

        $this->applyDateFilters($query, $filters);

        return view('admin.orders', [
            'orders' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
            'restaurants' => Restaurant::orderBy('name')->get(['id', 'name']),
            'transitions' => self::ORDER_TRANSITIONS,
        ]);
    }

    public function updateOrderStatus(Order $order, Request $request): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', 'string', 'max:50']]);
        $nextStatus = $validated['status'];

        if (! in_array($nextStatus, self::ORDER_TRANSITIONS[$order->status] ?? [], true)) {
            return back()->withErrors(['status' => 'That order status transition is not allowed.']);
        }

        DB::transaction(function () use ($order, $nextStatus): void {
            $order->status = $nextStatus;
            if (in_array($nextStatus, ['picked_up', 'delivered'], true)) {
                $order->delivery_status = $nextStatus;
            }
            $order->save();
            NotificationLog::record($order->customer_id, 'order_status_updated', 'admin', [
                'order_id' => $order->id,
                'status' => $nextStatus,
            ]);
        });

        return back()->with('success', 'Order status updated.');
    }

    public function payments(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', Rule::when($request->filled('from'), ['after_or_equal:from'])],
        ]);
        $query = Payment::with(['order:id,order_number,restaurant_id', 'order.restaurant:id,name', 'user:id,name,email'])->latest();

        if (! empty($filters['q'])) {
            $query->where(fn ($payments) => $payments
                ->where('reference', 'like', '%'.$filters['q'].'%')
                ->orWhereHas('order', fn ($order) => $order->where('order_number', 'like', '%'.$filters['q'].'%'))
                ->orWhereHas('user', fn ($user) => $user->where('email', 'like', '%'.$filters['q'].'%')));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $this->applyDateFilters($query, $filters);

        return view('admin.payments', [
            'payments' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
            'totalPaid' => (clone $query)->where('status', 'paid')->sum('amount'),
        ]);
    }

    public function refunds(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'requested', 'approved', 'rejected', 'paid'])],
        ]);
        $query = Refund::with(['order:id,order_number,customer_id,payment_status', 'order.customer:id,name,email', 'user:id,name,email'])
            ->latest();

        if (! empty($filters['q'])) {
            $query->where(fn ($refunds) => $refunds
                ->where('reason', 'like', '%'.$filters['q'].'%')
                ->orWhereHas('order', fn ($order) => $order->where('order_number', 'like', '%'.$filters['q'].'%'))
                ->orWhereHas('user', fn ($user) => $user->where('email', 'like', '%'.$filters['q'].'%')));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return view('admin.refunds', [
            'refunds' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function updateRefundStatus(Refund $refund, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['requested', 'approved', 'rejected', 'paid'])],
        ]);

        $allowedTransitions = [
            'pending' => ['approved', 'rejected'],
            'requested' => ['approved', 'rejected'],
            'approved' => ['paid'],
        ];

        if ($validated['status'] === $refund->status) {
            return back()->with('success', 'Refund is already '.$refund->status.'.');
        }

        if (! in_array($validated['status'], $allowedTransitions[$refund->status] ?? [], true)) {
            return back()->withErrors(['status' => 'This refund status transition is not allowed.']);
        }

        DB::transaction(function () use ($refund, $validated): void {
            $refund->status = $validated['status'];
            $refund->save();

            if (in_array($validated['status'], ['approved', 'paid'], true)) {
                $refund->order()->update(['payment_status' => 'refunded']);
            } elseif ($validated['status'] === 'rejected') {
                $refund->order()->update(['payment_status' => 'paid']);
            }
        });

        return back()->with('success', 'Refund status updated.');
    }

    public function coupons(): View
    {
        return view('admin.coupons', [
            'coupons' => Coupon::withCount('orders')->latest()->paginate(20)->withQueryString(),
        ]);
    }

    public function storeCoupon(Request $request): RedirectResponse
    {
        $validated = $this->validateCoupon($request);
        Coupon::create($validated);

        return back()->with('success', 'Coupon created successfully.');
    }

    public function updateCoupon(Coupon $coupon, Request $request): RedirectResponse
    {
        $validated = $this->validateCoupon($request, $coupon);
        $coupon->fill($validated)->save();

        return back()->with('success', 'Coupon updated successfully.');
    }

    public function deleteCoupon(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        return back()->with('success', 'Coupon deleted successfully.');
    }

    public function settlements(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'paid'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', Rule::when($request->filled('from'), ['after_or_equal:from'])],
        ]);
        $query = Settlement::with(['restaurant:id,name', 'order:id,order_number'])->latest();

        if (! empty($filters['q'])) {
            $query->where(fn ($settlements) => $settlements
                ->whereHas('restaurant', fn ($restaurant) => $restaurant->where('name', 'like', '%'.$filters['q'].'%'))
                ->orWhereHas('order', fn ($order) => $order->where('order_number', 'like', '%'.$filters['q'].'%')));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $this->applyDateFilters($query, $filters);

        return view('admin.settlements', [
            'settlements' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
            'summary' => [
                'gross' => (clone $query)->sum('gross_amount'),
                'commission' => (clone $query)->sum('commission_amount'),
                'net' => (clone $query)->sum('net_amount'),
            ],
        ]);
    }

    public function updateSettlementStatus(Settlement $settlement, Request $request): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::in(['pending', 'approved', 'paid'])]]);

        if ($validated['status'] === $settlement->status) {
            return back()->with('success', 'Settlement is already '.$settlement->status.'.');
        }

        if (! in_array($validated['status'], self::SETTLEMENT_TRANSITIONS[$settlement->status] ?? [], true)) {
            return back()->withErrors(['status' => 'Settlement status can only move from pending to approved and then paid.']);
        }

        DB::transaction(function () use ($settlement, $validated): void {
            $settlement->status = $validated['status'];
            $settlement->save();
            Commission::where('order_id', $settlement->order_id)->update(['status' => $validated['status']]);

            $type = $validated['status'] === 'paid' ? 'payout' : 'settlement';
            PayoutLedger::updateOrCreate(
                [
                    'settlement_id' => $settlement->id,
                    'status' => $validated['status'],
                    'type' => $type,
                ],
                [
                    'restaurant_id' => $settlement->restaurant_id,
                    'order_id' => $settlement->order_id,
                    'amount' => $settlement->net_amount,
                    'direction' => 'credit',
                    'reference' => 'settlement-'.$settlement->id,
                    'notes' => 'Settlement '.$validated['status'].' for order #'.$settlement->order_id,
                    'metadata' => [
                        'gross_amount' => (float) $settlement->gross_amount,
                        'commission_amount' => (float) $settlement->commission_amount,
                        'net_amount' => (float) $settlement->net_amount,
                    ],
                ]
            );
        });

        return back()->with('success', 'Settlement status updated and payout ledger synchronized.');
    }

    public function payoutLedger(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', Rule::when($request->filled('from'), ['after_or_equal:from'])],
        ]);
        $query = PayoutLedger::with(['restaurant:id,name', 'order:id,order_number'])->latest();

        if (! empty($filters['q'])) {
            $query->where(fn ($ledger) => $ledger
                ->where('reference', 'like', '%'.$filters['q'].'%')
                ->orWhereHas('restaurant', fn ($restaurant) => $restaurant->where('name', 'like', '%'.$filters['q'].'%'))
                ->orWhereHas('order', fn ($order) => $order->where('order_number', 'like', '%'.$filters['q'].'%')));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $this->applyDateFilters($query, $filters);

        return view('admin.payout-ledger', [
            'entries' => $query->paginate(20)->withQueryString(),
            'filters' => $filters,
            'total' => (clone $query)->sum('amount'),
        ]);
    }

    public function exportSettlements(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'paid'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', Rule::when($request->filled('from'), ['after_or_equal:from'])],
        ]);
        $query = Settlement::with(['restaurant:id,name', 'order:id,order_number'])->latest();

        if (! empty($filters['q'])) {
            $query->where(fn ($settlements) => $settlements
                ->whereHas('restaurant', fn ($restaurant) => $restaurant->where('name', 'like', '%'.$filters['q'].'%'))
                ->orWhereHas('order', fn ($order) => $order->where('order_number', 'like', '%'.$filters['q'].'%')));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $this->applyDateFilters($query, $filters);

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'Restaurant', 'Order', 'Gross', 'Commission', 'Net', 'Status', 'Created at']);

            foreach ($query->lazy(500) as $settlement) {
                fputcsv($output, [
                    $settlement->id,
                    $settlement->restaurant?->name ?? '',
                    $settlement->order?->order_number ?? '',
                    $settlement->gross_amount,
                    $settlement->commission_amount,
                    $settlement->net_amount,
                    $settlement->status,
                    $settlement->created_at?->toDateTimeString(),
                ]);
            }

            fclose($output);
        }, 'settlements-'.now()->format('YmdHis').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportPayoutLedger(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:50'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', Rule::when($request->filled('from'), ['after_or_equal:from'])],
        ]);
        $query = PayoutLedger::with(['restaurant:id,name', 'order:id,order_number'])->latest();

        if (! empty($filters['q'])) {
            $query->where(fn ($ledger) => $ledger
                ->where('reference', 'like', '%'.$filters['q'].'%')
                ->orWhereHas('restaurant', fn ($restaurant) => $restaurant->where('name', 'like', '%'.$filters['q'].'%'))
                ->orWhereHas('order', fn ($order) => $order->where('order_number', 'like', '%'.$filters['q'].'%')));
        }

        foreach (['status', 'type'] as $filter) {
            if (! empty($filters[$filter])) {
                $query->where($filter, $filters[$filter]);
            }
        }

        $this->applyDateFilters($query, $filters);

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'Restaurant', 'Order', 'Type', 'Direction', 'Amount', 'Status', 'Reference', 'Created at']);

            foreach ($query->lazy(500) as $entry) {
                fputcsv($output, [
                    $entry->id,
                    $entry->restaurant?->name ?? '',
                    $entry->order?->order_number ?? '',
                    $entry->type,
                    $entry->direction,
                    $entry->amount,
                    $entry->status,
                    $entry->reference,
                    $entry->created_at?->toDateTimeString(),
                ]);
            }

            fclose($output);
        }, 'payout-ledger-'.now()->format('YmdHis').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function assignableRoles(): array
    {
        return auth()->user()->hasRole('super_admin')
            ? self::ROLES
            : array_diff(self::ROLES, ['admin', 'super_admin']);
    }

    private function validateCoupon(Request $request, ?Coupon $coupon = null): array
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'type' => ['required', Rule::in(['fixed', 'percentage'])],
            'value' => ['required', 'numeric', 'min:0', Rule::when($request->input('type') === 'percentage', ['max:100'])],
            'minimum_order_value' => ['required', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_usage' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ]);
        $validated['code'] = strtoupper(trim($validated['code']));

        return $validated;
    }

    private function applyDateFilters($query, array $filters): void
    {
        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }
    }
}
