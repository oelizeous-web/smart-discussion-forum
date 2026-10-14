<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Topic;
use App\Notifications\NewReplyNotification;
use Illuminate\Support\Facades\Auth;

class PostService
{
    public function getPostsByTopic(int $topicId)
        {
            return Post::query()
                ->where('topic_id', $topicId)
                ->select('id', 'topic_id', 'user_id', 'content', 'created_at') 
                ->with('author:id,name,avatar,bio')
                ->latest()
                ->take(50)
                ->get()
                ->reverse(); 
        }

    public function createPost(array $data, int $topicId): Post
    {
        $post = Post::create([
            'user_id'  => Auth::id(),
            'topic_id' => $topicId,
            'content'  => $data['content'],
            'attachment_path' => $data['attachment_path'] ?? null,
        ]);

        $topic = Topic::find($topicId);
        $topic?->increment('post_count');

        if ($topic) {
            $this->notifyOfReply($topic, $post);
        }

        return $post;
    }

    protected function notifyOfReply(Topic $topic, Post $post): void
    {
       $asker = $topic->author;
        if (!$asker || $asker->id === $post->user_id) {
            return; // don't notify people replying to their own topic
        }

        // Deliberately filtered in PHP rather than a JSON-path DB query — the
        // `notifications.data` column is plain text, not a typed JSON column,
        // and this keeps it portable across SQLite/MySQL/Postgres without
        // relying on driver-specific JSON operators. Unread counts per user
        // are small, so this is cheap.
        $existing = $asker->unreadNotifications()
            ->where('type', NewReplyNotification::class)
            ->get()
            ->first(fn ($n) => ($n->data['topic_id'] ?? null) === $topic->id);

        if ($existing) {
            $data = $existing->data;
            $data['post_id']     = $post->id;
            $data['from']        = $post->author->name;
            $data['reply_count'] = ($data['reply_count'] ?? 1) + 1;
            $data['message']     = "{$data['reply_count']} new replies in \"{$topic->title}\"";
            $existing->forceFill(['data' => $data])->save();
        } else {
            $asker->notify(new NewReplyNotification($post, 1));
        }
    }


    
}