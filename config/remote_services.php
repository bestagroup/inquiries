<?php

return [
    'allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('REMOTE_SERVICE_ALLOWED_HOSTS', ''))))),
    'allow_private_networks' => (bool) env('REMOTE_SERVICE_ALLOW_PRIVATE_NETWORKS', false),
    'require_https_in_production' => (bool) env('REMOTE_SERVICE_REQUIRE_HTTPS', true),
    'enforce_dns_resolution' => (bool) env('REMOTE_SERVICE_ENFORCE_DNS_RESOLUTION', true),
    'max_response_bytes' => (int) env('REMOTE_SERVICE_MAX_RESPONSE_BYTES', 1048576),
    'async' => (bool) env('REMOTE_SERVICE_ASYNC', true),
    'queue' => (string) env('REMOTE_SERVICE_QUEUE', 'remote-services'),
];
