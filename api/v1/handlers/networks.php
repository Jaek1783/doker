<?php
/**
 * Networks API Handler
 */

function handleNetworks($method, $id = null, $action = null) {
    switch ($method) {
        case 'GET':
            if ($id) {
                getNetwork($id);
            } else {
                listNetworks();
            }
            break;

        case 'POST':
            if ($id && $action === 'connect') {
                connectNetwork($id);
            } elseif ($id && $action === 'disconnect') {
                disconnectNetwork($id);
            } elseif (!$id) {
                createNetwork();
            } else {
                errorResponse('Invalid action', 400);
            }
            break;

        case 'DELETE':
            if ($id) {
                deleteNetwork($id);
            } else {
                errorResponse('Network ID required', 400);
            }
            break;

        default:
            errorResponse('Method not allowed', 405);
    }
}

function listNetworks() {
    $result = execDocker("network ls --format '{{json .}}'");
    
    $networks = [];
    foreach ($result['output'] as $line) {
        if ($line) {
            $network = json_decode($line, true);
            if ($network) {
                $networks[] = $network;
            }
        }
    }
    
    successResponse($networks);
}

function getNetwork($id) {
    $result = execDocker("network inspect $id");
    
    if (!$result['success']) {
        errorResponse("Network not found: $id", 404);
    }
    
    $data = json_decode(implode('', $result['output']), true);
    successResponse($data[0] ?? null);
}

function createNetwork() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['name'])) {
        errorResponse('Network name is required', 400);
    }
    
    $name = escapeshellarg($input['name']);
    $driver = isset($input['driver']) ? '--driver ' . escapeshellarg($input['driver']) : '';
    $subnet = isset($input['subnet']) ? '--subnet ' . escapeshellarg($input['subnet']) : '';
    
    $result = execDocker("network create $driver $subnet $name");
    
    if (!$result['success']) {
        errorResponse('Failed to create network: ' . implode(' ', $result['output']), 500);
    }
    
    $networkId = trim($result['output'][0] ?? '');
    successResponse([
        'id' => $networkId,
        'name' => $input['name'],
        'message' => 'Network created successfully'
    ]);
}

function connectNetwork($networkId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['container'])) {
        errorResponse('Container ID is required', 400);
    }
    
    $container = escapeshellarg($input['container']);
    $result = execDocker("network connect $networkId $container");
    
    if (!$result['success']) {
        errorResponse('Failed to connect container to network', 500);
    }
    
    successResponse(['message' => "Container connected to network $networkId"]);
}

function disconnectNetwork($networkId) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['container'])) {
        errorResponse('Container ID is required', 400);
    }
    
    $container = escapeshellarg($input['container']);
    $result = execDocker("network disconnect $networkId $container");
    
    if (!$result['success']) {
        errorResponse('Failed to disconnect container from network', 500);
    }
    
    successResponse(['message' => "Container disconnected from network $networkId"]);
}

function deleteNetwork($id) {
    $result = execDocker("network rm $id");
    
    if (!$result['success']) {
        errorResponse("Failed to delete network: $id", 500);
    }
    
    successResponse(['message' => "Network $id deleted"]);
}
