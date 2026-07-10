<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Middleware\PermissionMiddleware as SpatiePermissionMiddleware;

class PermissionMiddleware extends SpatiePermissionMiddleware
{
    public function handle(Request $request, Closure $next, $permission, $guard = null)
    {
        if (!Schema::hasTable('permissions')) {
            return $next($request);
        }

        return parent::handle($request, $next, $permission, $guard);
    }
}
