<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use App\Services\TermiiService;
use App\Services\TwilioService;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Notification;
use App\Notifications\AdminBroadcastNotification;

class AdminBroadcastController extends Controller
{
    public function sendMessage(Request $request, TwilioService $twilio, TermiiService $termii)
    {
        try {
            $request->validate([
                'message' => 'required|string',
                'subject' => 'nullable|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return failureResponse($e->errors(), 422, 'validation', $e);
        }

        $message = $request->input('message');
        $subject = $request->input('subject') ?? 'Platform Notice';

        $users = User::all();

        //Send in-app + email notifications
        Notification::send($users, new AdminBroadcastNotification($message, $subject));

        //Send SMS + WhatsApp manually
        $failedUsers = [];
        foreach ($users as $user) {
            dispatch(function () use ($user, $twilio, $termii, $message) {
                try {
                    if ($user->phone) {
                        $termii->sendSms($user->phone, $message);
                    }

                    if ($user->whatsapp_number) {
                        $twilio->sendWhatsAppMessage($user->whatsapp_number, $message);
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed to send broadcast to user', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            })->onQueue('broadcast');
        }


        if (count($failedUsers) > 0) {
            return failureResponse(
                'Broadcast message sent with some errors.',
                207,
                'partial_failure',
                null,
            ) + ['failed_user_ids' => $failedUsers];
        }

        return successResponse('Broadcast message sent successfully to all users.');
    }
}
