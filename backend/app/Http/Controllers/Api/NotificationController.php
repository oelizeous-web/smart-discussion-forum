<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->latest()->limit(30)->get();

        return response()->json([
            'success'      => true,
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'data' => $notifications->map(fn ($n) => [
                'id'         => $n->id,
                'topic_id'   => $n->data['topic_id'] ?? null,
                'group_id'   => $n->data['group_id'] ?? null, 
                'post_id'    => $n->data['post_id'] ?? null,
                'message'    => $n->data['message'] ?? '',
                'read'       => $n->read_at !== null,
                'created_at' => $n->created_at->toDateTimeString(),
            ]),
        ]);
    }

    public function markRead(Request $request, $id)
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();
        return response()->json(['success' => true]);
    }
}