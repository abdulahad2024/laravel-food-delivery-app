@extends('admin.layout')

@section('title', 'Users')

@section('content')
    <header>
        <h1 class="text-3xl font-bold tracking-tight text-slate-950">Users</h1>
        <p class="mt-2 text-slate-500">Search accounts, manage access and assign platform roles.</p>
    </header>

    <details class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <summary class="cursor-pointer list-none px-5 py-4 font-semibold text-blue-700">＋ Add a user</summary>
        <form method="POST" action="{{ route('admin.users.store') }}" class="grid gap-4 border-t border-slate-100 p-5 sm:grid-cols-2 xl:grid-cols-3">
            @csrf
            <label class="text-sm font-medium text-slate-700">Full name<input name="name" value="{{ old('name') }}" required maxlength="255" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Email<input name="email" type="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Phone<input name="phone" value="{{ old('phone') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Temporary password<input name="password" type="password" minlength="8" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
            <label class="text-sm font-medium text-slate-700">Role<select name="role" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2">@foreach ($roles as $role)<option value="{{ $role }}">{{ ucfirst(str_replace('_', ' ', $role)) }}</option>@endforeach</select></label>
            <label class="text-sm font-medium text-slate-700">Account status<select name="status" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2"><option value="active">Active</option><option value="inactive">Inactive</option><option value="blocked">Blocked</option></select></label>
            <div class="sm:col-span-2 xl:col-span-3"><button class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Create user</button></div>
        </form>
    </details>

    <form method="GET" action="{{ route('admin.users') }}" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-2 xl:grid-cols-4">
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, email or phone" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <select name="role" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">All roles</option>@foreach ($roles as $role)<option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ ucfirst(str_replace('_', ' ', $role)) }}</option>@endforeach</select>
        <select name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"><option value="">All account statuses</option>@foreach (['active', 'inactive', 'blocked'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
        <div class="flex gap-2"><button class="flex-1 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Filter</button><a href="{{ route('admin.users') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Reset</a></div>
    </form>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-4 py-3">User</th><th class="px-4 py-3">Phone</th><th class="px-4 py-3">Created</th><th class="px-4 py-3">Access and status</th><th class="px-4 py-3">Save</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        @php($canEdit = auth()->user()->hasRole('super_admin') || (! $user->hasRole('super_admin') && ! $user->hasRole('admin')))
                        <tr class="align-top">
                            <td class="px-4 py-4"><div class="font-semibold text-slate-900">{{ $user->name }}</div><div class="mt-1 text-slate-500">{{ $user->email }}</div></td>
                            <td class="whitespace-nowrap px-4 py-4 text-slate-600">{{ $user->phone ?: '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-slate-600">{{ $user->created_at?->format('M j, Y') }}</td>
                            <td colspan="2" class="min-w-[24rem] px-4 py-4">
                                @if ($canEdit)
                                    <form id="user-{{ $user->id }}" method="POST" action="{{ route('admin.users.update', $user) }}" class="grid gap-2 sm:grid-cols-2">
                                        @csrf @method('PUT')
                                        <input name="name" value="{{ $user->name }}" required aria-label="Name" class="rounded-lg border border-slate-300 px-2 py-1.5">
                                        <input name="email" type="email" value="{{ $user->email }}" required aria-label="Email" class="rounded-lg border border-slate-300 px-2 py-1.5">
                                        <input name="phone" value="{{ $user->phone }}" aria-label="Phone" placeholder="Phone" class="rounded-lg border border-slate-300 px-2 py-1.5">
                                        <input name="password" type="password" minlength="8" autocomplete="new-password" aria-label="New password" placeholder="Keep current password" class="rounded-lg border border-slate-300 px-2 py-1.5">
                                        <select name="role" aria-label="Role" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5">@foreach ($roles as $role)<option value="{{ $role }}" @selected($user->hasRole($role))>{{ ucfirst(str_replace('_', ' ', $role)) }}</option>@endforeach</select>
                                        <select name="status" aria-label="Status" class="rounded-lg border border-slate-300 bg-white px-2 py-1.5">@foreach (['active', 'inactive', 'blocked'] as $status)<option value="{{ $status }}" @selected($user->status === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
                                    </form>
                                @else
                                    <span class="text-sm text-slate-500">Protected administrator</span>
                                    <div class="mt-2 flex flex-wrap gap-1">@foreach ($user->roles as $role)<span class="rounded-full bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">{{ $role->display_name ?? $role->name }}</span>@endforeach</div>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                @if ($canEdit)<button form="user-{{ $user->id }}" class="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">Save</button>@endif
                                <div class="mt-2 flex flex-wrap gap-1">@foreach ($user->roles as $role)<span class="rounded-full bg-slate-100 px-2 py-1 text-xs text-slate-600">{{ $role->name }}</span>@endforeach</div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-slate-500">No users match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $users->links() }}
@endsection
