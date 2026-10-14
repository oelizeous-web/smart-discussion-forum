<?php
namespace App\Services;

use App\Models\Topic;
use App\Models\User;

class TopicService
{
    public function createTopic(array $data, User $user)
    {
        $topic = Topic::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'group_id' => $data['group_id'],
            'user_id' => $user->id,
            'is_private' => $data['is_private'] ?? false,
        ]);

        // Automatically approve the creator
        $topic->participants()->attach($user->id, ['status' => 'approved']);

        return $topic;
    }

    public function requestAccess($topicId, User $user)
    {
        $topic = Topic::findOrFail($topicId);
        $topic->participants()->syncWithoutDetaching([$user->id => ['status' => 'pending']]);
        return true;
    }

    public function approveParticipant($topicId, $targetUserId, User $actingUser)
    {
        $topic = Topic::findOrFail($topicId);
        
        if ($topic->user_id === $actingUser->id || $actingUser->hasRole('admin')) {
            $topic->participants()->updateExistingPivot($targetUserId, ['status' => 'approved']);
            return true;
        }
        return false;
    }
}