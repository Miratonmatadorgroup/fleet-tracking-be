<?php
namespace App\Events\Authentication;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class UserLoggedInEvent
{
    use Dispatchable;

    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }
}
