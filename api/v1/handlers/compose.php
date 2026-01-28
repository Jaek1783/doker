<?php
/**
 * Docker Compose API Handler
 */

function handleCompose($method, $project = null, $action = null) {
    switch ($method) {
        case 'GET':
            if ($project && $action === 'status') {
                getProjectStatus($project);
            } elseif ($action === 'projects' || $project === 'projects') {
                listProjects();
            } else {
                listProjects();
            }
            break;

        case 'POST':
            if ($action === 'up' || $project === 'up') {
                composeUp();
            } elseif ($action === 'down' || $project === 'down') {
                composeDown();
            } else {
                errorResponse('Invalid action', 400);
            }
            break;

        default:
            errorResponse('Method not allowed', 405);
    }
}

function execCompose($command) {
    $output = [];
    $returnVar = 0;
    exec("docker compose $command 2>&1", $output, $returnVar);
    return [
        'success' => $returnVar === 0,
        'output' => $output,
        'exitCode' => $returnVar
    ];
}

function listProjects() {
    $result = execCompose("ls --format json");
    
    if (!$result['success']) {
        // docker compose가 없는 경우
        successResponse([
            'projects' => [],
            'message' => 'No compose projects found or docker compose not available'
        ]);
        return;
    }
    
    $output = implode('', $result['output']);
    $projects = json_decode($output, true);
    
    successResponse([
        'projects' => $projects ?? []
    ]);
}

function getProjectStatus($project) {
    $project = escapeshellarg($project);
    $result = execCompose("-p $project ps --format json");
    
    if (!$result['success']) {
        errorResponse("Project not found or error: $project", 404);
    }
    
    $services = [];
    foreach ($result['output'] as $line) {
        if ($line) {
            $service = json_decode($line, true);
            if ($service) {
                $services[] = $service;
            }
        }
    }
    
    successResponse([
        'project' => trim($project, "'"),
        'services' => $services
    ]);
}

function composeUp() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['path'])) {
        errorResponse('Compose file path is required', 400);
    }
    
    $path = escapeshellarg($input['path']);
    $detached = isset($input['detached']) && $input['detached'] === false ? '' : '-d';
    $build = isset($input['build']) && $input['build'] === true ? '--build' : '';
    
    $result = execCompose("-f $path up $detached $build");
    
    if (!$result['success']) {
        errorResponse('Failed to start compose project: ' . implode(' ', $result['output']), 500);
    }
    
    successResponse([
        'message' => 'Compose project started',
        'output' => $result['output']
    ]);
}

function composeDown() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['path'])) {
        errorResponse('Compose file path is required', 400);
    }
    
    $path = escapeshellarg($input['path']);
    $volumes = isset($input['volumes']) && $input['volumes'] === true ? '-v' : '';
    $removeOrphans = isset($input['remove_orphans']) && $input['remove_orphans'] === true ? '--remove-orphans' : '';
    
    $result = execCompose("-f $path down $volumes $removeOrphans");
    
    if (!$result['success']) {
        errorResponse('Failed to stop compose project: ' . implode(' ', $result['output']), 500);
    }
    
    successResponse([
        'message' => 'Compose project stopped',
        'output' => $result['output']
    ]);
}
