<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * FOR THE JAVA TEAM.
 *
 * Hitting GET /api (no auth required) returns this JSON map of every
 * available endpoint: method, url, auth/role requirements, and the
 * expected request body. Use this to wire up your HTTP client instead
 * of guessing field names from the PHP source.
 *
 * This file is self-contained — it does not read the router, so if you
 * add/remove a route in routes/api.php, update the matching entry below
 * by hand to keep it accurate.
 */
class ApiDocsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'name'    => 'Smart Discussion Forum API',
            'base_url' => url('/api'),
            'auth'    => 'Bearer token via Laravel Sanctum. Send: Authorization: Bearer {access_token} (obtained from /login or /register).',
            'notes'   => [
                'auth:sanctum'    => 'Requires a valid Bearer token.',
                'role:admin'      => 'Requires the authenticated user to have the admin role.',
                'role:lecturer|admin' => 'Requires the authenticated user to have the lecturer or admin role.',
                'check.blacklist' => 'Blocks suspended/blacklisted users even if their token is valid.',
            ],
            'endpoints' => [

                // ---------------- Public ----------------
                [
                    'method' => 'POST', 'url' => '/api/register', 'auth' => 'public',
                    'description' => 'Register a new student account and receive a token.',
                    'body' => [
                        'name' => 'string, required',
                        'email' => 'string, required, valid email, unique',
                        'password' => 'string, required, min 8, must match password_confirmation',
                        'password_confirmation' => 'string, required',
                        'agreed_to_rules' => 'boolean, required, must be true',
                    ],
                    'response' => ['access_token' => 'string', 'token_type' => 'bearer', 'user' => ['id','name','email','roles']],
                ],
                [
                    'method' => 'POST', 'url' => '/api/login', 'auth' => 'public',
                    'description' => 'Log in and receive a token. Fails with 403 if the account is suspended.',
                    'body' => [
                        'email' => 'string, required, valid email',
                        'password' => 'string, required',
                    ],
                    'response' => ['access_token' => 'string', 'token_type' => 'bearer', 'user' => ['id','name','email','roles']],
                ],

                // ---------------- Authenticated: Admin only ----------------
                [
                    'method' => 'POST', 'url' => '/api/admin/register-lecturer', 'auth' => 'auth:sanctum, role:admin',
                    'description' => 'Admin creates a lecturer account.',
                    'body' => [
                        'name' => 'string, required',
                        'email' => 'string, required, valid email, unique',
                        'password' => 'string, required, min 8, must match password_confirmation',
                        'password_confirmation' => 'string, required',
                        'agreed_to_rules' => 'boolean, required, must be true',
                    ],
                ],
                [
                    'method' => 'GET', 'url' => '/api/admin/groups', 'auth' => 'auth:sanctum, role:admin',
                    'description' => 'List all groups (admin view).',
                ],

                // ---------------- Authenticated: Lecturer or Admin ----------------
                [
                    'method' => 'POST', 'url' => '/api/groups/{group}/quizzes', 'auth' => 'auth:sanctum, role:lecturer|admin',
                    'description' => 'Create a quiz with its questions inside a group.',
                    'url_params' => ['group' => 'integer, the group ID'],
                    'body' => [
                        'title' => 'string, required, max 100',
                        'description' => 'string, required',
                        'time_limit' => 'integer, required, minutes, min 1',
                        'status' => 'string, required, one of: DRAFT, PUBLISHED',
                        'start_time' => 'date, nullable',
                        'auto_submit' => 'boolean, optional',
                        'questions' => 'array, required, min 1 item',
                        'questions.*.text' => 'string, required',
                        'questions.*.points' => 'integer, required, min 1',
                        'questions.*.correct_answer' => 'string, required, one of: A, B, C, D',
                        'questions.*.options' => 'object, required',
                        'questions.*.options.A' => 'string, required',
                        'questions.*.options.B' => 'string, required',
                        'questions.*.options.C' => 'string, required',
                        'questions.*.options.D' => 'string, required',
                    ],
                ],
                [
                    'method' => 'GET', 'url' => '/api/quizzes/{quiz}/report', 'auth' => 'auth:sanctum, role:lecturer|admin',
                    'description' => 'Performance report for a quiz. Returns 403 until the quiz time limit has globally elapsed.',
                    'url_params' => ['quiz' => 'integer, the quiz ID'],
                ],

                // ---------------- Authenticated: any logged-in user ----------------
                [
                    'method' => 'POST', 'url' => '/api/groups/join', 'auth' => 'auth:sanctum',
                    'description' => 'Join an existing group.',
                    'body' => ['group_id' => 'integer, required, must exist in groups table'],
                ],
                [
                    'method' => 'GET', 'url' => '/api/groups', 'auth' => 'auth:sanctum',
                    'description' => 'List the current user\'s groups and groups available to join.',
                    'response' => ['my_groups' => 'array', 'available_groups' => 'array'],
                ],
                [
                    'method' => 'POST', 'url' => '/api/groups', 'auth' => 'auth:sanctum',
                    'description' => 'Create a new group, plus its first topic.',
                    'body' => [
                        'name' => 'string, required, max 255',
                        'topic_title' => 'string, required, max 255',
                        'topic_description' => 'string, required, max 255',
                    ],
                ],
                [
                    'method' => 'POST', 'url' => '/api/logout', 'auth' => 'auth:sanctum',
                    'description' => 'Revoke the current access token.',
                ],

                // ---------------- Authenticated + not blacklisted ----------------
                [
                    'method' => 'GET', 'url' => '/api/groups/{group}/topics', 'auth' => 'auth:sanctum, check.blacklist',
                    'description' => 'List topics in a group.',
                    'url_params' => ['group' => 'integer, the group ID'],
                ],
                [
                    'method' => 'POST', 'url' => '/api/groups/{group}/topics', 'auth' => 'auth:sanctum, check.blacklist',
                    'description' => 'Create a topic inside a group.',
                    'url_params' => ['group' => 'integer, the group ID'],
                    'body' => [
                        'title' => 'string, required, min 3, max 255',
                        'description' => 'string, nullable, max 1000',
                        'group_id' => 'integer, required, must exist in groups table',
                        'is_private' => 'boolean, optional',
                    ],
                ],
                [
                    'method' => 'POST', 'url' => '/api/topics/request-access', 'auth' => 'auth:sanctum, check.blacklist',
                    'description' => 'Request access to a private topic.',
                    'body' => ['topic_id' => 'integer, required, must exist in topics table'],
                ],
                [
                    'method' => 'POST', 'url' => '/api/topics/approve', 'auth' => 'auth:sanctum, check.blacklist',
                    'description' => 'Approve a user\'s request to join a topic.',
                    'body' => [
                        'topic_id' => 'integer, required, must exist in topics table',
                        'user_id' => 'integer, required, must exist in users table',
                    ],
                ],
                [
                    'method' => 'POST', 'url' => '/api/topics/warn', 'auth' => 'auth:sanctum, check.blacklist',
                    'description' => 'Issue a moderation warning to a participant.',
                    'body' => [
                        'topic_id' => 'integer, required, must exist in topics table',
                        'user_id' => 'integer, required, must exist in users table',
                    ],
                ],
                [
                    'method' => 'GET', 'url' => '/api/quizzes', 'auth' => 'auth:sanctum, check.blacklist',
                    'description' => 'List quizzes available to the current user.',
                ],
                [
                    'method' => 'GET', 'url' => '/api/quizzes/{quiz}', 'auth' => 'auth:sanctum, check.blacklist',
                    'description' => 'Get quiz details, including authoritative server_time and ends_at for countdown timers.',
                    'url_params' => ['quiz' => 'integer, the quiz ID'],
                ],
                [
                    'method' => 'POST', 'url' => '/api/attempts/{attempt}/submit', 'auth' => 'auth:sanctum, check.blacklist',
                    'description' => 'Submit answers for a quiz attempt. Server enforces the time limit (30s grace period) and auto-scores it.',
                    'url_params' => ['attempt' => 'integer, the quiz attempt ID'],
                    'body' => [
                        'answers' => 'array/object, required, e.g. {"1": "A", "2": "C"} keyed by question ID',
                        'auto_submitted' => 'boolean, optional',
                    ],
                ],
                [
                    'method' => 'GET', 'url' => '/api/topics/{topic}/posts', 'auth' => 'auth:sanctum, check.blacklist',
                    'description' => 'List posts (messages) in a topic.',
                    'url_params' => ['topic' => 'integer, the topic ID'],
                ],
                [
                    'method' => 'POST', 'url' => '/api/topics/{topic}/posts', 'auth' => 'auth:sanctum, check.blacklist',
                    'description' => 'Create a post (message) in a topic.',
                    'url_params' => ['topic' => 'integer, the topic ID'],
                    'body' => [
                        'content' => 'string, required',
                        'receiver_id' => 'integer, nullable, must exist in users table (for direct/private replies)',
                    ],
                ],
                [
                    'method' => 'POST', 'url' => '/api/posts/{post}/flag', 'auth' => 'auth:sanctum, check.blacklist',
                    'description' => 'Flag a post for moderator review.',
                    'url_params' => ['post' => 'integer, the post ID'],
                    'body' => ['reason' => 'string, required, max 255'],
                ],
            ],
        ], JsonResponse::HTTP_OK, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}