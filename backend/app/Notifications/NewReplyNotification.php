<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Notifications\Notification;

class NewReplyNotification extends Notification
{
    public function __construct(public Post $post, public int $replyCount) {}

    public function via($notifiable) { return ['database']; }

    public function toDatabase($notifiable)
    {
        return [
            'type'        => 'new_reply',
            'topic_id'    => $this->post->topic_id,
            'group_id'    => $this->post->topic->group_id, 
            'topic_title' => $this->post->topic->title,
            'post_id'     => $this->post->id,
            'from'        => $this->post->author->name,
            'reply_count' => $this->replyCount,
            'message'     => $this->replyCount > 1
                ? "{$this->replyCount} new replies in \"{$this->post->topic->title}\""
                : "{$this->post->author->name} replied to your topic \"{$this->post->topic->title}\"",
        ];
    }
}