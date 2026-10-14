<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GroupService;
use Illuminate\Http\Request;
use App\Models\Group;
use App\Http\Resources\GroupResource;
use App\Models\Mark;

class GroupController extends Controller
{
    public function index(Request $request, GroupService $groupService)
    {
        return response()->json([
            'my_groups' => $groupService->getUserGroups($request->user()),
            'available_groups' => $groupService->getAvailableGroups($request->user())
        ]);
    }

        public function adminIndex(Request $request)
    {
        $groups = Group::with('creator')
            ->withCount(['members', 'topics'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data'    => GroupResource::collection($groups),
        ]);
    }

        public function manageableIndex(Request $request)
    {
        return $this->adminIndex($request);
    }

    public function join(Request $request, GroupService $groupService)
    {
        $request->validate(['group_id' => 'required|exists:groups,id']);
        $result = $groupService->joinGroup($request->group_id, $request->user());
        return response()->json($result);
    }

    public function store(Request $request, GroupService $groupService)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'topic_title' => 'required|string|max:255',
            'topic_description' => 'required|string|max:255',
        ]);

        try {
            $group = $groupService->createGroupWithTopic($validated, $request->user());
            
            return response()->json([
                'success' => true, 
                'message' => 'Group created successfully',
                'data' => $group
            ], 201);
            
        } catch (\Exception $e) {
            // Log it for your own debugging
            logger($e->getMessage());
            return response()->json(['error' => 'Failed to create group.'], 500);
        }
    }

        public function students($groupId)
    {
        $group = Group::with('members.blacklist')->findOrFail($groupId);

        $data = $group->members->map(function ($user) use ($groupId) {
            $quizMarks = Mark::where('user_id', $user->id)
                ->whereHas('quiz', fn ($q) => $q->where('group_id', $groupId));

            $status = $user->blacklist->status ?? 'ACTIVE';

            return [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'account_status' => $status,
                'warning_count'  => $user->warning_count,
                'quizzes_taken'  => (clone $quizMarks)->count(),
                'average_score'  => (clone $quizMarks)->avg('score'),
                'standing'       => $status === 'SUSPENDED' ? 'Suspended' : 'Good standing',
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function grades($groupId)
    {
        $marks = Mark::with(['student', 'quiz'])
            ->whereHas('quiz', fn ($q) => $q->where('group_id', $groupId))
            ->latest()
            ->get();

        $data = $marks->map(fn ($mark) => [
            'user_id'         => $mark->user_id,
            'student_name'    => $mark->student->name ?? 'Unknown',
            'quiz_id'         => $mark->quiz_id,
            'quiz_title'      => $mark->quiz->title ?? 'Unknown Quiz',
            'score'           => $mark->score,
            'grade'           => match (true) {
                $mark->score >= 90 => 'A',
                $mark->score >= 80 => 'B',
                $mark->score >= 70 => 'C',
                $mark->score >= 60 => 'D',
                default             => 'F',
            },
            'completion_date' => $mark->created_at->toDateTimeString(),
        ]);

        return response()->json(['success' => true, 'data' => $data]);
    }

    // GET /api/groups/{group}/participation-criteria
    public function participationCriteria($groupId)
    {
        $group = Group::findOrFail($groupId);
        $criteria = app(\App\Services\ParticipationService::class)->getCriteria($group);

        return response()->json([
            'success' => true,
            'data'    => array_merge(['group_id' => $group->id], $criteria),
        ]);
    }

    // POST /api/groups/{group}/participation-criteria
    public function updateParticipationCriteria(Request $request, $groupId)
    {
        $validated = $request->validate([
            'points_per_post'            => 'required|integer|min:0|max:100',
            'points_per_quiz_completion' => 'required|integer|min:0|max:100',
        ]);

        $group = Group::findOrFail($groupId);
        $criteria = app(\App\Services\ParticipationService::class)->setCriteria(
            $group,
            $validated['points_per_post'],
            $validated['points_per_quiz_completion']
        );

        return response()->json([
            'success' => true,
            'message' => 'Participation criteria updated.',
            'data'    => $criteria,
        ]);
    }
}