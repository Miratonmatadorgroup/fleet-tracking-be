<?php
namespace App\DTOs\Wallet;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserTransactionsDTO
{
    public User $user;

    public static function fromAuth(): self
    {
        $user = Auth::user();

        if (!$user) {
            abort(403, "Unauthorized. You must be logged in as a user.");
        }

        return new self($user);
    }

    public function __construct(User $user)
    {
        $this->user = $user;
    }
}
