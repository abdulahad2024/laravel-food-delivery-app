<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index()
    {
        $coupons = Coupon::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->latest()
            ->get();

        return response()->json($coupons);
    }

    public function adminIndex(Request $request)
    {
        if (! $request->user()?->hasRole('admin') && ! $request->user()?->hasRole('super_admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $query = Coupon::query();

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->boolean('is_active'), FILTER_VALIDATE_BOOL));
        }

        return response()->json($query->latest()->paginate(20));
    }

    public function store(Request $request)
    {
        if (! $request->user()?->hasRole('admin') && ! $request->user()?->hasRole('super_admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $this->validateCouponData($request);
        $coupon = Coupon::create($validated);

        return response()->json($coupon, 201);
    }

    public function update(Request $request, Coupon $coupon)
    {
        if (! $request->user()?->hasRole('admin') && ! $request->user()?->hasRole('super_admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $this->validateCouponData($request, $coupon);
        $coupon->fill($validated);
        $coupon->save();

        return response()->json($coupon);
    }

    public function destroy(Request $request, Coupon $coupon)
    {
        if (! $request->user()?->hasRole('admin') && ! $request->user()?->hasRole('super_admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $coupon->delete();

        return response()->json(['message' => 'Coupon deleted successfully.']);
    }

    public function validate(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'subtotal' => ['required', 'numeric', 'min:0'],
        ]);

        $coupon = Coupon::whereRaw('LOWER(code) = ?', [strtolower(trim($validated['code']))])->first();

        if (! $coupon) {
            return response()->json(['valid' => false, 'message' => 'Coupon not found.'], 404);
        }

        $subtotal = (float) $validated['subtotal'];
        $isValid = $coupon->isValidForSubtotal($subtotal, $request->user()?->id);

        return response()->json([
            'valid' => $isValid,
            'coupon' => $coupon,
            'discount_amount' => $isValid ? $coupon->calculateDiscount($subtotal) : 0,
            'message' => $isValid ? 'Coupon applied successfully.' : 'Coupon is expired, inactive, below the minimum order value, or has reached its usage limit.',
        ]);
    }

    protected function validateCouponData(Request $request, ?Coupon $coupon = null): array
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', $coupon ? 'unique:coupons,code,'.$coupon->id : 'unique:coupons,code'],
            'type' => ['required', 'string', 'in:fixed,percentage'],
            'value' => ['required', 'numeric', 'min:0'],
            'minimum_order_value' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_usage' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));

        return $validated;
    }
}
