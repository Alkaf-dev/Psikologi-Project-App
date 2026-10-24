<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\NotificationCenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function index(Request $request, NotificationCenter $notifications): JsonResponse
    {
        $user = $this->currentUser();
        $items = $notifications->forUser($user);

        if ($request->boolean('unread')) {
            $items = $items->where('read', false)->values();
        }

        $items = $items->reverse()->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'total' => $items->count(),
                'unread' => $notifications->unreadCount($user),
            ],
        ]);
    }

    public function read(Request $request, NotificationCenter $notifications): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:191'],
        ]);

        $user = $this->currentUser();
        $key = trim((string) $data['key']);
        $available = $notifications->forUser($user)->contains(
            fn (array $notification): bool => (string) ($notification['key'] ?? '') === $key,
        );

        if (! $available) {
            abort(422, 'Notifikasi tidak ditemukan untuk akun ini.');
        }

        $notifications->markAsRead($user, $key);

        return response()->json([
            'message' => 'Notifikasi ditandai sudah dibaca.',
            'unread_count' => $notifications->unreadCount($user),
        ]);
    }

    public function readAll(NotificationCenter $notifications): JsonResponse
    {
        $user = $this->currentUser();
        $notifications->markAllAsRead($user);

        return response()->json([
            'message' => 'Semua notifikasi ditandai sudah dibaca.',
            'unread_count' => $notifications->unreadCount($user),
        ]);
    }
}
