<?php

$baseUrl = 'https://query.ampre.ca/odata/Property';
$queryString = http_build_query([
    '$top' => 1,
]);
$url = "{$baseUrl}?{$queryString}";

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

if ($response === false) {
    fwrite(STDERR, sprintf("Request failed (HTTP %s): %s\n", $statusCode ?: 'N/A', $curlError ?: 'Unknown cURL error'));
    exit(1);
}

$decoded = json_decode($response, true);

if (!isset($decoded['value'][0])) {
    fwrite(STDERR, "Unexpected response:\n$response\n");
    exit(1);
}

$first = $decoded['value'][0];

echo "Keys:\n";
foreach (array_keys($first) as $key) {
    printf("- %s\n", $key);
}

echo "\nSample listing excerpt:\n";
foreach (['TransactionType', 'ContractStatus', 'PublicRemarks'] as $field) {
    if (array_key_exists($field, $first)) {
        printf("%s: %s\n", $field, var_export($first[$field], true));
    }
}

$metadataCurl = curl_init('https://query.ampre.ca/odata/$metadata');
curl_setopt_array($metadataCurl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/xml',
        'Authorization: Bearer eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiJ2ZW5kb3IvdHJyZWIvOTgyOSIsImF1ZCI6IkFtcFVzZXJzUHJkIiwicm9sZXMiOlsiQW1wVmVuZG9yIl0sImlzcyI6InByb2QuYW1wcmUuY2EiLCJleHAiOjI1MzQwMjMwMDc5OSwiaWF0IjoxNzYxNzQ4NDE5LCJzdWJqZWN0VHlwZSI6InZlbmRvciIsInN1YmplY3RLZXkiOiI5ODI5IiwianRpIjoiY2JmYTBiNWIxZGQxMGZjZiIsImN1c3RvbWVyTmFtZSI6InRycmViIn0.u6yj3SqCU9HwCVKj3MCJ8MOfnq-8QiKYH2WYNoOhOLc',
    ],
]);

$metadata = curl_exec($metadataCurl);
$metadataError = curl_error($metadataCurl);
$metadataStatus = curl_getinfo($metadataCurl, CURLINFO_HTTP_CODE);
curl_close($metadataCurl);

if ($metadata === false || $metadataStatus !== 200) {
    fwrite(STDERR, sprintf("\nFailed to load metadata (HTTP %s): %s\n", $metadataStatus ?: 'N/A', $metadataError ?: 'Unknown cURL error'));
    exit(1);
}

echo "\nMetadata snippet around TransactionType:\n";
$context = 200;
$pos = stripos($metadata, 'TransactionType');
if ($pos === false) {
    echo "TransactionType not found in metadata.\n";
} else {
    $start = max(0, $pos - $context);
    $snippet = substr($metadata, $start, 2 * $context);
    echo $snippet, "\n";
}
