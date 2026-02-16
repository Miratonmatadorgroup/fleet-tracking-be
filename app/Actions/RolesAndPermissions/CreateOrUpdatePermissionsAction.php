<?php
namespace App\Actions\RolesAndPermissions;

use Spatie\Permission\Models\Permission;
use App\Events\RolesAndPermissions\PermissionCreated;
use App\Events\RolesAndPermissions\PermissionUpdated;
use App\DTOs\RolesAndPermissions\CreateOrUpdatePermissionsDTO;

class CreateOrUpdatePermissionsAction
{
    public function execute(CreateOrUpdatePermissionsDTO $dto): array
    {
        $results = [];

        foreach ($dto->permissions as $permissionData) {
            $permission = Permission::updateOrCreate(
                ['name' => $permissionData['name'], 'guard_name' => 'api'],
                $permissionData
            );

            if ($permission->wasRecentlyCreated) {
                event(new PermissionCreated($permission));
            } else {
                event(new PermissionUpdated($permission));
            }

            $results[] = $permission;
        }

        return $results;
    }
}
