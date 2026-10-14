<?php
namespace App\Services;

use App\Models\Topic;
use App\Models\User;

class ModerationService
{
    /** Topic-scoped: a topic owner (or admin) warning a participant in that thread. */
    public function warnParticipant($topicId, $targetUserId, User $actingUser): array
    {
        $topic = Topic::findOrFail($topicId);

        if ($topic->user_id !== $actingUser->id && !$actingUser->hasRole('admin')) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        $userToWarn = User::findOrFail($targetUserId);
        $userToWarn->issueWarning();

        return ['success' => true, 'data' => $userToWarn->fresh('blacklist')];
    }

    /** Platform-scoped: admin panel, no topic context — route middleware already gates role:admin. */
    public function warnUser(int $userId): User
    {
        $user = User::findOrFail($userId);
        $user->issueWarning();
        return $user;
    }

    public function suspendUser(int $userId): User
    {
        $user = User::findOrFail($userId);
        $user->manualSuspend();
        return $user;
    }

    public function reinstateUser(int $userId): User
    {
        $user = User::findOrFail($userId);
        $user->pardon();
        return $user;
    }

    /** Called after a compliant post — clears warnings the same way an admin pardon would. */
    public function clearWarningsIfCompliant(User $user): void
    {
        if ($user->warning_count > 0 || ($user->blacklist && $user->blacklist->warning_count > 0)) {
            $user->pardon();
        }
    }
}