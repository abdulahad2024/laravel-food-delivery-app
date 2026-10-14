@extends('admin.layout')

@section('title', 'Payments')

@section('content')
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div><h1 class="text-3xl font-bold tracking-tight text-slate-950">Payments</h1><p class="mt-2 text-slate-500">Read-only payment audit linked to orders and customers.</p></div>
        <div class="rounded-xl bg-emerald-50 px-4 py-3 text-sm"><span class="text-emerald-800">Paid in results</span><strong class="ml-3 text-lg text-emerald-900">${{ number_format((float) $totalPaid, 2) }}</strong></div>
    </header>

    <form method="GET" action="{{ route('admin.payments') }}" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-2 xl:grid-cols-5">
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Order, email or reference" class="rounded-lg border border-slate-300 px-3 py-2 text-sm xl:col-span-2">
        <select name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">All payment statuses</option>@foreach (['pending', 'paid', 'failed', 'refunded'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
        <input name="from" type="date" value="{{ $filters['from'] ?? '' }}" aria-label="From date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <input name="to" type="date" value="{{ $filters['to'] ?? '' }}" aria-label="To date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <div class="flex gap-2 xl:col-span-5"><button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Apply filters</button><a href="{{ route('admin.payments') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Reset</a></div>
    </form>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Payment</th><th class="px-4 py-3">Order / restaurant</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Gateway / reference</th><th class="px-4 py-3">Amount / status</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($payments as $payment)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-4"><div class="font-semibold text-slate-900">#{{ $payment->id }}</div><div class="mt-1 text-xs text-slate-500">{{ $payment->paid_at?->format('M j, Y H:i') ?? $payment->created_at?->format('M j, Y H:i') }}</div></td>
                            <td class="whitespace-nowrap px-4 py-4"><div>#{{ $payment->order?->order_number ?? '—' }}</div><div class="mt-1 text-xs text-slate-500">{{ $payment->order?->restaurant?->name ?? 'Unknown' }}</div></td>
                            <td class="whitespace-nowrap px-4 py-4"><div>{{ $payment->user?->name ?? 'Unknown' }}</div><div class="mt-1 text-xs text-slate-500">{{ $payment->user?->email }}</div></td>
                            <td class="whitespace-nowrap px-4 py-4"><div>{{ ucfirst($payment->gateway) }}</div><div class="mt-1 text-xs text-slate-500">{{ $payment->reference ?: 'No reference' }}</div></td>
                            <td class="whitespace-nowrap px-4 py-4"><div class="font-semibold">${{ number_format((float) $payment->amount, 2) }}</div><div class="mt-1 text-xs font-medium text-slate-500">{{ ucfirst($payment->status) }}</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-slate-500">No payments match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $payments->links() }}
@endsection
