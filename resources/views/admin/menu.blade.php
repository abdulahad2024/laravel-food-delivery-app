@extends('admin.layout')

@section('title', 'Menu')

@section('content')
    <header>
        <h1 class="text-3xl font-bold tracking-tight text-slate-950">Menu management</h1>
        <p class="mt-2 text-slate-500">Create menu items, update prices and control availability.</p>
    </header>

    <details class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <summary class="cursor-pointer list-none px-5 py-4 font-semibold text-blue-700">＋ Add a menu item</summary>
        <form method="POST" action="{{ route('admin.menu.store') }}" class="grid gap-4 border-t border-slate-100 p-5 sm:grid-cols-2 xl:grid-cols-3">
            @csrf
            <label class="text-sm font-medium text-slate-700">Restaurant<select name="restaurant_id" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2"><option value="">Choose restaurant</option>@foreach ($restaurants as $restaurant)<option value="{{ $restaurant->id }}" @selected(old('restaurant_id') == $restaurant->id)>{{ $restaurant->name }}</option>@endforeach</select></label>
            <label class="text-sm font-medium text-slate-700">Category<select name="category_id" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2"><option value="">No category</option>@foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->restaurant?->name }} · {{ $category->name }}</option>@endforeach</select></label>
            <label class="text-sm font-medium text-slate-700">Item name<input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Price<input name="price" type="number" min="0" step="0.01" value="{{ old('price') }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Preparation minutes<input name="preparation_time_minutes" type="number" min="1" max="600" value="{{ old('preparation_time_minutes', 15) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Image URL<input name="image_url" type="url" value="{{ old('image_url') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700 sm:col-span-2 xl:col-span-3">Description<textarea name="description" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('description') }}</textarea></label>
            <div class="sm:col-span-2 xl:col-span-3"><button class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Create menu item</button></div>
        </form>
    </details>

    <form method="GET" action="{{ route('admin.menu') }}" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-2 xl:grid-cols-4">
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Menu item name" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <select name="restaurant_id" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">All restaurants</option>@foreach ($restaurants as $restaurant)<option value="{{ $restaurant->id }}" @selected(($filters['restaurant_id'] ?? '') == $restaurant->id)>{{ $restaurant->name }}</option>@endforeach</select>
        <select name="availability" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">All availability</option><option value="available" @selected(($filters['availability'] ?? '') === 'available')>Available</option><option value="unavailable" @selected(($filters['availability'] ?? '') === 'unavailable')>Unavailable</option></select>
        <div class="flex gap-2"><button class="flex-1 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Filter</button><a href="{{ route('admin.menu') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Reset</a></div>
    </form>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">Item</th><th class="px-4 py-3">Restaurant / category</th><th class="px-4 py-3">Price and availability</th><th class="px-4 py-3">Save</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($foods as $food)
                        <tr class="align-top">
                            <td class="px-4 py-4"><div class="font-semibold text-slate-900">{{ $food->name }}</div><div class="mt-1 max-w-sm text-xs text-slate-500">{{ $food->description }}</div></td>
                            <td class="whitespace-nowrap px-4 py-4">{{ $food->restaurant?->name }}<div class="mt-1 text-xs text-slate-500">{{ $food->category?->name ?? 'Uncategorised' }}</div></td>
                            <td class="min-w-[18rem] px-4 py-4">
                                <form id="food-{{ $food->id }}" method="POST" action="{{ route('admin.menu.update', $food) }}" class="grid gap-2 sm:grid-cols-3">
                                    @csrf @method('PUT')
                                    <input name="price" type="number" min="0" step="0.01" value="{{ $food->price }}" required aria-label="Price" class="rounded-lg border border-slate-300 px-2 py-1.5">
                                    <select name="is_available" aria-label="Availability" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5"><option value="1" @selected($food->is_available)>Available</option><option value="0" @selected(! $food->is_available)>Hidden</option></select>
                                    <select name="is_featured" aria-label="Featured" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5"><option value="1" @selected($food->is_featured)>Featured</option><option value="0" @selected(! $food->is_featured)>Standard</option></select>
                                </form>
                            </td>
                            <td class="px-4 py-4"><button form="food-{{ $food->id }}" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">Save</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-12 text-center text-slate-500">No menu items match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $foods->links() }}
@endsection
