<?php

$baseUrl = 'https://query.ampre.ca/odata/Property';
$filter = "PublicRemarks ne null and " .
    "startswith(TransactionType,'For Sale') and (" .
    "contains(PublicRemarks,'Power of Sale') or " .
    "contains(PublicRemarks,'power of sale') or " .
    "contains(PublicRemarks,'POWER OF SALE') or " .
    "contains(PublicRemarks,'Power Of Sale'))";

$query = [
    '$top' => 30,
];

if ($filter !== '') {
    $query['$filter'] = $filter;
}

$queryString = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
$url = "{$baseUrl}?{$queryString}";
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
 * Format bytes to a human readable string.
 */
function formatBytes(int $bytes): string
{
    if ($bytes === 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = min((int)floor(log($bytes, 1024)), count($units) - 1);
    $formatted = $bytes / pow(1024, $power);

    return sprintf('%.2f %s', $formatted, $units[$power]);
}

if ($response === false) {
    $logEntry = sprintf(
        "[%s] Request failed (HTTP %s) size=%d bytes (%s): %s\n\n",
        $timestamp,
        $statusCode ?: 'N/A',
        $responseSize,
        formatBytes($responseSize),
        $curlError ?: 'Unknown cURL error'
    );
} else {
    $decoded = json_decode($response, true);
    $itemCount = null;

    if (is_array($decoded)) {
        if (isset($decoded['value']) && is_array($decoded['value'])) {
            $itemCount = count($decoded['value']);
        } elseif (array_key_exists('@odata.count', $decoded)) {
            $itemCount = (int)$decoded['@odata.count'];
        }
    }

    $summaryPieces = [
        sprintf('size=%d bytes (%s)', $responseSize, formatBytes($responseSize)),
    ];

    if ($itemCount !== null) {
        $summaryPieces[] = sprintf('items=%d', $itemCount);
    } else {
        $summaryPieces[] = 'items=n/a';
    }

    $logEntry = sprintf(
        "[%s] Request succeeded (HTTP %d) %s\n%s\n\n",
        $timestamp,
        $statusCode,
        implode(', ', $summaryPieces),
        $response
    );
}

file_put_contents($logFile, $logEntry, FILE_APPEND);

echo $logEntry;
