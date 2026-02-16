<?php
namespace App\Events\ApiClient;


use App\Models\ApiClient;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApiClientBlocked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ApiClient $apiClient,
        public readonly bool $blocked
    ) {}
}
