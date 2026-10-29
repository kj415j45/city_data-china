<?php

function requestMca(string $url, string $method, array $parameters, string &$cookie): array {
    $headers = [
        'Accept: application/json',
        'Origin: https://dmfw.mca.gov.cn',
        'Referer: https://dmfw.mca.gov.cn/XzqhVersionPublish.html',
        'X-Requested-With: XMLHttpRequest',
    ];
    if ($method === 'POST') {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }

    $maxAttempts = 3;
    $requestLabel = $url;
    if (isset($parameters['parentCode'])) {
        $requestLabel .= ' (parentCode=' . $parameters['parentCode'] . ')';
    }

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $requestHeaders = $headers;
        if ($cookie !== '') {
            $requestHeaders[] = 'Cookie: ' . $cookie;
        }
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $requestHeaders),
                'content' => $method === 'POST' ? http_build_query($parameters) : '',
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        $http_response_header = [];
        error_clear_last();
        $response = @file_get_contents($url, false, $context);
        $error = $response === false ? error_get_last() : null;
        $httpStatus = 0;
        $responseHeaders = $http_response_header;
        foreach ($responseHeaders as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
                $httpStatus = (int) $matches[1];
            }
        }
        updateMcaCookie($responseHeaders, $cookie);

        $retryable = $response === false || in_array($httpStatus, [408, 429, 500, 502, 503, 504], true);
        if ($retryable && $attempt < $maxAttempts) {
            $delay = 2 ** ($attempt - 1);
            foreach ($responseHeaders as $header) {
                if (preg_match('/^Retry-After:\s*(.+)$/i', $header, $matches)) {
                    $value = trim($matches[1]);
                    $retryAfter = ctype_digit($value) ? (int) $value : strtotime($value);
                    if ($retryAfter !== false) {
                        $seconds = ctype_digit($value) ? $retryAfter : $retryAfter - time();
                        $delay = max($delay, min(60, $seconds));
                    }
                }
            }
            $reason = $response === false ? 'transport failure' : "HTTP {$httpStatus}";
            fwrite(STDERR, "MCA request {$requestLabel}: {$reason}; attempt {$attempt}/{$maxAttempts}, retrying in {$delay}s." . PHP_EOL);
            sleep($delay);
            continue;
        }

        if ($response === false) {
            $detail = isset($error['message']) ? ' ' . $error['message'] : '';
            throw new RuntimeException("Failed to request MCA endpoint: {$requestLabel} after {$attempt} attempts.{$detail}");
        }
        if ($retryable) {
            throw new RuntimeException("MCA endpoint {$requestLabel} returned HTTP {$httpStatus} after {$attempt} attempts.");
        }

        return [
            'status' => $httpStatus,
            'body' => $response,
            'headers' => $responseHeaders,
        ];
    }
}

function updateMcaCookie(array $headers, string &$cookie): bool {
    $updated = false;
    $cookies = [];
    foreach (explode(';', $cookie) as $part) {
        $pair = explode('=', trim($part), 2);
        if (count($pair) === 2) {
            $cookies[$pair[0]] = $pair[1];
        }
    }

    foreach ($headers as $header) {
        if (!preg_match('/^Set-Cookie:\s*([^=;\s]+)=([^;]*)/i', $header, $matches)) {
            continue;
        }
        $cookies[$matches[1]] = $matches[2];
        $updated = true;
    }

    if ($updated) {
        $cookieParts = [];
        foreach ($cookies as $name => $value) {
            $cookieParts[] = $name . '=' . $value;
        }
        $cookie = implode('; ', $cookieParts);
    }

    return $updated;
}

function getAdministrativeTableName(string &$cookie): string {
    $response = requestMca(
        'https://dmfw.mca.gov.cn/XzqhVersionPublish.html',
        'GET',
        [],
        $cookie
    );
    updateMcaCookie($response['headers'], $cookie);
    if ($response['status'] !== 200) {
        throw new RuntimeException("MCA administrative-division page returned HTTP {$response['status']}.");
    }

    if (!preg_match('/\btableName\s*=\s*[\'"]([A-Za-z0-9_]+)[\'"]/', $response['body'], $matches)) {
        throw new RuntimeException('Unable to find the current administrative-division table name on the MCA page.');
    }

    return $matches[1];
}

function getAdministrativeDivisions(string $parentCode, string $tableName, string &$cookie): array {
    $url = 'https://dmfw.mca.gov.cn/xzqh/getCodeList';
    $parameters = [
        'parentCode' => $parentCode,
        'placeCode' => '',
        'tableName' => $tableName,
        'type' => '2',
    ];
    $response = requestMca($url, 'POST', $parameters, $cookie);

    $cookieUpdated = updateMcaCookie($response['headers'], $cookie);
    if ($response['status'] === 403 && $cookieUpdated) {
        $response = requestMca($url, 'POST', $parameters, $cookie);
        updateMcaCookie($response['headers'], $cookie);
    }
    if ($response['status'] !== 200) {
        $message = "MCA administrative-division API returned HTTP {$response['status']}.";
        if ($response['status'] === 403) {
            $message .= ' The WAF rejected the request; set MCA_COOKIE to a valid browser session Cookie if needed.';
        }
        throw new RuntimeException($message);
    }

    $payload = json_decode($response['body'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('MCA API returned invalid JSON: ' . json_last_error_msg());
    }
    if (!is_array($payload) || (int) ($payload['status'] ?? 0) !== 200 || !is_array($payload['data'] ?? null)) {
        $message = is_array($payload) ? ($payload['message'] ?? 'unexpected response structure') : 'unexpected response structure';
        throw new RuntimeException('MCA API request failed: ' . $message);
    }

    return $payload['data'];
}

function getAdministrativeItems(array $divisions, int $level): array {
    $items = [];
    foreach ($divisions as $division) {
        if (!is_array($division) || (int) ($division['level'] ?? 0) !== $level) {
            continue;
        }

        $code = $division['code'] ?? '';
        $name = $division['name'] ?? '';
        if (!is_string($name) || $name === '') {
            continue;
        }
        if (!is_string($code) || !preg_match('/^\d{9}$/', $code)) {
            fwrite(STDERR, "Skipping {$name}: administrative code is unavailable or invalid." . PHP_EOL);
            continue;
        }

        $items[$level === 4 ? $code : substr($code, 0, 6)] = $name;
    }

    return $items;
}
