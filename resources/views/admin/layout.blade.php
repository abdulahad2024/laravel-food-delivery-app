<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Admin Dashboard') - FoodFlow</title>
        <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    </head>
    <body class="bg-slate-100 text-slate-800 antialiased">
        <aside class="bg-slate-950 text-white md:fixed md:inset-y-0 md:left-0 md:w-64 md:overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4 md:block md:p-6">
                <a href="{{ route('admin.dashboard') }}" class="text-2xl font-bold tracking-tight">FoodFlow</a>
                <span class="hidden text-xs text-slate-400 md:block md:pt-2">Operations Console</span>
                <form method="POST" action="{{ route('logout') }}" class="md:hidden">
                    @csrf
                    <button type="submit" class="rounded-lg border border-slate-700 px-3 py-2 text-sm text-slate-200 hover:bg-slate-800">Logout</button>
                </form>
            </div>

            <nav class="flex gap-1 overflow-x-auto p-3 md:block md:space-y-1 md:p-4">
                @php
                    $navigation = [
                        ['route' => 'admin.dashboard', 'label' => 'Dashboard'],
                        ['route' => 'admin.users', 'label' => 'Users'],
                        ['route' => 'admin.restaurant-users', 'label' => 'Restaurant accounts'],
                        ['route' => 'admin.restaurants', 'label' => 'Restaurants'],
                        ['route' => 'admin.menu', 'label' => 'Menu'],
                        ['route' => 'admin.orders', 'label' => 'Orders'],
                        ['route' => 'admin.payments', 'label' => 'Payments'],
                        ['route' => 'admin.refunds', 'label' => 'Refunds'],
                        ['route' => 'admin.coupons', 'label' => 'Coupons'],
                        ['route' => 'admin.settlements', 'label' => 'Settlements'],
                        ['route' => 'admin.payout-ledger', 'label' => 'Payout ledger'],
                    ];
                @endphp

                @foreach ($navigation as $item)
                    <a href="{{ route($item['route']) }}"
                        class="shrink-0 rounded-lg px-3 py-2 text-sm font-medium transition
                            {{ request()->routeIs($item['route'].'*') ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="hidden border-t border-slate-800 p-4 md:absolute md:bottom-0 md:left-0 md:right-0 md:block">
                <div class="mb-3 truncate text-xs text-slate-400">{{ auth()->user()->name }} · {{ auth()->user()->email }}</div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full rounded-lg border border-slate-700 px-3 py-2 text-sm font-medium text-slate-200 transition hover:bg-slate-800">
                        Sign out
                    </button>
                </form>
            </div>
        </aside>

        <main class="min-h-screen p-4 sm:p-6 lg:p-8 md:ml-64">
            <div class="mx-auto max-w-7xl space-y-5">
                @if (session('success'))
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800" role="status">
                        {{ session('success') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                        <p class="font-semibold">Please check the form and try again.</p>
                        <ul class="mt-2 list-inside list-disc space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </body>
</html>
