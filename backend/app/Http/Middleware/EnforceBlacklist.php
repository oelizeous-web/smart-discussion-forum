<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnforceBlacklist
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            $blacklist = $user->blacklist;

            if ($blacklist && $blacklist->status === 'SUSPENDED') {
                
                // Has the temporary ban expired?
                if ($blacklist->expiry_date && $blacklist->expiry_date->isPast()) {
                    // Use the unified pardon method we added to the User model earlier
                    // This resets warnings to 0 and sets status to ACTIVE
                    $user->pardon(); 
                    
                    return $next($request);
                }

                // --- STILL BANNED: Handle based on Request Type ---

                // 1. If this is an API request from the Java Client
                if ($request->expectsJson()) {
                    // Revoke the user's Sanctum tokens to force a log out on the device
                    $user->tokens()->delete(); 

                    return response()->json([
                        'error' => 'Your account is currently suspended due to policy violations.',
                        'status' => 'BLACKLISTED',
                        'suspension_ends' => $blacklist->expiry_date->toIso8601String() // Let Java know when to try again
                    ], 403);
                }

                // 2. If this is a Web request from a Browser (Livewire/Volt)
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('account.blacklisted');
            }
        }

        return $next($request);
    }
}