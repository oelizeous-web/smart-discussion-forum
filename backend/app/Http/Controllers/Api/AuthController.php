<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // FOR THE JAVA TEAM: Registration Endpoint
    public function register(Request $request, RegistrationService $registrationService)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'agreed_to_rules' => 'required|boolean|accepted', // Must be true
        ]);

        $user = $registrationService->registerUser($validated, 'student');

        $token = $user->createToken('java-client')->plainTextToken;

        return $this->respondWithToken($token, $user);
    }

    // FOR THE JAVA TEAM: Login Endpoint
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized',
            'status' => 'wrong passwords'
            ], 401);
        }

        $user = Auth::user();

        // Check if the student has an active suspension in the blacklists table
        $isBlacklisted = \App\Models\Blacklist::where('user_id', $user->id)
            ->where('status', 'SUSPENDED')
            ->where('expiry_date', '>', now())
            ->exists();

        if ($isBlacklisted) {
            Auth::logout();
            return response()->json([
                'error' => 'Account is suspended due to inactivity.',
                'status' => 'BLACKLISTED'
            ], 403);
        }

        $token = $user->createToken('java-client')->plainTextToken;

        return $this->respondWithToken($token, $user);
    }
        protected function respondWithToken($token, $user)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(), // Java team uses this for UI
            ]
        ]);
    }

    public function registerLecturer(Request $request, RegistrationService $registrationService)
    {
        // 1. Validate the standard fields PLUS the secret code
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'agreed_to_rules' => 'required|boolean|accepted',
        ]);

        // 2. Use the exact same service, but pass the 'lecturer' role
        $user = $registrationService->registerUser($validated, 'lecturer');

        // 3. Log them in and return the Sanctum token
        $token = $user->createToken('java-client')->plainTextToken;

        return $this->respondWithToken($token, $user);
    }

}
