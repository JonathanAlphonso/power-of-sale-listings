<?php

$baseUrl = 'https://query.ampre.ca/odata/Property';
$filter = "PublicRemarks ne null and " .
    "startswith(TransactionType,'For Sale') and (" .
    "contains(PublicRemarks,'Power of Sale') or " .
    "contains(PublicRemarks,'power of sale') or " .
    "contains(PublicRemarks,'POWER OF SALE') or " .
    "contains(PublicRemarks,'Power Of Sale'))";

$limit = 30;
if ($argc > 1 && is_numeric($argv[1])) {
    $limit = max(1, (int)$argv[1]);
}

$query = [
    '$top' => $limit,
    '$filter' => $filter,
];

$url = $baseUrl . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
$logFile = __DIR__ . DIRECTORY_SEPARATOR . 'request.log';

$curl = curl_init($url);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'OData-Version: 4.0',
        'Authorization: Bearer eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiJ2ZW5kb3IvdHJyZWIvOTgyOSIsImF1ZCI6IkFtcFVzZXJzUHJkIiwicm9sZXMiOlsiQW1wVmVuZG9yIl0sImlzcyI6InByb2QuYW1wcmUuY2EiLCJleHAiOjI1MzQwMjMwMDc5OSwiaWF0IjoxNzYxNzQ4NDE5LCJzdWJqZWN0VHlwZSI6InZlbmRvciIsInN1YmplY3RLZXkiOiI5ODI5IiwianRpIjoiY2JmYTBiNWIxZGQxMGZjZiIsImN1c3RvbWVyTmFtZSI6InRycmViIn0.u6yj3SqCU9HwCVKj3MCJ8MOfnq-8QiKYH2WYNoOhOLc',
    ],
]);

$response = curl_exec($curl);
$curlError = curl_error($curl);
$statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

$timestamp = date('c');
$responseSize = $response === false ? 0 : strlen($response);

/**
 * Convert bytes to a human-readable string.
 */
function formatBytes(int $bytes): string
{
    if ($bytes === 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = min((int)floor(log($bytes, 1024)), count($units) - 1);

    return sprintf('%.2f %s', $bytes / pow(1024, $power), $units[$power]);
}

if ($response === false) {
    $message = sprintf(
        "[%s] count_results failed (HTTP %s) requested=%d size=%d bytes (%s): %s\n",
        $timestamp,
        $statusCode ?: 'N/A',
        $limit,
        $responseSize,
        formatBytes($responseSize),
        $curlError ?: 'Unknown cURL error'
    );
    file_put_contents($logFile, $message, FILE_APPEND);
    fwrite(STDERR, $message);
    exit(1);
}

if ($statusCode !== 200) {
    $message = sprintf(
        "[%s] count_results received HTTP %d requested=%d size=%d bytes (%s)\nResponse: %s\n",
        $timestamp,
        $statusCode,
        $limit,
        $responseSize,
        formatBytes($responseSize),
        $response
    );
    file_put_contents($logFile, $message, FILE_APPEND);
    fwrite(STDERR, $message);
    exit(1);
}

$data = json_decode($response, true);

if (!isset($data['value']) || !is_array($data['value'])) {
    $message = sprintf(
        "[%s] count_results unexpected response structure requested=%d size=%d bytes (%s)\nResponse: %s\n",
        $timestamp,
        $limit,
        $responseSize,
        formatBytes($responseSize),
        $response
    );
    file_put_contents($logFile, $message, FILE_APPEND);
    fwrite(STDERR, $message);
    exit(1);
}

$count = count($data['value']);

printf("Requested %d, returned %d result(s)\n", $limit, $count);

$logEntry = sprintf(
    "[%s] count_results succeeded requested=%d returned=%d size=%d bytes (%s)\n",
    $timestamp,
    $limit,
    $count,
    $responseSize,
    formatBytes($responseSize)
);

file_put_contents($logFile, $logEntry, FILE_APPEND);
