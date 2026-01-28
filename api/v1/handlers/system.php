<?php
/**
 * System API Handler
 */

function handleSystem($method, $action = null, $subAction = null) {
    if ($method !== 'GET' && $method !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    switch ($action) {
        case 'info':
            getSystemInfo();
            break;

        case 'version':
            getDockerVersion();
            break;

        case 'df':
            getDiskUsage();
            break;

        case 'prune':
            if ($method === 'POST') {
                pruneSystem();
            } else {
                errorResponse('POST method required', 405);
            }
            break;

        default:
            // 기본: API 상태 반환
            successResponse([
                'status' => 'online',
                'timestamp' => date('c'),
                'endpoints' => [
                    '/system/info' => 'Docker 시스템 정보',
                    '/system/version' => 'Docker 버전',
                    '/system/df' => '디스크 사용량',
                    '/system/prune' => '미사용 리소스 정리 (POST)'
                ]
            ]);
    }
}

function getSystemInfo() {
    $result = execDocker("info --format '{{json .}}'");
    
    if (!$result['success']) {
        // Docker가 없거나 접근할 수 없는 경우 기본 정보 반환
        successResponse([
            'docker_available' => false,
            'message' => 'Docker daemon is not accessible',
            'server_info' => [
                'php_version' => phpversion(),
                'server_time' => date('c'),
                'os' => php_uname()
            ]
        ]);
        return;
    }
    
    $info = json_decode($result['output'][0] ?? '{}', true);
    successResponse($info);
}

function getDockerVersion() {
    $result = execDocker("version --format '{{json .}}'");
    
    if (!$result['success']) {
        successResponse([
            'docker_available' => false,
            'message' => 'Docker is not installed or not accessible',
            'api_version' => '1.0.0'
        ]);
        return;
    }
    
    $version = json_decode($result['output'][0] ?? '{}', true);
    successResponse($version);
}

function getDiskUsage() {
    $result = execDocker("system df --format '{{json .}}'");
    
    if (!$result['success']) {
        errorResponse('Failed to get disk usage', 500);
    }
    
    $usage = [];
    foreach ($result['output'] as $line) {
        if ($line) {
            $item = json_decode($line, true);
            if ($item) {
                $usage[] = $item;
            }
        }
    }
    
    successResponse([
        'disk_usage' => $usage
    ]);
}

function pruneSystem() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $pruneAll = isset($input['all']) && $input['all'] === true;
    $volumes = isset($input['volumes']) && $input['volumes'] === true ? '--volumes' : '';
    
    $result = execDocker("system prune -f $volumes");
    
    if (!$result['success']) {
        errorResponse('Failed to prune system', 500);
    }
    
    successResponse([
        'message' => 'System pruned successfully',
        'output' => $result['output']
    ]);
}
