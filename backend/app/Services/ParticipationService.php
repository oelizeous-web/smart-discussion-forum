<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Post;
use App\Models\QuizAttempt;
use App\Models\User;

class ParticipationService
{
    public function getCriteria(Group $group): array
    {
        return [
            'points_per_post'            => $group->points_per_post,
            'points_per_quiz_completion' => $group->points_per_quiz_completion,
        ];
    }

    public function setCriteria(Group $group, int $pointsPerPost, int $pointsPerQuizCompletion): array
    {
        $group->update([
            'points_per_post'            => $pointsPerPost,
            'points_per_quiz_completion' => $pointsPerQuizCompletion,
        ]);

        return $this->getCriteria($group);
    }

    /**
     * [user_id => ['posts_count' => int, 'quizzes_completed' => int, 'participation_score' => int]]
     * for every member of the group, using that group's configured criteria
     * (req #9: "award marks for participation depending on set criteria").
     */
    public function computeForGroup(Group $group): array
    {
        $postCounts = Post::whereHas('topic', fn ($q) => $q->where('group_id', $group->id))
            ->selectRaw('user_id, count(*) as c')
            ->groupBy('user_id')
            ->pluck('c', 'user_id');

        $quizCompletions = QuizAttempt::whereHas('quiz', fn ($q) => $q->where('group_id', $group->id))
            ->whereNotNull('submitted_at')
            ->selectRaw('user_id, count(*) as c')
            ->groupBy('user_id')
            ->pluck('c', 'user_id');

        $memberIds = $group->members()->pluck('users.id');

        $result = [];
        foreach ($memberIds as $userId) {
            $posts = (int) ($postCounts->get($userId) ?? 0);
            $quizzes = (int) ($quizCompletions->get($userId) ?? 0);
            $result[$userId] = [
                'posts_count'         => $posts,
                'quizzes_completed'   => $quizzes,
            'participation_score' => $posts * ($group->points_per_post ?? 1)
                + $quizzes * ($group->points_per_quiz_completion ?? 5)
            ];
        }

        return $result;
    }

    public function computeForUser(User $user, Group $group): array
    {
        return $this->computeForGroup($group)[$user->id]
            ?? ['posts_count' => 0, 'quizzes_completed' => 0, 'participation_score' => 0];
    }
}