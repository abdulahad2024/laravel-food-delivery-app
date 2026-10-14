@extends('admin.layout')

@section('title', 'Orders')

@section('content')
    <header>
        <h1 class="text-3xl font-bold tracking-tight text-slate-950">Orders</h1>
        <p class="mt-2 text-slate-500">Search and monitor order, payment and delivery progress.</p>
    </header>

    <form method="GET" action="{{ route('admin.orders') }}" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-2 xl:grid-cols-6">
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Order, customer or restaurant" class="rounded-lg border border-slate-300 px-3 py-2 text-sm xl:col-span-2">
        <select name="restaurant_id" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">All restaurants</option>@foreach ($restaurants as $restaurant)<option value="{{ $restaurant->id }}" @selected(($filters['restaurant_id'] ?? '') == $restaurant->id)>{{ $restaurant->name }}</option>@endforeach</select>
        <select name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">All order statuses</option>@foreach (['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'delivered', 'cancelled', 'rejected'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select>
        <select name="payment_status" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">All payment statuses</option>@foreach (['pending', 'paid', 'failed', 'refunded'] as $status)<option value="{{ $status }}" @selected(($filters['payment_status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
        <input name="from" type="date" value="{{ $filters['from'] ?? '' }}" aria-label="From date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <input name="to" type="date" value="{{ $filters['to'] ?? '' }}" aria-label="To date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <div class="flex gap-2 xl:col-span-6"><button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Apply filters</button><a href="{{ route('admin.orders') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Reset</a></div>
    </form>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Order / date</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Restaurant / rider</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Payment</th><th class="px-4 py-3">Update status</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($orders as $order)
                        <tr class="align-top">
                            <td class="whitespace-nowrap px-4 py-4"><div class="font-semibold text-slate-900">#{{ $order->order_number }}</div><div class="mt-1 text-xs text-slate-500">{{ $order->created_at?->format('M j, Y H:i') }}</div></td>
                            <td class="whitespace-nowrap px-4 py-4"><div>{{ $order->customer?->name ?? 'Unknown' }}</div><div class="mt-1 text-xs text-slate-500">{{ $order->customer?->email }}</div></td>
                            <td class="whitespace-nowrap px-4 py-4"><div>{{ $order->restaurant?->name ?? 'Unknown' }}</div><div class="mt-1 text-xs text-slate-500">Rider: {{ $order->rider?->name ?? 'Unassigned' }}</div></td>
                            <td class="whitespace-nowrap px-4 py-4 font-semibold">${{ number_format((float) $order->total, 2) }}</td>
                            <td class="whitespace-nowrap px-4 py-4"><div class="font-medium">{{ ucfirst($order->payment_status) }}</div><div class="mt-1 text-xs text-slate-500">{{ ucfirst(str_replace('_', ' ', $order->delivery_status)) }}</div></td>
                            <td class="min-w-[14rem] px-4 py-4">
                                @if (! empty($transitions[$order->status]))
                                    <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="flex items-center gap-2">
                                        @csrf
                                        <select name="status" required class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-2 py-2 text-xs">
                                            @foreach ($transitions[$order->status] as $status)
                                                <option value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                                            @endforeach
                                        </select>
                                        <button class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">Update</button>
                                    </form>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500">No orders match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $orders->links() }}
@endsection
