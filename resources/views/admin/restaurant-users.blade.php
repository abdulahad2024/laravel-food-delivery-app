@extends('admin.layout')

@section('title', 'Restaurant accounts')

@section('content')
    <header>
        <h1 class="text-3xl font-bold tracking-tight text-slate-950">Restaurant accounts</h1>
        <p class="mt-2 text-slate-500">Manage owner logins independently from customer and platform accounts.</p>
    </header>

    <details class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <summary class="cursor-pointer list-none px-5 py-4 font-semibold text-blue-700">＋ Create restaurant account</summary>
        <form method="POST" action="{{ route('admin.restaurant-users.store') }}" class="grid gap-4 border-t border-slate-100 p-5 sm:grid-cols-2 xl:grid-cols-3">
            @csrf
            <label class="text-sm font-medium text-slate-700">Account name<input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Email<input name="email" type="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Phone<input name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Temporary password<input name="password" type="password" minlength="8" autocomplete="new-password" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Account status<select name="status" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2"><option value="active">Active</option><option value="inactive">Inactive</option><option value="blocked">Blocked</option></select></label>
            <div class="flex items-end"><button class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Create account</button></div>
        </form>
    </details>

    <form method="GET" action="{{ route('admin.restaurant-users') }}" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-3">
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, email or phone" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <select name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">All statuses</option>@foreach (['active', 'inactive', 'blocked'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
        <div class="flex gap-2"><button class="flex-1 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Filter</button><a href="{{ route('admin.restaurant-users') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Reset</a></div>
    </form>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Account</th><th class="px-4 py-3">Restaurants</th><th class="px-4 py-3">Update account</th><th class="px-4 py-3">Save</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($restaurantUsers as $restaurantUser)
                        <tr class="align-top">
                            <td class="whitespace-nowrap px-4 py-4"><div class="font-semibold text-slate-900">{{ $restaurantUser->name }}</div><div class="mt-1 text-xs text-slate-500">{{ $restaurantUser->email }}</div></td>
                            <td class="whitespace-nowrap px-4 py-4"><div>{{ $restaurantUser->restaurants_count }} restaurant(s)</div><div class="mt-1 text-xs text-slate-500">{{ ucfirst($restaurantUser->status) }}</div></td>
                            <td class="min-w-[30rem] px-4 py-4">
                                <form id="restaurant-user-{{ $restaurantUser->id }}" method="POST" action="{{ route('admin.restaurant-users.update', $restaurantUser) }}" class="grid gap-2 sm:grid-cols-2">
                                    @csrf @method('PUT')
                                    <input name="name" value="{{ $restaurantUser->name }}" required aria-label="Name" class="rounded-lg border border-slate-300 px-2 py-1.5">
                                    <input name="email" type="email" value="{{ $restaurantUser->email }}" required aria-label="Email" class="rounded-lg border border-slate-300 px-2 py-1.5">
                                    <input name="phone" value="{{ $restaurantUser->phone }}" aria-label="Phone" placeholder="Phone" class="rounded-lg border border-slate-300 px-2 py-1.5">
                                    <input name="password" type="password" minlength="8" autocomplete="new-password" aria-label="New password" placeholder="Keep current password" class="rounded-lg border border-slate-300 px-2 py-1.5">
                                    <select name="status" aria-label="Status" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5">@foreach (['active', 'inactive', 'blocked'] as $status)<option value="{{ $status }}" @selected($restaurantUser->status === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
                                </form>
                            </td>
                            <td class="px-4 py-4"><button form="restaurant-user-{{ $restaurantUser->id }}" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">Save</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-12 text-center text-slate-500">No restaurant accounts match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $restaurantUsers->links() }}
@endsection
