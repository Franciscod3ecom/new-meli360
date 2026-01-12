<?php
// backend/lib/ml_api.php

function makeCurlRequest($url, $method = 'GET', $headers = [], $postData = [], $json = false) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CustomRequest, $method);

    if (!empty($postData)) {
        if ($json) {
            $data = json_encode($postData);
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        }
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $isJson = false;
    $decodedResponse = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $isJson = true;
        $response = $decodedResponse;
    }

    return [
        'httpCode' => $httpCode,
        'response' => $response,
        'error' => $error,
        'is_json' => $isJson
    ];
}

function updateMercadoLibreItemStatus($itemId, $status, $accessToken) {
    $url = 'https://api.mercadolibre.com/items/' . $itemId;
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    $payload = ['status' => $status];

    return makeCurlRequest($url, 'PUT', $headers, $payload, true);
}
