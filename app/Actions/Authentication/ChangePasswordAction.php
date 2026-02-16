<?php

namespace App\Actions\Authentication;


use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\DTOs\Authentication\ChangePasswordDTO;

class ChangePasswordAction
{
    public static function execute(ChangePasswordDTO $dto): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!Hash::check($dto->current_password, $user->password)) {
            throw new \Exception("Current password is incorrect", 403);
        }

        /** @var string $hashedPassword */
        $hashedPassword = Hash::make($dto->new_password);

        $user->password = $hashedPassword;
        $user->save();
    }
}
