<?php

function successResponse($message, $data = null, $status = 200, $extras = [])
{
    if (is_string($message) && str_contains(strtolower($message), "created")) {
        $status = 201;
    }
    return response()->json(
        [
            'success' => true,
            'message' => $message,
            'data' => $data,
            ...$extras,
        ],
        $status
    );
}

function failureResponse($message, $status = 400, $type = null, ?Throwable $th = null)
{
    $base = [
        'success' => false,
        'type'    => $type,
        'message' => null,
    ];

    $extra = [];

    if (is_array($message)) {
        $flatErrors = collect($message)->flatMap(function ($value) {
            return is_array($value) ? $value : [$value];
        })->filter()->values();

        $base['message'] = $flatErrors->first() ?? 'Validation failed.';

        $extra = $message;
    } else {
        $base['message'] = $message;
    }

    if (is_string($base['message']) && str_contains(strtolower($base['message']), "not found")) {
        $status = 404;
    }

    if ($th) {
        logError($base['message'], $th);
    }

    if (env("APP_ENV") === "local" && $th) {
        $base['dev_message'] = [
            "message" => $th->getMessage(),
            "error"   => $th->getTraceAsString()
        ];
    }

    return response()->json(array_merge($base, $extra), $status);
}

function detectDeviceName($userAgent)
{
    $userAgent = strtolower($userAgent);

    if (str_contains($userAgent, 'windows')) {
        return 'Windows PC';
    }

    if (str_contains($userAgent, 'macintosh') || str_contains($userAgent, 'mac os')) {
        return 'Macbook';
    }

    if (str_contains($userAgent, 'iphone')) {
        return 'iPhone';
    }

    if (str_contains($userAgent, 'android')) {
        return 'Android';
    }

    if (str_contains($userAgent, 'linux')) {
        return 'Linux';
    }

    if (str_contains($userAgent, 'postman')) {
        return 'Postman';
    }

    if (str_contains($userAgent, 'vscode') || str_contains($userAgent, 'restclient')) {
        return 'VSCode RestClient';
    }

    return 'Unknown Device';
}


function logError($message, Throwable $th)
{
    logger(
        $message,
        [
            'message' => $th->getMessage(),
            'file' => $th->getFile(),
            'line' => $th->getLine(),
        ]
    );
}
