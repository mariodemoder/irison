<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureClinic
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        abort_if(!$user || !$user->clinic_id, 403, 'No clinic assigned');

        app()->instance('activeClinic', $user->clinic);

        return $next($request);
    }
}
