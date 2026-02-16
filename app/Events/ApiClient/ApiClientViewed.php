<?php

namespace App\Events\ApiClient;

use App\Models\ApiClient;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class ApiClientViewed
{
    use Dispatchable, SerializesModels;

    public function __construct(public $clients) {}
}
