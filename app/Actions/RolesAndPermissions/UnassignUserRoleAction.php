<?php
namespace App\Actions\RolesAndPermissions;

use App\Models\User;
use App\DTOs\RolesAndPermissions\UnassignUserRoleDTO;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UnassignUserRoleAction
{
    public function execute(UnassignUserRoleDTO $dto): User
    {
        $user = User::where('email', $dto->identifier)
            ->orWhere('phone', $dto->identifier)
            ->orWhere('whatsapp_number', $dto->identifier)
            ->first();

        if (!$user) {
            throw new ModelNotFoundException('User not found');
        }

        if ($user->hasRole($dto->role)) {
            $user->removeRole($dto->role);
        }

        return $user;
    }
}
