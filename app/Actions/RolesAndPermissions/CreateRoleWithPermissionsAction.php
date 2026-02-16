<?php
namespace App\Actions\RolesAndPermissions;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Events\RolesAndPermissions\RoleCreatedEvent;
use App\DTOs\RolesAndPermissions\CreateRoleWithPermissionsDTO;

class CreateRoleWithPermissionsAction
{
    public function execute(CreateRoleWithPermissionsDTO $dto): Role
    {
        return DB::transaction(function () use ($dto) {
            $role = Role::create([
                'name' => $dto->role,
                'guard_name' => 'api',
            ]);

            $permissionIds = [];
            foreach ($dto->permissions as $permissionName) {
                $permission = Permission::firstOrCreate(
                    ['name' => $permissionName, 'guard_name' => 'api']
                );
                $permissionIds[] = $permission->id;
            }

            $role->syncPermissions($permissionIds);

            event(new RoleCreatedEvent($role, $dto->permissions));

            return $role;
        });
    }
}
