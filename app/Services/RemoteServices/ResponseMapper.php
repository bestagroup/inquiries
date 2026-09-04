<?php

namespace App\Services\RemoteServices;

use App\Models\RemoteService;

class ResponseMapper
{
    public function map(RemoteService $service, ?array $payload, ?string $raw = null): array
    {
        $service->loadMissing('outputFields');
        if ($service->outputFields->isEmpty()) {
            return $payload ?? ['result' => $raw];
        }

        $source = $payload ?? ['_raw' => $raw];
        $result = [];
        foreach ($service->outputFields as $field) {
            $path = $field->json_path ?: $field->key;
            $result[] = [
                'key' => $field->key,
                'label' => $field->label,
                'value' => data_get($source, $path),
            ];
        }

        return $result;
    }
}
