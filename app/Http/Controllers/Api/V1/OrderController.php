<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Food;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function preview(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => ['required', 'integer', 'exists:restaurants,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.food_id' => ['required', 'integer', 'exists:foods,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ]);

        $restaurant = Restaurant::findOrFail($validated['restaurant_id']);
        $totals = $this->calculateTotals($restaurant, $validated['items'], $validated['coupon_code'] ?? null);

        return response()->json([
            'restaurant' => $restaurant,
            'items' => $totals['items'],
            'subtotal' => $totals['subtotal'],
            'delivery_fee' => $totals['delivery_fee'],
            'discount_amount' => $totals['discount_amount'],
            'total' => $totals['total'],
            'minimum_order_value' => $restaurant->minimum_order_value,
            'coupon' => $totals['coupon'],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id' => ['required', 'integer', 'exists:restaurants,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.food_id' => ['required', 'integer', 'exists:foods,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $restaurant = Restaurant::findOrFail($validated['restaurant_id']);
        $totals = $this->calculateTotals($restaurant, $validated['items'], $validated['coupon_code'] ?? null);

        if ($totals['subtotal'] < $restaurant->minimum_order_value) {
            return response()->json([
                'message' => 'Order subtotal is below the minimum order value.',
            ], 422);
        }

        $order = DB::transaction(function () use ($request, $restaurant, $validated, $totals) {
            $order = Order::create([
                'customer_id' => $request->user()->id,
                'restaurant_id' => $restaurant->id,
                'address_id' => $request->input('address_id'),
                'coupon_id' => $totals['coupon']?->id,
                'coupon_code' => $totals['coupon']?->code,
                'coupon_discount_amount' => $totals['discount_amount'],
                'order_number' => 'ORD-'.strtoupper(Str::random(8)),
                'status' => 'pending',
                'payment_status' => 'pending',
                'subtotal' => $totals['subtotal'],
                'delivery_fee' => $totals['delivery_fee'],
                'discount_amount' => $totals['discount_amount'],
                'total' => $totals['total'],
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($totals['items'] as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'food_id' => $item['food_id'],
                    'name' => $item['name'],
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            return $order->fresh(['items', 'restaurant', 'customer']);
        });

        return response()->json($order, 201);
    }

    public function index(Request $request)
    {
        $orders = $request->user()->orders()->with('restaurant', 'items')->latest()->paginate(15);

        return response()->json($orders);
    }

    public function show(Request $request, Order $order)
    {
        if ((int) $order->customer_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'This order does not belong to you.'], 403);
        }

        return response()->json($order->load('restaurant', 'items'));
    }

    protected function calculateTotals(Restaurant $restaurant, array $items, ?string $couponCode = null): array
    {
        $lineItems = [];
        $subtotal = 0;

        foreach ($items as $entry) {
            $food = Food::findOrFail($entry['food_id']);

            if ($food->restaurant_id !== $restaurant->id) {
                abort(422, 'Food item does not belong to this restaurant.');
            }

            if (! $food->is_available) {
                abort(422, 'One or more foods are unavailable.');
            }

            $quantity = (int) $entry['quantity'];
            $itemSubtotal = $food->price * $quantity;
            $subtotal += $itemSubtotal;

            $lineItems[] = [
                'food_id' => $food->id,
                'name' => $food->name,
                'unit_price' => $food->price,
                'quantity' => $quantity,
                'subtotal' => $itemSubtotal,
            ];
        }

        $deliveryFee = (float) $restaurant->delivery_fee;
        $coupon = null;
        $discountAmount = 0;

        if (! empty($couponCode)) {
            $coupon = Coupon::whereRaw('LOWER(code) = ?', [strtolower(trim($couponCode))])->first();

            if (! $coupon || ! $coupon->isValidForSubtotal($subtotal, auth()->id())) {
                abort(422, 'Coupon is invalid or not applicable for this order.');
            }

            $discountAmount = $coupon->calculateDiscount($subtotal);
        }

        $total = $subtotal + $deliveryFee - $discountAmount;

        return [
            'items' => $lineItems,
            'subtotal' => round($subtotal, 2),
            'delivery_fee' => round($deliveryFee, 2),
            'discount_amount' => round($discountAmount, 2),
            'total' => round($total, 2),
            'coupon' => $coupon,
        ];
    }
}
