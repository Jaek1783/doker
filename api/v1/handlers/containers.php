<?php
/**
 * Containers API Handler
 */

function handleContainers($method, $id = null, $action = null) {
    switch ($method) {
        case 'GET':
            if ($id && $action === 'logs') {
                getContainerLogs($id);
            } elseif ($id && $action === 'stats') {
                getContainerStats($id);
            } elseif ($id) {
                getContainer($id);
            } else {
                listContainers();
            }
            break;

        case 'POST':
            if ($id && $action === 'start') {
                startContainer($id);
            } elseif ($id && $action === 'stop') {
                stopContainer($id);
            } elseif ($id && $action === 'restart') {
                restartContainer($id);
            } elseif (!$id) {
                createContainer();
            } else {
                errorResponse('Invalid action', 400);
            }
            break;

        case 'DELETE':
            if ($id) {
                deleteContainer($id);
            } else {
                errorResponse('Container ID required', 400);
            }
            break;

        default:
            errorResponse('Method not allowed', 405);
    }
}

function listContainers() {
    $all = isset($_GET['all']) && $_GET['all'] === 'true' ? '-a' : '';
    $result = execDocker("ps $all --format '{{json .}}'");
    
    if (!$result['success'] && empty($result['output'])) {
        successResponse([]);
        return;
    }
    
    $containers = [];
    foreach ($result['output'] as $line) {
        if ($line) {
            $container = json_decode($line, true);
            if ($container) {
                $containers[] = $container;
            }
        }
    }
    
    successResponse($containers);
}

function getContainer($id) {
    $result = execDocker("inspect $id");
    
    if (!$result['success']) {
        errorResponse("Container not found: $id", 404);
    }
    
    $data = json_decode(implode('', $result['output']), true);
    successResponse($data[0] ?? null);
}

function getContainerLogs($id) {
    $tail = isset($_GET['tail']) ? (int)$_GET['tail'] : 100;
    $result = execDocker("logs --tail $tail $id");
    
    successResponse([
        'container' => $id,
        'logs' => $result['output']
    ]);
}

function getContainerStats($id) {
    $result = execDocker("stats --no-stream --format '{{json .}}' $id");
    
    if (!$result['success']) {
        errorResponse("Failed to get stats for container: $id", 500);
    }
    
    $stats = json_decode($result['output'][0] ?? '{}', true);
    successResponse($stats);
}

function createContainer() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['image'])) {
        errorResponse('Image is required', 400);
    }
    
    $image = escapeshellarg($input['image']);
    $name = isset($input['name']) ? '--name ' . escapeshellarg($input['name']) : '';
    $ports = '';
    $envVars = '';
    $volumes = '';
    $detach = '-d';
    
    // 포트 매핑
    if (isset($input['ports']) && is_array($input['ports'])) {
        foreach ($input['ports'] as $port) {
            $ports .= ' -p ' . escapeshellarg($port);
        }
    }
    
    // 환경 변수
    if (isset($input['env']) && is_array($input['env'])) {
        foreach ($input['env'] as $key => $value) {
            $envVars .= ' -e ' . escapeshellarg("$key=$value");
        }
    }
    
    // 볼륨 마운트
    if (isset($input['volumes']) && is_array($input['volumes'])) {
        foreach ($input['volumes'] as $vol) {
            $volumes .= ' -v ' . escapeshellarg($vol);
        }
    }
    
    $result = execDocker("run $detach $name $ports $envVars $volumes $image");
    
    if (!$result['success']) {
        errorResponse('Failed to create container: ' . implode(' ', $result['output']), 500);
    }
    
    $containerId = trim($result['output'][0] ?? '');
    successResponse([
        'id' => $containerId,
        'message' => 'Container created successfully'
    ]);
}

function startContainer($id) {
    $result = execDocker("start $id");
    
    if (!$result['success']) {
        errorResponse("Failed to start container: $id", 500);
    }
    
    successResponse(['message' => "Container $id started"]);
}

function stopContainer($id) {
    $result = execDocker("stop $id");
    
    if (!$result['success']) {
        errorResponse("Failed to stop container: $id", 500);
    }
    
    successResponse(['message' => "Container $id stopped"]);
}

function restartContainer($id) {
    $result = execDocker("restart $id");
    
    if (!$result['success']) {
        errorResponse("Failed to restart container: $id", 500);
    }
    
    successResponse(['message' => "Container $id restarted"]);
}

function deleteContainer($id) {
    $force = isset($_GET['force']) && $_GET['force'] === 'true' ? '-f' : '';
    $result = execDocker("rm $force $id");
    
    if (!$result['success']) {
        errorResponse("Failed to delete container: $id", 500);
    }
    
    successResponse(['message' => "Container $id deleted"]);
}
