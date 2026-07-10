<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User;
use App\Models\Personnel;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Permissions
        $permissions = [
            'manage stocks',
            'view stocks',
            'manage planning',
            'view planning',
            'manage production',
            'view production',
            'manage users',
            'view reports',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName]);
        }

        // Create Roles and Assign Permissions

        // 1. Depo / Stok Role
        $depoRole = Role::firstOrCreate(['name' => 'Depo/Stok']);
        $depoRole->syncPermissions([
            'view stocks',
            'manage stocks',
            'view planning',
            'view production',
        ]);

        // 2. Planlama Role
        $planlamaRole = Role::firstOrCreate(['name' => 'Planlama']);
        $planlamaRole->syncPermissions([
            'view stocks',
            'view planning',
            'manage planning',
            'view production',
            'view reports',
        ]);

        // 3. Üretim Role
        $uretimRole = Role::firstOrCreate(['name' => 'Üretim']);
        $uretimRole->syncPermissions([
            'view stocks',
            'view planning',
            'view production',
            'manage production',
        ]);

        // 4. Üst Yönetim Role
        $ustYonetimRole = Role::firstOrCreate(['name' => 'Üst Yönetim']);
        $ustYonetimRole->syncPermissions(Permission::all());

        // Assign Role to Personnel (Main authenticatable model)
        $personnels = Personnel::all();
        foreach ($personnels as $p) {
            if ($p->isAdmin()) {
                $p->assignRole('Üst Yönetim');
                continue;
            }

            $deptName = $p->department ? strtolower($p->department->BolumAdi ?? '') : '';
            if (str_contains($deptName, 'plan') || str_contains($deptName, 'ofis')) {
                $p->assignRole('Planlama');
            } elseif (str_contains($deptName, 'depo') || str_contains($deptName, 'stok')) {
                $p->assignRole('Depo/Stok');
            } else {
                $p->assignRole('Üretim');
            }
        }

        // Assign Role to Users if they exist
        try {
            $users = User::all();
            foreach ($users as $user) {
                if ($user->isAdmin()) {
                    $user->assignRole('Üst Yönetim');
                    continue;
                }

                $deptName = $user->department ? strtolower($user->department->BolumAdi ?? '') : '';
                if (str_contains($deptName, 'plan') || str_contains($deptName, 'ofis')) {
                    $user->assignRole('Planlama');
                } elseif (str_contains($deptName, 'depo') || str_contains($deptName, 'stok')) {
                    $user->assignRole('Depo/Stok');
                } else {
                    $user->assignRole('Üretim');
                }
            }
        } catch (\Throwable) {
            // Ignore if User table / model is not fully configured or used
        }
    }
}
