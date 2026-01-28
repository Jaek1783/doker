<?php
/**
 * Docker Management & Payment API - Router
 * 
 * API v1 메인 라우터
 * - Docker 관리 API
 * - 포트원 결제 API
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// OPTIONS 요청 처리 (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 설정 로드
$configPath = __DIR__ . '/../../config/database.php';
if (file_exists($configPath)) {
    require_once $configPath;
}

// 요청 정보 파싱
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];
$basePath = '/api/v1';

// URI에서 base path 제거
$path = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace($basePath, '', $path);
$path = trim($path, '/');
$segments = $path ? explode('/', $path) : [];

// JSON 응답 헬퍼 함수
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

function errorResponse($message, $statusCode = 400, $code = null) {
    jsonResponse([
        'status' => 'error',
        'error' => [
            'code' => $code ?? $statusCode,
            'message' => $message
        ]
    ], $statusCode);
}

function successResponse($data, $message = null) {
    $response = ['status' => 'success', 'data' => $data];
    if ($message) {
        $response['message'] = $message;
    }
    jsonResponse($response);
}

// Docker 명령어 실행 헬퍼
function execDocker($command) {
    $output = [];
    $returnVar = 0;
    exec("docker $command 2>&1", $output, $returnVar);
    return [
        'success' => $returnVar === 0,
        'output' => $output,
        'exitCode' => $returnVar
    ];
}

// 라우팅
$resource = $segments[0] ?? '';
$resourceId = $segments[1] ?? null;
$action = $segments[2] ?? null;

switch ($resource) {
    case '':
        // API 정보
        successResponse([
            'name' => 'Docker Management & Payment API',
            'version' => '1.0.0',
            'endpoints' => [
                // Docker Management
                'containers' => '/api/v1/containers',
                'images' => '/api/v1/images',
                'volumes' => '/api/v1/volumes',
                'networks' => '/api/v1/networks',
                'system' => '/api/v1/system',
                'compose' => '/api/v1/compose',
                // Payment (PortOne)
                'payments' => '/api/v1/payments',
                'subscriptions' => '/api/v1/subscriptions',
                'webhooks' => '/api/v1/webhooks'
            ]
        ]);
        break;

    case 'containers':
        require_once __DIR__ . '/handlers/containers.php';
        handleContainers($requestMethod, $resourceId, $action);
        break;

    case 'images':
        require_once __DIR__ . '/handlers/images.php';
        handleImages($requestMethod, $resourceId, $action);
        break;

    case 'volumes':
        require_once __DIR__ . '/handlers/volumes.php';
        handleVolumes($requestMethod, $resourceId, $action);
        break;

    case 'networks':
        require_once __DIR__ . '/handlers/networks.php';
        handleNetworks($requestMethod, $resourceId, $action);
        break;

    case 'system':
        require_once __DIR__ . '/handlers/system.php';
        handleSystem($requestMethod, $resourceId, $action);
        break;

    case 'compose':
        require_once __DIR__ . '/handlers/compose.php';
        handleCompose($requestMethod, $resourceId, $action);
        break;

    // ========================================
    // Payment API (PortOne)
    // ========================================
    
    case 'payments':
        require_once __DIR__ . '/handlers/payments.php';
        handlePayments($requestMethod, $resourceId, $action);
        break;

    case 'subscriptions':
        require_once __DIR__ . '/handlers/subscriptions.php';
        handleSubscriptions($requestMethod, $resourceId, $action);
        break;

    case 'webhooks':
        require_once __DIR__ . '/handlers/webhooks.php';
        handleWebhooks($requestMethod, $resourceId, $action);
        break;

    default:
        errorResponse("Unknown resource: $resource", 404);
}
