<?php
namespace App\Actions\RolesAndPermissions;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\DTOs\RolesAndPermissions\GetUserRolesAndPermissionsDTO;

class GetUserRolesAndPermissionsAction
{
    public function execute(GetUserRolesAndPermissionsDTO $dto): array
    {
        $user = User::where('email', $dto->identifier)
            ->orWhere('phone', $dto->identifier)
            ->orWhere('whatsapp_number', $dto->identifier)
            ->first();

        if (!$user) {
            throw new ModelNotFoundException('User not found');
        }

        return [
            'user' => $user->only(['id', 'name', 'email']),
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ];
    }
}
