<?php
namespace App\Events\Driver;

use App\Models\Driver;
use Illuminate\Queue\SerializesModels;

class DriverApplicationSubmitted
{
    use SerializesModels;

    public Driver $driver;

    public function __construct(Driver $driver)
    {
        $this->driver = $driver;
    }
}
