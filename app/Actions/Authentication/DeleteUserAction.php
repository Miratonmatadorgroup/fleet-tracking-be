<?php

namespace App\Actions\Authentication;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\DTOs\Authentication\DeleteUserDTO;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DeleteUserAction
{
    public function execute(DeleteUserDTO $dto): void
    {
        $admin = Auth::user(); 
        $user = User::find($dto->userId);

        if (!$user) {
            throw new HttpException(404, 'User not found.');
        }

        if ($user->id === $admin->id) {
            throw new HttpException(403, 'Admins cannot delete themselves.');
        }

        $user->delete();
    }
}
