<?php
namespace App\DTOs\Authentication;

class DeleteUserDTO
{
   public function __construct(
        public readonly string $adminId, 
        public readonly string $userId
    ) {}
}
