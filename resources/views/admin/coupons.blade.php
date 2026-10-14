@extends('admin.layout')

@section('title', 'Coupons')

@section('content')
    <header>
        <h1 class="text-3xl font-bold tracking-tight text-slate-950">Coupons</h1>
        <p class="mt-2 text-slate-500">Create promotions and manage their activity window and usage limits.</p>
    </header>

    <details class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <summary class="cursor-pointer list-none px-5 py-4 font-semibold text-blue-700">＋ Create a coupon</summary>
        <form method="POST" action="{{ route('admin.coupons.store') }}" class="grid gap-4 border-t border-slate-100 p-5 sm:grid-cols-2 xl:grid-cols-4">
            @csrf
            <label class="text-sm font-medium text-slate-700">Coupon code<input name="code" value="{{ old('code') }}" required maxlength="50" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 uppercase"></label>
            <label class="text-sm font-medium text-slate-700">Discount type<select name="type" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2"><option value="percentage">Percentage</option><option value="fixed">Fixed amount</option></select></label>
            <label class="text-sm font-medium text-slate-700">Value<input name="value" type="number" min="0" step="0.01" value="{{ old('value') }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Minimum order<input name="minimum_order_value" type="number" min="0" step="0.01" value="{{ old('minimum_order_value', 0) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Starts at<input name="starts_at" type="datetime-local" value="{{ old('starts_at') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Expires at<input name="expires_at" type="datetime-local" value="{{ old('expires_at') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Maximum uses<input name="max_usage" type="number" min="1" value="{{ old('max_usage') }}" placeholder="Unlimited" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Active<select name="is_active" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2"><option value="1">Yes</option><option value="0">No</option></select></label>
            <div class="sm:col-span-2 xl:col-span-4"><button class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Create coupon</button></div>
        </form>
    </details>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Code / uses</th><th class="px-4 py-3">Discount</th><th class="px-4 py-3">Validity / limits</th><th class="px-4 py-3">Edit</th><th class="px-4 py-3">Delete</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($coupons as $coupon)
                        <tr class="align-top">
                            <td class="whitespace-nowrap px-4 py-4"><div class="font-bold tracking-wide text-slate-950">{{ $coupon->code }}</div><div class="mt-1 text-xs text-slate-500">{{ $coupon->orders_count }} uses</div></td>
                            <td class="whitespace-nowrap px-4 py-4">{{ $coupon->type === 'percentage' ? number_format((float) $coupon->value, 2).'%' : '$'.number_format((float) $coupon->value, 2) }}<div class="mt-1 text-xs text-slate-500">Min. ${{ number_format((float) $coupon->minimum_order_value, 2) }}</div></td>
                            <td class="min-w-[10rem] px-4 py-4 text-xs text-slate-600"><div>{{ $coupon->starts_at?->format('M j, Y H:i') ?? 'Starts now' }}</div><div>{{ $coupon->expires_at?->format('M j, Y H:i') ?? 'No expiry' }}</div><div>Max: {{ $coupon->max_usage ?? 'Unlimited' }}</div></td>
                            <td class="min-w-[35rem] px-4 py-4">
                                <form id="coupon-{{ $coupon->id }}" method="POST" action="{{ route('admin.coupons.update', $coupon) }}" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                                    @csrf @method('PUT')
                                    <input name="code" value="{{ $coupon->code }}" required maxlength="50" aria-label="Coupon code" class="rounded-lg border border-slate-300 px-2 py-1.5 uppercase">
                                    <select name="type" aria-label="Type" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5"><option value="percentage" @selected($coupon->type === 'percentage')>Percentage</option><option value="fixed" @selected($coupon->type === 'fixed')>Fixed</option></select>
                                    <input name="value" type="number" min="0" step="0.01" value="{{ $coupon->value }}" required aria-label="Value" class="rounded-lg border border-slate-300 px-2 py-1.5">
                                    <input name="minimum_order_value" type="number" min="0" step="0.01" value="{{ $coupon->minimum_order_value }}" required aria-label="Minimum order" class="rounded-lg border border-slate-300 px-2 py-1.5">
                                    <input name="starts_at" type="datetime-local" value="{{ $coupon->starts_at?->format('Y-m-d\TH:i') }}" aria-label="Starts at" class="rounded-lg border border-slate-300 px-2 py-1.5">
                                    <input name="expires_at" type="datetime-local" value="{{ $coupon->expires_at?->format('Y-m-d\TH:i') }}" aria-label="Expires at" class="rounded-lg border border-slate-300 px-2 py-1.5">
                                    <input name="max_usage" type="number" min="1" value="{{ $coupon->max_usage }}" placeholder="Unlimited" aria-label="Maximum uses" class="rounded-lg border border-slate-300 px-2 py-1.5">
                                    <select name="is_active" aria-label="Active" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5"><option value="1" @selected($coupon->is_active)>Active</option><option value="0" @selected(! $coupon->is_active)>Inactive</option></select>
                                </form>
                                <button form="coupon-{{ $coupon->id }}" class="mt-2 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">Save changes</button>
                            </td>
                            <td class="px-4 py-4">
                                <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}" onsubmit="return confirm('Delete this coupon? Existing orders will keep their coupon snapshot.')">
                                    @csrf @method('DELETE')
                                    <button class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-slate-500">No coupons have been created.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $coupons->links() }}
@endsection
