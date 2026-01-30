<?php

namespace App\Events\Dispute;

use Illuminate\Queue\SerializesModels;
use Illuminate\Pagination\LengthAwarePaginator;

class DisputesListedEvent
{
    use SerializesModels;

    public function __construct(
        public readonly LengthAwarePaginator $disputes
    ) {}
}

