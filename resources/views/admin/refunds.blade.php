@extends('admin.layout')

@section('title', 'Refunds')

@section('content')
    <header>
        <h1 class="text-3xl font-bold tracking-tight text-slate-950">Refund requests</h1>
        <p class="mt-2 text-slate-500">Review customer requests and synchronize refunded order payment status.</p>
    </header>

    <form method="GET" action="{{ route('admin.refunds') }}" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-3">
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Order, customer email or reason" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <select name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">All refund statuses</option>@foreach (['pending', 'requested', 'approved', 'rejected', 'paid'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
        <div class="flex gap-2"><button class="flex-1 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Filter</button><a href="{{ route('admin.refunds') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Reset</a></div>
    </form>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Order / date</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Amount / reason</th><th class="px-4 py-3">Order payment</th><th class="px-4 py-3">Review</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($refunds as $refund)
                        <tr class="align-top">
                            <td class="whitespace-nowrap px-4 py-4"><div class="font-semibold text-slate-900">#{{ $refund->order?->order_number ?? $refund->order_id }}</div><div class="mt-1 text-xs text-slate-500">{{ $refund->created_at?->format('M j, Y H:i') }}</div></td>
                            <td class="whitespace-nowrap px-4 py-4"><div>{{ $refund->user?->name ?? 'Unknown' }}</div><div class="mt-1 text-xs text-slate-500">{{ $refund->user?->email }}</div></td>
                            <td class="max-w-md px-4 py-4"><div class="font-semibold">${{ number_format((float) $refund->amount, 2) }}</div><p class="mt-1 text-xs text-slate-500">{{ $refund->reason ?: 'No reason provided' }}</p></td>
                            <td class="whitespace-nowrap px-4 py-4">{{ ucfirst($refund->order?->payment_status ?? 'unknown') }}</td>
                            <td class="min-w-[14rem] px-4 py-4">
                                @if (in_array($refund->status, ['pending', 'requested'], true))
                                    <form method="POST" action="{{ route('admin.refunds.status', $refund) }}" class="flex items-center gap-2">
                                        @csrf
                                        <select name="status" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-2 py-2 text-xs"><option value="approved">Approve</option><option value="rejected">Reject</option></select>
                                        <button class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">Review</button>
                                    </form>
                                @elseif ($refund->status === 'approved')
                                    <form method="POST" action="{{ route('admin.refunds.status', $refund) }}" class="flex items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="status" value="paid">
                                        <button class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">Mark refunded</button>
                                    </form>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ ucfirst($refund->status) }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-slate-500">No refunds match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $refunds->links() }}
@endsection
