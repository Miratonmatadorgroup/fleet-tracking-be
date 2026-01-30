<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class DeliveryTimeService
{
    /**
     * Convert Google duration (in minutes) to estimated days.
     *
     * @param int $googleDurationMinutes
     * @return int
     */
    public function calculateEstimatedDays(int $googleDurationMinutes): int
    {
        // Convert minutes to days (round up)
        $estimatedDays = (int) ceil($googleDurationMinutes / 1440);

        Log::info("Google Duration (minutes): {$googleDurationMinutes}, Converted to Estimated Days: {$estimatedDays}");

        return $estimatedDays;
    }

    /**
     * Humanize the duration (e.g. "13 hours 45 minutes" instead of raw minutes).
     *
     * @param int $googleDurationMinutes
     * @return string
     */
    public function humanizeDuration(int $googleDurationMinutes): string
    {
        $days = intdiv($googleDurationMinutes, 1440); 
        $hours = intdiv($googleDurationMinutes % 1440, 60);
        $minutes = $googleDurationMinutes % 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = $days . ' ' . ($days === 1 ? 'day' : 'days');
        }
        if ($hours > 0) {
            $parts[] = $hours . ' ' . ($hours === 1 ? 'hour' : 'hours');
        }
        if ($minutes > 0) {
            $parts[] = $minutes . ' ' . ($minutes === 1 ? 'minute' : 'minutes');
        }

        return implode(' ', $parts) ?: '0 minutes';
    }
}
