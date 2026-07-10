<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware as SpatieRoleOrPermissionMiddleware;

class RoleOrPermissionMiddleware extends SpatieRoleOrPermissionMiddleware
{
    public function handle(Request $request, Closure $next, $roleOrPermission, $guard = null)
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions')) {
            return $next($request);
        }

        return parent::handle($request, $next, $roleOrPermission, $guard);
    }
}
