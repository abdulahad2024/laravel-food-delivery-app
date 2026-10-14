@extends('admin.layout')

@section('title', 'Dashboard')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-blue-700">FoodFlow operations</p>
            <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-950">Dashboard</h1>
            <p class="mt-2 text-slate-500">Live activity and financial overview from your database.</p>
        </div>
        <a href="{{ route('admin.orders') }}" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
            Manage orders
        </a>
    </div>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'Customers', 'value' => number_format($customers), 'link' => route('admin.users')],
            ['label' => 'Restaurants', 'value' => number_format($restaurants), 'link' => route('admin.restaurants')],
            ['label' => 'Orders', 'value' => number_format($orders), 'link' => route('admin.orders')],
            ['label' => 'Gross order value', 'value' => '$'.number_format((float) $grossSales, 2), 'link' => route('admin.orders')],
        ] as $stat)
            <a href="{{ $stat['link'] }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-md">
                <p class="text-sm font-medium text-slate-500">{{ $stat['label'] }}</p>
                <p class="mt-3 text-3xl font-bold tracking-tight text-slate-950">{{ $stat['value'] }}</p>
            </a>
        @endforeach
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('admin.restaurants', ['approval_status' => 'pending']) }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-sm font-medium text-slate-500">Restaurants awaiting approval</p>
            <p class="mt-2 text-2xl font-bold text-amber-700">{{ number_format($pendingRestaurants) }}</p>
        </a>
        <a href="{{ route('admin.payments', ['status' => 'paid']) }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-sm font-medium text-slate-500">Paid order value</p>
            <p class="mt-2 text-2xl font-bold text-emerald-700">${{ number_format((float) $revenue, 2) }}</p>
        </a>
        <a href="{{ route('admin.settlements') }}" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <p class="text-sm font-medium text-slate-500">Recorded commission</p>
            <p class="mt-2 text-2xl font-bold text-slate-950">${{ number_format((float) $commission, 2) }}</p>
        </a>
        <div class="grid grid-cols-2 gap-3 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <a href="{{ route('admin.refunds', ['status' => 'requested']) }}">
                <p class="text-xs font-medium text-slate-500">Open refunds</p>
                <p class="mt-2 text-xl font-bold text-rose-700">{{ number_format($pendingRefunds) }}</p>
            </a>
            <a href="{{ route('admin.settlements', ['status' => 'pending']) }}">
                <p class="text-xs font-medium text-slate-500">Pending payouts</p>
                <p class="mt-2 text-xl font-bold text-amber-700">{{ number_format($pendingSettlements) }}</p>
            </a>
        </div>
    </section>

    <section class="grid gap-5 xl:grid-cols-5">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 xl:col-span-2">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Order value</h2>
                    <p class="text-sm text-slate-500">Last 7 days</p>
                </div>
                <a href="{{ route('admin.orders') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">View report</a>
            </div>
            <div class="mt-6 flex h-40 items-end gap-3">
                @foreach ($salesChart as $point)
                    <div class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-2" title="${{ number_format($point['amount'], 2) }}">
                        <div class="w-full rounded-t-md bg-blue-500" style="height: {{ $point['height'] }}%"></div>
                        <span class="text-xs text-slate-500">{{ $point['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 xl:col-span-3">
            <div class="mb-4 flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Recent orders</h2>
                    <p class="text-sm text-slate-500">Latest activity across restaurants</p>
                </div>
                <a href="{{ route('admin.orders') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-900">All orders</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="py-3 pr-4 font-semibold">Order</th>
                            <th class="py-3 pr-4 font-semibold">Customer</th>
                            <th class="py-3 pr-4 font-semibold">Restaurant</th>
                            <th class="py-3 pr-4 font-semibold">Total</th>
                            <th class="py-3 font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($recentOrders as $order)
                            <tr>
                                <td class="whitespace-nowrap py-3 pr-4 font-semibold text-slate-900">#{{ $order->order_number }}</td>
                                <td class="whitespace-nowrap py-3 pr-4">{{ $order->customer?->name ?? 'Unknown' }}</td>
                                <td class="whitespace-nowrap py-3 pr-4">{{ $order->restaurant?->name ?? 'Unknown' }}</td>
                                <td class="whitespace-nowrap py-3 pr-4">${{ number_format((float) $order->total, 2) }}</td>
                                <td class="whitespace-nowrap py-3">
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ str_replace('_', ' ', $order->status) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-10 text-center text-slate-500">There are no orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
