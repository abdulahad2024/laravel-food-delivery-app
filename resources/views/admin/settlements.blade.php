@extends('admin.layout')

@section('title', 'Settlements')

@section('content')
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div><h1 class="text-3xl font-bold tracking-tight text-slate-950">Settlements</h1><p class="mt-2 text-slate-500">Reconcile restaurant earnings and keep the payout ledger synchronized.</p></div>
        <a href="{{ route('admin.settlements.export', request()->query()) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Export CSV</a>
    </header>

    <section class="grid gap-4 sm:grid-cols-3">
        @foreach ([['label' => 'Gross total', 'value' => $summary['gross']], ['label' => 'Commission total', 'value' => $summary['commission']], ['label' => 'Net payable', 'value' => $summary['net']]] as $stat)
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"><p class="text-sm text-slate-500">{{ $stat['label'] }}</p><p class="mt-2 text-2xl font-bold text-slate-950">${{ number_format((float) $stat['value'], 2) }}</p></div>
        @endforeach
    </section>

    <form method="GET" action="{{ route('admin.settlements') }}" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-2 xl:grid-cols-5">
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Restaurant or order" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <select name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">All settlement statuses</option>@foreach (['pending', 'approved', 'paid'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
        <input name="from" type="date" value="{{ $filters['from'] ?? '' }}" aria-label="From date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <input name="to" type="date" value="{{ $filters['to'] ?? '' }}" aria-label="To date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <div class="flex gap-2"><button class="flex-1 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Filter</button><a href="{{ route('admin.settlements') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Reset</a></div>
    </form>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Restaurant / order</th><th class="px-4 py-3">Gross</th><th class="px-4 py-3">Commission</th><th class="px-4 py-3">Net payout</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Update</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($settlements as $settlement)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-4"><div class="font-semibold text-slate-900">{{ $settlement->restaurant?->name ?? 'Unknown' }}</div><div class="mt-1 text-xs text-slate-500">Order #{{ $settlement->order?->order_number ?? '—' }}</div></td>
                            <td class="whitespace-nowrap px-4 py-4">${{ number_format((float) $settlement->gross_amount, 2) }}</td>
                            <td class="whitespace-nowrap px-4 py-4">${{ number_format((float) $settlement->commission_amount, 2) }}</td>
                            <td class="whitespace-nowrap px-4 py-4 font-semibold">${{ number_format((float) $settlement->net_amount, 2) }}</td>
                            <td class="whitespace-nowrap px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $settlement->status === 'paid' ? 'bg-emerald-100 text-emerald-800' : ($settlement->status === 'approved' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800') }}">{{ ucfirst($settlement->status) }}</span></td>
                            <td class="min-w-[13rem] px-4 py-4">
                                @if ($settlement->status === 'pending')
                                    <form method="POST" action="{{ route('admin.settlements.status', $settlement) }}" class="flex items-center gap-2">@csrf<select name="status" class="rounded-lg border border-slate-300 bg-white px-2 py-2 text-xs"><option value="approved">Approve</option></select><button class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white">Update</button></form>
                                @elseif ($settlement->status === 'approved')
                                    <form method="POST" action="{{ route('admin.settlements.status', $settlement) }}" class="flex items-center gap-2">@csrf<select name="status" class="rounded-lg border border-slate-300 bg-white px-2 py-2 text-xs"><option value="paid">Mark paid</option></select><button class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white">Update</button></form>
                                @else
                                    <span class="text-xs text-slate-500">Payout complete</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500">No settlements match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $settlements->links() }}
@endsection
