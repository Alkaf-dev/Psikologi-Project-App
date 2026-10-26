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
        $unread = $items->where('read', false)->count();

        if ($request->boolean('unread')) {
            $items = $items->where('read', false)->values();
        }

        $items = $items->reverse()->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'total' => $items->count(),
                'unread' => $unread,
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
        $items = $notifications->forUser($user);
        $notification = $items->firstWhere('key', $key);

        if ($notification === null) {
            abort(422, 'Notifikasi tidak ditemukan untuk akun ini.');
        }

        $wasUnread = ! ($notification['read'] ?? false);
        $notifications->markAsRead($user, $key);

        return response()->json([
            'message' => 'Notifikasi ditandai sudah dibaca.',
            'unread_count' => $items->where('read', false)->count() - ($wasUnread ? 1 : 0),
        ]);
    }

    public function readAll(NotificationCenter $notifications): JsonResponse
    {
        $user = $this->currentUser();
        $items = $notifications->forUser($user);
        $notifications->markManyAsRead($user, $items->pluck('key')->all());

        return response()->json([
            'message' => 'Semua notifikasi ditandai sudah dibaca.',
            'unread_count' => 0,
        ]);
    }
}
