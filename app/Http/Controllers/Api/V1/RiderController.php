<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\Order;
use Illuminate\Http\Request;

class RiderController extends Controller
{
    public function availability(Request $request)
    {
        $validated = $request->validate([
            'online' => ['required', 'boolean'],
        ]);

        $request->user()->status = $validated['online'] ? 'online' : 'offline';
        $request->user()->save();

        return response()->json([
            'online' => $validated['online'],
            'status' => $request->user()->status,
        ]);
    }

    public function deliveries(Request $request)
    {
        $orders = Order::where('rider_id', $request->user()->id)
            ->with('restaurant', 'customer', 'items')
            ->latest()
            ->paginate(20);

        return response()->json($orders);
    }

    public function acceptDelivery(Request $request, Order $order)
    {
        if ((int) $order->rider_id !== (int) $request->user()->id) {
            if (! is_null($order->rider_id)) {
                return response()->json(['message' => 'This delivery is already assigned to another rider.'], 409);
            }

            $order->rider_id = $request->user()->id;
            $order->delivery_status = 'assigned';
            $order->save();
        }

        NotificationLog::record($order->customer_id, 'delivery_assigned', 'rider', [
            'order_id' => $order->id,
            'rider_id' => $request->user()->id,
            'delivery_status' => $order->delivery_status,
        ]);

        return response()->json($order->fresh('rider', 'restaurant', 'customer'));
    }

    public function updateDeliveryStatus(Request $request, Order $order)
    {
        if ((int) $order->rider_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'You are not assigned to this order.'], 403);
        }

        $validated = $request->validate([
            'delivery_status' => ['required', 'string', 'in:assigned,picked_up,delivered'],
        ]);

        $allowed = [
            'assigned' => ['picked_up'],
            'picked_up' => ['delivered'],
        ];

        $current = $order->delivery_status;

        if (! isset($allowed[$current]) || ! in_array($validated['delivery_status'], $allowed[$current], true)) {
            return response()->json(['message' => 'Invalid delivery status transition.'], 422);
        }

        $order->delivery_status = $validated['delivery_status'];
        $order->save();

        NotificationLog::record($order->customer_id, 'delivery_status_updated', 'rider', [
            'order_id' => $order->id,
            'delivery_status' => $order->delivery_status,
        ]);

        return response()->json($order->fresh('rider', 'restaurant', 'customer'));
    }
}
