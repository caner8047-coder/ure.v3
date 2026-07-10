<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Middleware\RoleMiddleware as SpatieRoleMiddleware;

class RoleMiddleware extends SpatieRoleMiddleware
{
    public function handle(Request $request, Closure $next, $role, $guard = null)
    {
        if (!Schema::hasTable('roles')) {
            return $next($request);
        }

        return parent::handle($request, $next, $role, $guard);
    }
}
