<?php

namespace MltStephane\LaravelAnalytics\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use MltStephane\LaravelAnalytics\Analytics;

class CollectController
{
    public function __invoke(Request $request, Analytics $analytics): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->rules());

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();

        $payload['uuid'] = $payload['uuid'] ?? (string) Str::uuid();

        if (isset($payload['timestamp'])) {
            $now = time();
            $payload['timestamp'] = min(max((int) $payload['timestamp'], $now - 86400), $now + 86400);
        }

        $analytics->collect($payload, [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request' => $request,
        ]);

        return response()->json(null, 204);
    }

    protected function rules(): array
    {
        return [
            'type' => ['required', 'in:pageview,event'],
            'name' => ['required_if:type,event', 'string', 'max:50'],
            // Required for pageviews; optional for custom events.
            'url' => ['required_if:type,pageview', 'string', 'max:2048'],
            'title' => ['nullable', 'string', 'max:255'],
            'referrer' => ['nullable', 'string', 'max:2048'],
            'hostname' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:20'],
            // Screen size is accepted but not stored (privacy).
            'screen' => ['nullable', 'string', 'max:20'],
            'data' => ['nullable', 'array', 'max:50'],
            'uuid' => ['nullable', 'string', 'max:64'],
            'timestamp' => ['nullable', 'integer'],
        ];
    }
}
