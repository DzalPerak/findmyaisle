<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string|array  $roles  The required roles (comma-separated string or array)
     */
    public function handle(Request $request, Closure $next, ...$roles): SymfonyResponse
    {
        // Check if user is authenticated
        if (!$request->user()) {
            return redirect()->route('login');
        }

        $user = $request->user();
        
        // Load roles if not already loaded
        if (!$user->relationLoaded('roles')) {
            $user->load('roles');
        }

        // If no roles specified, just check if user is authenticated
        if (empty($roles)) {
            return $next($request);
        }

        // Check if user has any of the required roles
        $hasRequiredRole = $user->roles()->whereIn('name', $roles)->exists();

        if (!$hasRequiredRole) {
            // Return 403 for AJAX requests or redirect to dashboard for web requests
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Insufficient permissions. Required roles: ' . implode(', ', $roles)
                ], 403);
            }

            return redirect()->route('dashboard')->with('error', 
                'Access denied. You need one of these roles: ' . implode(', ', $roles)
            );
        }

        return $next($request);
    }
}
