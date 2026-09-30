<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = $request->user()->notifications()->latest()->orderByDesc('id')->paginate(20);

        return response()->json([
            'data' => collect($items->items())->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->data['title'] ?? 'Notification',
                'message' => $item->data['message'] ?? '',
                'action_path' => $this->localPath($item->data['action_path'] ?? null),
                'read' => $item->read_at !== null,
                'created_at' => $item->created_at?->toISOString(),
            ]),
            'next_page' => $items->hasMorePages() ? $items->currentPage() + 1 : null,
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return response()->json(['unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    private function localPath(mixed $path): ?string
    {
        // Destination routes retain their existing permission and scope middleware.
        return is_string($path) && preg_match('~^/(?!/)[^\\\\\x00-\x20]*$~D', $path) ? $path : null;
    }
}
