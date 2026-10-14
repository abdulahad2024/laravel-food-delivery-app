@extends('admin.layout')

@section('title', 'Payout ledger')

@section('content')
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div><h1 class="text-3xl font-bold tracking-tight text-slate-950">Payout ledger</h1><p class="mt-2 text-slate-500">An auditable record of settlement and payout status changes.</p></div>
        <div class="flex items-center gap-3"><div class="rounded-xl bg-white px-4 py-3 text-sm shadow-sm ring-1 ring-slate-200"><span class="text-slate-500">Filtered amount</span><strong class="ml-2 text-slate-950">${{ number_format((float) $total, 2) }}</strong></div><a href="{{ route('admin.payout-ledger.export', request()->query()) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Export CSV</a></div>
    </header>

    <form method="GET" action="{{ route('admin.payout-ledger') }}" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-2 xl:grid-cols-6">
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Restaurant, order or reference" class="rounded-lg border border-slate-300 px-3 py-2 text-sm xl:col-span-2">
        <select name="type" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">All entry types</option>@foreach (['settlement', 'payout'] as $type)<option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ ucfirst($type) }}</option>@endforeach</select>
        <select name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">All statuses</option>@foreach (['pending', 'approved', 'paid'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
        <input name="from" type="date" value="{{ $filters['from'] ?? '' }}" aria-label="From date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <input name="to" type="date" value="{{ $filters['to'] ?? '' }}" aria-label="To date" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <div class="flex gap-2 xl:col-span-6"><button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Apply filters</button><a href="{{ route('admin.payout-ledger') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Reset</a></div>
    </form>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Date / reference</th><th class="px-4 py-3">Restaurant / order</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Direction</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Status</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($entries as $entry)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-4"><div class="font-semibold text-slate-900">{{ $entry->created_at?->format('M j, Y H:i') }}</div><div class="mt-1 text-xs text-slate-500">{{ $entry->reference ?: '—' }}</div></td>
                            <td class="whitespace-nowrap px-4 py-4"><div>{{ $entry->restaurant?->name ?? 'Unknown' }}</div><div class="mt-1 text-xs text-slate-500">#{{ $entry->order?->order_number ?? '—' }}</div></td>
                            <td class="whitespace-nowrap px-4 py-4">{{ ucfirst($entry->type) }}</td>
                            <td class="whitespace-nowrap px-4 py-4">{{ ucfirst($entry->direction) }}</td>
                            <td class="whitespace-nowrap px-4 py-4 font-semibold">${{ number_format((float) $entry->amount, 2) }}</td>
                            <td class="whitespace-nowrap px-4 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ ucfirst($entry->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-slate-500">No payout entries match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $entries->links() }}
@endsection
