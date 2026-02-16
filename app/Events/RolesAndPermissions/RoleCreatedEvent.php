<?php
namespace App\Events\RolesAndPermissions;

use Spatie\Permission\Models\Role;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class RoleCreatedEvent
{
    use Dispatchable, SerializesModels;

    public Role $role;
    public array $permissions;

    public function __construct(Role $role, array $permissions)
    {
        $this->role = $role;
        $this->permissions = $permissions;
    }
}
