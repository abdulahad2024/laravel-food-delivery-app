<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $notifications = NotificationLog::where('user_id', $user->id)
            ->latest()
            ->paginate(20);

        return response()->json($notifications);
    }

    public function adminIndex(Request $request)
    {
        $user = $request->user();

        if (! $user || (! $user->hasRole('admin') && ! $user->hasRole('super_admin'))) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json(
            NotificationLog::with('user')->latest()->paginate(20)
        );
    }
}
