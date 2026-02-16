<?php
namespace App\Events\RolesAndPermissions;


use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Spatie\Permission\Models\Permission;

class PermissionUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Permission $permission) {}
}
