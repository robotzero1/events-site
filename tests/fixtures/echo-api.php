<?php

// A stand-in for the API's web server in `CurlApiTest`, run with PHP's own server: it answers with what it was
// sent, or as the API does when something is not there or goes wrong.

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch ($path) {
    case '/api/echo':
        header('Content-Type: application/json');
        echo json_encode([
            'accept' => $_SERVER['HTTP_ACCEPT'] ?? null,
            'language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null,
            'forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            'host' => $_SERVER['HTTP_HOST'] ?? null,
            'query' => $_SERVER['QUERY_STRING'] ?? '',
        ]);
        break;
    case '/api/missing':
        http_response_code(404);
        echo json_encode(['message' => 'Not found.']);
        break;
    case '/api/refused':
        http_response_code(422);
        echo json_encode(['message' => 'The from field must match the format Y-m-d.', 'errors' => ['from' => ['The from field must match the format Y-m-d.']]]);
        break;
    case '/api/too-often':
        http_response_code(429);
        echo json_encode(['message' => 'Too Many Attempts.']);
        break;
    case '/api/text':
        echo 'Not JSON at all';
        break;
    case '/api/slow':
        sleep(3);
        echo '{}';
        break;
    default:
        http_response_code(500);
        echo 'Server error';
}
