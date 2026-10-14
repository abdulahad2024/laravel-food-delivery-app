<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->user()?->hasRole('admin') && ! $request->user()?->hasRole('super_admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json(
            Refund::with(['order.customer', 'user'])->latest()->paginate(20)
        );
    }

    public function updateStatus(Request $request, Refund $refund)
    {
        if (! $request->user()?->hasRole('admin') && ! $request->user()?->hasRole('super_admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:requested,approved,rejected,paid'],
        ]);

        $refund->status = $validated['status'];
        $refund->save();

        if ($validated['status'] === 'approved' || $validated['status'] === 'paid') {
            $refund->order->payment_status = 'refunded';
            $refund->order->save();
        }

        if ($validated['status'] === 'rejected') {
            $refund->order->payment_status = 'paid';
            $refund->order->save();
        }

        return response()->json([
            'refund' => $refund->fresh(['order.customer', 'user']),
            'order' => $refund->order->fresh(),
        ]);
    }

    public function store(Request $request, Order $order)
    {
        if ((int) $order->customer_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'This order does not belong to you.'], 403);
        }

        if ($order->payment_status !== 'paid') {
            return response()->json(['message' => 'Only paid orders can be refunded.'], 422);
        }

        if ($order->refund()->exists()) {
            return response()->json(['message' => 'A refund request already exists for this order.'], 422);
        }

        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:'.(float) $order->total],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $refundAmount = (float) ($validated['amount'] ?? $order->total);

        $refund = Refund::create([
            'order_id' => $order->id,
            'user_id' => $request->user()->id,
            'amount' => $refundAmount,
            'reason' => $validated['reason'],
            'status' => 'requested',
        ]);

        $order->payment_status = 'refunded';
        $order->save();

        return response()->json([
            'refund' => $refund,
            'order' => $order->fresh(),
        ], 201);
    }
}
