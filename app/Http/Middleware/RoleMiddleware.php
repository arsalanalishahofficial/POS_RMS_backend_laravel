<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle($request, Closure $next, $role)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        if ($user->role !== $role) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 403);
        }

        return $next($request);
    }

}
