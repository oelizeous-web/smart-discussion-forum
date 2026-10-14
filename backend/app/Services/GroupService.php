<?php
namespace App\Services;

use App\Models\Group;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GroupService
{
    public function joinGroup($groupId, User $user)
    {
        $user->groups()->syncWithoutDetaching([$groupId]);
        return ['success' => true, 'message' => 'Successfully joined the group.'];
    }

    public function getUserGroups(User $user)
    {
        $groups = Group::where('user_id', $user->id)
            ->orWhereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->with(['topics.participants' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }])
            ->latest()
            ->get();

        foreach ($groups as $group) {
            foreach ($group->topics as $topic) {
                $membership = $topic->participants->first();
                $participantStatus = $membership->pivot->status ?? null;

                $topic->participant_status = $participantStatus;
                $topic->can_access = !$topic->is_private
                    || $topic->user_id === $user->id
                    || $participantStatus === 'approved';

                $topic->makeHidden('participants');
            }
        }

        return $groups;
    }

    public function getAvailableGroups(User $user)
    {
        return Group::where('user_id', '!=', $user->id)
            ->whereDoesntHave('members', fn ($q) => $q->where('user_id', $user->id))
            ->take(10)
            ->get();
    }

    public function createGroupWithTopic(array $data, User $user)
    {
        return DB::transaction(function () use ($data, $user) {
            $group = Group::create([
                'name'    => $data['name'],
                'user_id' => $user->id,
            ]);

            Topic::create([
                'group_id'    => $group->id,
                'title'       => $data['topic_title'],
                'description' => $data['topic_description'],
                'user_id'     => $user->id,
            ]);

            if (method_exists($group, 'members')) {
                $group->members()->attach($user->id);
            }

            return $group;
        });
    }
}