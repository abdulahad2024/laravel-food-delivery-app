<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function confirm(Request $request, Order $order)
    {
        if ((int) $order->customer_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'This order does not belong to you.'], 403);
        }

        $validated = $request->validate([
            'gateway' => ['required', 'string', 'max:50'],
            'reference' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        if ((float) $validated['amount'] !== (float) $order->total) {
            return response()->json(['message' => 'Payment amount does not match the order total.'], 422);
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $request->user()->id,
            'amount' => $validated['amount'],
            'gateway' => $validated['gateway'],
            'reference' => $validated['reference'],
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $order->payment_status = 'paid';
        $order->save();

        $commissionRate = 10.00;
        $commissionAmount = round((float) $order->total * ($commissionRate / 100), 2);

        $commission = Commission::firstOrCreate([
            'order_id' => $order->id,
        ], [
            'restaurant_id' => $order->restaurant_id,
            'amount' => $commissionAmount,
            'rate' => $commissionRate,
            'status' => 'pending',
        ]);

        \App\Models\Settlement::updateOrCreate(
            ['order_id' => $order->id],
            [
                'restaurant_id' => $order->restaurant_id,
                'gross_amount' => (float) $order->total,
                'commission_amount' => $commissionAmount,
                'net_amount' => round((float) $order->total - $commissionAmount, 2),
                'status' => 'pending',
            ]
        );

        NotificationLog::record($order->customer_id, 'payment_confirmed', 'payment', [
            'order_id' => $order->id,
            'amount' => $payment->amount,
            'reference' => $payment->reference,
        ]);

        return response()->json([
            'payment' => $payment,
            'order' => $order->fresh(),
            'commission' => $commission,
            'settlement' => \App\Models\Settlement::where('order_id', $order->id)->first(),
        ]);
    }
}
