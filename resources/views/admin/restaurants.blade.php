@extends('admin.layout')

@section('title', 'Restaurants')

@section('content')
    <header>
        <h1 class="text-3xl font-bold tracking-tight text-slate-950">Restaurants</h1>
        <p class="mt-2 text-slate-500">Review applications and manage restaurant profiles without deleting order history.</p>
    </header>

    <details class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <summary class="cursor-pointer list-none px-5 py-4 font-semibold text-blue-700">＋ Add a restaurant</summary>
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.restaurants.store') }}" class="grid gap-4 border-t border-slate-100 p-5 sm:grid-cols-2 xl:grid-cols-3">
            @csrf
            <label class="text-sm font-medium text-slate-700">Restaurant name<input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Owner<select name="owner_id" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2"><option value="">Choose owner</option>@foreach ($owners as $owner)<option value="{{ $owner->id }}" @selected(old('owner_id') == $owner->id)>{{ $owner->name }} · {{ $owner->email }}</option>@endforeach</select></label>
            <label class="text-sm font-medium text-slate-700">Email<input name="email" type="email" value="{{ old('email') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Phone<input name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Delivery fee<input name="delivery_fee" type="number" min="0" step="0.01" value="{{ old('delivery_fee', '0.00') }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Minimum order<input name="minimum_order_value" type="number" min="0" step="0.01" value="{{ old('minimum_order_value', '0.00') }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Approval<select name="approval_status" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2"><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select></label>
            <label class="text-sm font-medium text-slate-700">Operating status<select name="status" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2"><option value="draft">Draft</option><option value="open">Open</option><option value="closed">Closed</option></select></label>
            <label class="text-sm font-medium text-slate-700">Profile image<input name="profile_image" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Latitude<input name="latitude" type="number" min="-90" max="90" step="any" value="{{ old('latitude') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Longitude<input name="longitude" type="number" min="-180" max="180" step="any" value="{{ old('longitude') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700 sm:col-span-2 xl:col-span-3">Description<textarea name="description" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('description') }}</textarea></label>
            <div class="sm:col-span-2 xl:col-span-3"><button class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Create restaurant</button></div>
        </form>
    </details>

    <form method="GET" action="{{ route('admin.restaurants') }}" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-2 xl:grid-cols-4">
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Restaurant or owner" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <select name="approval_status" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">All approvals</option>@foreach (['pending', 'approved', 'rejected'] as $status)<option value="{{ $status }}" @selected(($filters['approval_status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
        <select name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">All operating statuses</option>@foreach (['open', 'closed', 'draft'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
        <div class="flex gap-2"><button class="flex-1 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Filter</button><a href="{{ route('admin.restaurants') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Reset</a></div>
    </form>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Restaurant</th><th class="px-4 py-3">Owner</th><th class="px-4 py-3">Activity</th><th class="px-4 py-3">Profile and status</th><th class="px-4 py-3">Save</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($restaurants as $restaurant)
                        <tr class="align-top">
                            <td class="whitespace-nowrap px-4 py-4">
                                @if ($restaurant->profile_image_url)
                                    <img src="{{ $restaurant->profile_image_url }}" alt="{{ $restaurant->name }} profile image" class="mb-2 size-16 rounded-xl object-cover">
                                @endif
                                <div class="font-semibold text-slate-900">{{ $restaurant->name }}</div><div class="mt-1 text-xs text-slate-500">#{{ $restaurant->id }} · {{ $restaurant->slug }}</div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4"><div>{{ $restaurant->owner?->name ?? 'Unknown' }}</div><div class="mt-1 text-xs text-slate-500">{{ $restaurant->owner?->email }}</div></td>
                            <td class="whitespace-nowrap px-4 py-4 text-slate-600">{{ $restaurant->foods_count }} menu items<br>{{ $restaurant->orders_count }} orders</td>
                            <td class="min-w-[31rem] px-4 py-4">
                                <form id="restaurant-{{ $restaurant->id }}" method="POST" enctype="multipart/form-data" action="{{ route('admin.restaurants.update', $restaurant) }}" class="grid gap-2 sm:grid-cols-2">
                                    @csrf @method('PUT')
                                    <select name="owner_id" aria-label="Restaurant owner" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5">@foreach ($owners as $owner)<option value="{{ $owner->id }}" @selected($restaurant->owner_id === $owner->id)>{{ $owner->name }} · {{ $owner->email }}</option>@endforeach</select>
                                    <input name="name" value="{{ $restaurant->name }}" required aria-label="Restaurant name" class="rounded-lg border border-slate-300 px-2 py-1.5">
                                    <input name="phone" value="{{ $restaurant->phone }}" aria-label="Phone" placeholder="Phone" class="rounded-lg border border-slate-300 px-2 py-1.5">
                                    <input name="email" type="email" value="{{ $restaurant->email }}" aria-label="Email" placeholder="Email" class="rounded-lg border border-slate-300 px-2 py-1.5">
                                    <select name="approval_status" aria-label="Approval" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5">@foreach (['pending', 'approved', 'rejected'] as $status)<option value="{{ $status }}" @selected($restaurant->approval_status === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
                                    <select name="status" aria-label="Operating status" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5">@foreach (['open', 'closed', 'draft'] as $status)<option value="{{ $status }}" @selected($restaurant->status === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
                                    <select name="is_active" aria-label="Active" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5"><option value="1" @selected($restaurant->is_active)>Enabled</option><option value="0" @selected(! $restaurant->is_active)>Disabled</option></select>
                                    <input name="delivery_fee" type="number" min="0" step="0.01" value="{{ $restaurant->delivery_fee }}" required aria-label="Delivery fee" class="rounded-lg border border-slate-300 px-2 py-1.5">
                                    <input name="minimum_order_value" type="number" min="0" step="0.01" value="{{ $restaurant->minimum_order_value }}" required aria-label="Minimum order" class="rounded-lg border border-slate-300 px-2 py-1.5">
                                    <textarea name="description" rows="2" aria-label="Description" placeholder="Description" class="rounded-lg border border-slate-300 px-2 py-1.5 sm:col-span-2">{{ $restaurant->description }}</textarea>
                                    <input name="latitude" type="number" min="-90" max="90" step="any" value="{{ $restaurant->latitude }}" aria-label="Latitude" placeholder="Latitude" class="rounded-lg border border-slate-300 px-2 py-1.5">
                                    <input name="longitude" type="number" min="-180" max="180" step="any" value="{{ $restaurant->longitude }}" aria-label="Longitude" placeholder="Longitude" class="rounded-lg border border-slate-300 px-2 py-1.5">
                                    <input name="profile_image" type="file" accept="image/jpeg,image/png,image/webp" aria-label="Profile image" class="rounded-lg border border-slate-300 px-2 py-1.5 sm:col-span-2">
                                    @if ($restaurant->profile_image)
                                        <label class="flex items-center gap-2 text-xs text-slate-600 sm:col-span-2"><input name="remove_profile_image" type="checkbox" value="1" class="rounded border-slate-300">Remove current profile image</label>
                                    @endif
                                </form>
                            </td>
                            <td class="px-4 py-4"><button form="restaurant-{{ $restaurant->id }}" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">Save</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-slate-500">No restaurants match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $restaurants->links() }}
@endsection
