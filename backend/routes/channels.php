<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Topic;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});


Broadcast::channel('topic.{topicId}', function ($user, $topicId) {
    $topic = Topic::find($topicId);
    
    // Check if the user belongs to the group that owns this topic
    return $user->groups()->where('group_id', $topic->group_id)->exists();
});