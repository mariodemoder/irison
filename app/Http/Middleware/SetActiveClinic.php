<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetActiveClinic
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->clinic) {
            app()->instance('activeClinic', $user->clinic);
        }

        return $next($request);
    }
}
