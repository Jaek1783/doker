<?php
/**
 * Volumes API Handler
 */

function handleVolumes($method, $name = null, $action = null) {
    switch ($method) {
        case 'GET':
            if ($name) {
                getVolume($name);
            } else {
                listVolumes();
            }
            break;

        case 'POST':
            if (!$name) {
                createVolume();
            } else {
                errorResponse('Invalid action', 400);
            }
            break;

        case 'DELETE':
            if ($name) {
                deleteVolume($name);
            } else {
                errorResponse('Volume name required', 400);
            }
            break;

        default:
            errorResponse('Method not allowed', 405);
    }
}

function listVolumes() {
    $result = execDocker("volume ls --format '{{json .}}'");
    
    $volumes = [];
    foreach ($result['output'] as $line) {
        if ($line) {
            $volume = json_decode($line, true);
            if ($volume) {
                $volumes[] = $volume;
            }
        }
    }
    
    successResponse($volumes);
}

function getVolume($name) {
    $result = execDocker("volume inspect $name");
    
    if (!$result['success']) {
        errorResponse("Volume not found: $name", 404);
    }
    
    $data = json_decode(implode('', $result['output']), true);
    successResponse($data[0] ?? null);
}

function createVolume() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $name = isset($input['name']) ? escapeshellarg($input['name']) : '';
    $driver = isset($input['driver']) ? '--driver ' . escapeshellarg($input['driver']) : '';
    
    $result = execDocker("volume create $driver $name");
    
    if (!$result['success']) {
        errorResponse('Failed to create volume: ' . implode(' ', $result['output']), 500);
    }
    
    $volumeName = trim($result['output'][0] ?? $input['name'] ?? '');
    successResponse([
        'name' => $volumeName,
        'message' => 'Volume created successfully'
    ]);
}

function deleteVolume($name) {
    $force = isset($_GET['force']) && $_GET['force'] === 'true' ? '-f' : '';
    $result = execDocker("volume rm $force $name");
    
    if (!$result['success']) {
        errorResponse("Failed to delete volume: $name", 500);
    }
    
    successResponse(['message' => "Volume $name deleted"]);
}
