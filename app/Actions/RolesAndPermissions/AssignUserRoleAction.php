<?php
namespace App\Actions\RolesAndPermissions;


use App\Models\User;
use App\DTOs\RolesAndPermissions\AssignUserRoleDTO;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AssignUserRoleAction
{
    public function execute(AssignUserRoleDTO $dto): User
    {
        $user = User::where('email', $dto->identifier)
            ->orWhere('phone', $dto->identifier)
            ->orWhere('whatsapp_number', $dto->identifier)
            ->first();

        if (!$user) {
            throw new ModelNotFoundException('User not found');
        }

        $user->assignRole($dto->role);

        return $user;
    }
}
