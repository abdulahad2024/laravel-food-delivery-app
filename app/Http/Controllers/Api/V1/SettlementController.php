<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Order;
use App\Models\PayoutLedger;
use App\Models\RestaurantUser;
use App\Models\Settlement;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    public function index(Request $request)
    {
        $query = Settlement::with(['restaurant', 'order']);
        $user = $request->user();

        if ($user instanceof RestaurantUser) {
            $restaurantIds = $user->restaurants()->pluck('id');
            $query->whereIn('restaurant_id', $restaurantIds);
        } elseif (! $this->isAdmin($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($query->latest()->get());
    }

    public function summary(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $query = Settlement::query();

        if ($user instanceof RestaurantUser) {
            $query->whereIn('restaurant_id', $user->restaurants()->pluck('id'));
        } elseif (! $this->isAdmin($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $totals = $query->selectRaw('status, COUNT(*) as count, SUM(gross_amount) as gross_amount, SUM(commission_amount) as commission_amount, SUM(net_amount) as net_amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $summary = ['pending' => 0, 'approved' => 0, 'paid' => 0];

        foreach (['pending', 'approved', 'paid'] as $status) {
            $row = $totals->get($status, null);

            $summary[$status] = [
                'count' => (int) ($row->count ?? 0),
                'gross_amount' => (float) ($row->gross_amount ?? 0),
                'commission_amount' => (float) ($row->commission_amount ?? 0),
                'net_amount' => (float) ($row->net_amount ?? 0),
            ];
        }

        return response()->json([
            'summary' => $summary,
            'total_gross_amount' => (float) $query->sum('gross_amount'),
            'total_commission_amount' => (float) $query->sum('commission_amount'),
            'total_net_amount' => (float) $query->sum('net_amount'),
        ]);
    }

    public function ledger(Request $request)
    {
        $query = PayoutLedger::with(['restaurant', 'order', 'settlement'])->latest();
        $user = $request->user();

        if ($user instanceof RestaurantUser) {
            $query->whereIn('restaurant_id', $user->restaurants()->pluck('id'));
        } elseif (! $this->isAdmin($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($query->paginate(20));
    }

    public function ledgerSummary(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $query = PayoutLedger::query();

        if ($user instanceof RestaurantUser) {
            $query->whereIn('restaurant_id', $user->restaurants()->pluck('id'));
        } elseif (! $this->isAdmin($user)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $totals = $query->selectRaw('status, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $summary = ['pending' => 0, 'approved' => 0, 'paid' => 0];

        foreach (['pending', 'approved', 'paid'] as $status) {
            $row = $totals->get($status);
            $summary[$status] = [
                'count' => (int) ($row->count ?? 0),
                'amount' => (float) ($row->total_amount ?? 0),
            ];
        }

        return response()->json([
            'summary' => $summary,
            'total_amount' => (float) $query->sum('amount'),
        ]);
    }

    public function export(Request $request)
    {
        $user = $request->user();

        if (! $user || (! $user->hasRole('admin') && ! $user->hasRole('super_admin'))) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $query = Settlement::with(['restaurant', 'order']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from.' 00:00:00');
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to.' 23:59:59');
        }

        $rows = $query->latest()->get();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'restaurant_name', 'order_number', 'gross_amount', 'commission_amount', 'net_amount', 'status', 'created_at']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->id,
                    $row->restaurant?->name ?? '',
                    $row->order?->order_number ?? '',
                    $row->gross_amount,
                    $row->commission_amount,
                    $row->net_amount,
                    $row->status,
                    $row->created_at?->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, 'settlements-export-'.now()->format('YmdHis').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function ledgerExport(Request $request)
    {
        $user = $request->user();

        if (! $user || (! $user->hasRole('admin') && ! $user->hasRole('super_admin'))) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $query = PayoutLedger::with(['restaurant', 'order', 'settlement']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->from.' 00:00:00');
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->to.' 23:59:59');
        }

        $rows = $query->latest()->get();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'restaurant_name', 'order_number', 'type', 'direction', 'amount', 'status', 'reference', 'created_at']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->id,
                    $row->restaurant?->name ?? '',
                    $row->order?->order_number ?? '',
                    $row->type,
                    $row->direction,
                    $row->amount,
                    $row->status,
                    $row->reference,
                    $row->created_at?->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, 'payout-ledger-export-'.now()->format('YmdHis').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function updateStatus(Request $request, Settlement $settlement)
    {
        if (! $request->user()?->hasRole('admin') && ! $request->user()?->hasRole('super_admin')) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:pending,approved,paid'],
        ]);

        $settlement->status = $validated['status'];
        $settlement->save();

        $commission = Commission::where('order_id', $settlement->order_id)->first();

        if ($commission) {
            $commission->status = $validated['status'];
            $commission->save();
        }

        $this->recordLedger($settlement, $validated['status']);

        return response()->json($settlement->fresh(['restaurant', 'order']));
    }

    public function generate(Request $request, Order $order)
    {
        $user = $request->user();

        if (! $this->isAdmin($user) && ! ($user instanceof RestaurantUser)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($user instanceof RestaurantUser && ! $user->restaurants()->whereKey($order->restaurant_id)->exists()) {
            return response()->json(['message' => 'Order does not belong to this restaurant.'], 403);
        }

        $grossAmount = (float) $order->total;
        $commission = Commission::firstOrCreate(
            ['order_id' => $order->id],
            [
                'restaurant_id' => $order->restaurant_id,
                'amount' => round($grossAmount * 0.10, 2),
                'rate' => 10.00,
                'status' => 'pending',
            ]
        );

        if ($commission->restaurant_id !== $order->restaurant_id) {
            $commission->restaurant_id = $order->restaurant_id;
        }
        $commission->amount = round($grossAmount * 0.10, 2);
        $commission->rate = 10.00;
        $commission->status = $commission->status ?? 'pending';
        $commission->save();

        $commissionAmount = (float) $commission->amount;
        $netAmount = round($grossAmount - $commissionAmount, 2);

        $settlement = Settlement::updateOrCreate(
            ['order_id' => $order->id],
            [
                'restaurant_id' => $order->restaurant_id,
                'gross_amount' => $grossAmount,
                'commission_amount' => $commissionAmount,
                'net_amount' => $netAmount,
                'status' => 'pending',
            ]
        );

        $this->recordLedger($settlement, 'pending');

        return response()->json($settlement->fresh(['restaurant', 'order']));
    }

    protected function recordLedger(Settlement $settlement, string $status): void
    {
        $type = $status === 'paid' ? 'payout' : 'settlement';

        PayoutLedger::updateOrCreate(
            [
                'settlement_id' => $settlement->id,
                'status' => $status,
                'type' => $type,
            ],
            [
                'restaurant_id' => $settlement->restaurant_id,
                'order_id' => $settlement->order_id,
                'amount' => (float) $settlement->net_amount,
                'direction' => 'credit',
                'reference' => 'settlement-'.$settlement->id,
                'notes' => 'Settlement '.$status.' for order #'.$settlement->order_id,
                'metadata' => [
                    'gross_amount' => (float) $settlement->gross_amount,
                    'commission_amount' => (float) $settlement->commission_amount,
                    'net_amount' => (float) $settlement->net_amount,
                ],
            ]
        );
    }

    private function isAdmin(?Authenticatable $user): bool
    {
        return $user instanceof \App\Models\User
            && ($user->hasRole('admin') || $user->hasRole('super_admin'));
    }
}
