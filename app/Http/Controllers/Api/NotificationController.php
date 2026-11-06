<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TaskNotificationUrlResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);

        $page = $user->notifications()->latest()->paginate($perPage);

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'data' => collect($page->items())->map(fn ($n) => [
                'id' => $n->id,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
                'data' => $n->data,
                'web_url' => $this->resolveUrl($user, $n->data),
            ])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->json(['message' => 'Marked as read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    private function resolveUrl(\App\Models\User $user, array $data): ?string
    {
        try {
            return TaskNotificationUrlResolver::forNotificationData($user, $data);
        } catch (\Throwable) {
            return null;
        }
    }
}
