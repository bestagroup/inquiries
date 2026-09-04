<?php

namespace App\Actions\ServiceRequests;

use App\Enums\AttemptStatus;
use App\Enums\PayloadMode;
use App\Enums\ResponseFormat;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestAttempt;
use App\Services\RemoteServices\EndpointSecurityPolicy;
use App\Services\RemoteServices\ResponseMapper;
use App\Services\RemoteServices\ServiceTokenProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

class ExecuteServiceRequest
{
    public function __construct(
        private readonly EndpointSecurityPolicy $endpointPolicy,
        private readonly ResponseMapper $responseMapper,
        private readonly ServiceTokenProvider $serviceTokenProvider,
    ) {}

    public function execute(ServiceRequest $serviceRequest): ServiceRequestAttempt
    {
        $serviceRequest->loadMissing(['service', 'user']);
        $service = $serviceRequest->service;

        if (! $service || $service->trashed() || ! $service->is_active) {
            throw new RuntimeException('این سرویس غیرفعال است.');
        }

        if (! $serviceRequest->user?->is_active) {
            throw new RuntimeException('حساب کاربری درخواست‌دهنده غیرفعال است.');
        }

        $assignment = $serviceRequest->user->services()
            ->whereKey($serviceRequest->service_id)
            ->wherePivot('is_active', true)
            ->first();

        if (! $assignment) {
            throw new RuntimeException('دسترسی کاربر به این سرویس لغو شده است.');
        }

        $this->endpointPolicy->assertAllowed($service->endpoint_url);

        $attempt = DB::transaction(function () use ($serviceRequest, $service): ServiceRequestAttempt {
            $locked = ServiceRequest::query()->whereKey($serviceRequest->getKey())->lockForUpdate()->firstOrFail();
            $sequence = $locked->attempt_count + 1;
            $locked->update([
                'status' => ServiceRequestStatus::Pending,
                'attempt_count' => $sequence,
                'last_requested_at' => now(),
                'last_error' => null,
            ]);

            return ServiceRequestAttempt::query()->create([
                'service_request_id' => $locked->id,
                'sequence' => $sequence,
                'status' => AttemptStatus::Running,
                'endpoint_url' => $service->endpoint_url,
                'http_method' => $service->http_method->value,
                'request_payload' => $locked->input_payload,
                'started_at' => now(),
            ]);
        });

        $started = hrtime(true);
        $httpStatus = null;

        try {
            $response = $this->send($serviceRequest);
            $httpStatus = $response->status();
            $durationMs = (int) round((hrtime(true) - $started) / 1_000_000);
            $this->guardResponseSize($response->body());

            if (! $response->successful()) {
                $attempt->update([
                    'status' => AttemptStatus::Failed,
                    'response_raw' => $response->body(),
                    'http_status' => $response->status(),
                    'duration_ms' => $durationMs,
                    'error_code' => 'HTTP_'.$response->status(),
                    'error_message' => 'سرور مقصد پاسخ HTTP ناموفق بازگرداند.',
                    'completed_at' => now(),
                ]);

                $this->updateAggregateIfLatest($serviceRequest, $attempt, [
                    'status' => ServiceRequestStatus::Failed->value,
                    'last_responded_at' => now(),
                    'last_http_status' => $response->status(),
                    'last_duration_ms' => $durationMs,
                    'last_error' => 'پاسخ HTTP ناموفق: '.$response->status(),
                ]);

                return $attempt->fresh();
            }

            [$payload, $raw] = $this->parseResponse($response, $service->response_format);
            $mapped = $this->responseMapper->map($service, $payload, $raw);

            $attempt->update([
                'status' => AttemptStatus::Succeeded,
                'response_payload' => $payload,
                'mapped_response' => $mapped,
                'response_raw' => $raw,
                'http_status' => $response->status(),
                'duration_ms' => $durationMs,
                'completed_at' => now(),
            ]);

            $this->updateAggregateIfLatest($serviceRequest, $attempt, [
                'status' => ServiceRequestStatus::Succeeded->value,
                'last_responded_at' => now(),
                'last_http_status' => $response->status(),
                'last_duration_ms' => $durationMs,
                'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            $durationMs = (int) round((hrtime(true) - $started) / 1_000_000);
            report($exception);
            $message = $exception instanceof ConnectionException
                ? 'ارتباط با سرور مقصد برقرار نشد.'
                : mb_substr($exception->getMessage(), 0, 1000);

            $attempt->update([
                'status' => AttemptStatus::Failed,
                'http_status' => $httpStatus,
                'duration_ms' => $durationMs,
                'error_code' => class_basename($exception),
                'error_message' => $message,
                'completed_at' => now(),
            ]);
            $this->updateAggregateIfLatest($serviceRequest, $attempt, [
                'status' => ServiceRequestStatus::Failed->value,
                'last_responded_at' => now(),
                'last_http_status' => $httpStatus,
                'last_duration_ms' => $durationMs,
                'last_error' => $message,
            ]);
        }

        return $attempt->fresh();
    }

    private function send(ServiceRequest $serviceRequest): Response
    {
        $service = $serviceRequest->service;
        $headers = collect((array) $service->headers)
            ->reject(fn ($value, $key): bool => strtolower((string) $key) === 'authorization')
            ->put('Authorization', $this->serviceTokenProvider->authorizationHeader())
            ->all();

        $pending = Http::withHeaders($headers)
            ->timeout($service->timeout_seconds)
            ->connectTimeout($service->connect_timeout_seconds)
            ->withoutRedirecting()
            ->withOptions($this->responseLimitOptions())
            ->retry(max(1, $service->retry_times + 1), $service->retry_delay_ms, throw: false);

        $payload = $serviceRequest->input_payload;
        $method = $service->http_method->value;

        return match ($service->payload_mode) {
            PayloadMode::Query => $pending->withQueryParameters($payload)->send($method, $service->endpoint_url),
            PayloadMode::Form => $pending->asForm()->send($method, $service->endpoint_url, ['form_params' => $payload]),
            PayloadMode::Json => $pending->asJson()->send($method, $service->endpoint_url, ['json' => $payload]),
        };
    }

    private function parseResponse(Response $response, ResponseFormat $format): array
    {
        if ($format === ResponseFormat::Json) {
            if (trim($response->body()) === '') {
                return [[], null];
            }

            try {
                $decoded = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new RuntimeException('پاسخ JSON سرویس معتبر نیست.', previous: $exception);
            }

            return [is_array($decoded) ? $decoded : ['value' => $decoded], null];
        }

        if ($format === ResponseFormat::Xml) {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response->body(), \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
            if ($xml === false) {
                libxml_clear_errors();
                throw new RuntimeException('پاسخ XML سرویس معتبر نیست.');
            }
            $decoded = json_decode(json_encode($xml, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            libxml_clear_errors();

            return [$decoded, null];
        }

        return [null, $response->body()];
    }

    private function updateAggregateIfLatest(ServiceRequest $request, ServiceRequestAttempt $attempt, array $values): void
    {
        ServiceRequest::query()
            ->whereKey($request->getKey())
            ->where('attempt_count', $attempt->sequence)
            ->update($values);
    }

    private function guardResponseSize(string $body): void
    {
        $max = (int) config('remote_services.max_response_bytes', 1048576);
        if (strlen($body) > $max) {
            throw new RuntimeException('حجم پاسخ سرویس بیشتر از حد مجاز است.');
        }
    }

    private function responseLimitOptions(): array
    {
        $max = (int) config('remote_services.max_response_bytes', 1048576);

        return [
            'on_headers' => static function (ResponseInterface $response) use ($max): void {
                $length = $response->getHeaderLine('Content-Length');
                if ($length !== '' && ctype_digit($length) && (int) $length > $max) {
                    throw new RuntimeException('حجم پاسخ سرویس بیشتر از حد مجاز است.');
                }
            },
            'progress' => static function (int $downloadTotal, int $downloaded) use ($max): void {
                if ($downloadTotal > $max || $downloaded > $max) {
                    throw new RuntimeException('حجم پاسخ سرویس بیشتر از حد مجاز است.');
                }
            },
        ];
    }
}
