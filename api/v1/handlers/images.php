<?php
/**
 * Images API Handler
 */

function handleImages($method, $id = null, $action = null) {
    switch ($method) {
        case 'GET':
            if ($id) {
                getImage($id);
            } else {
                listImages();
            }
            break;

        case 'POST':
            if ($action === 'pull' || $id === 'pull') {
                pullImage();
            } elseif ($action === 'build' || $id === 'build') {
                buildImage();
            } else {
                errorResponse('Invalid action', 400);
            }
            break;

        case 'DELETE':
            if ($id) {
                deleteImage($id);
            } else {
                errorResponse('Image ID required', 400);
            }
            break;

        default:
            errorResponse('Method not allowed', 405);
    }
}

function listImages() {
    $result = execDocker("images --format '{{json .}}'");
    
    $images = [];
    foreach ($result['output'] as $line) {
        if ($line) {
            $image = json_decode($line, true);
            if ($image) {
                $images[] = $image;
            }
        }
    }
    
    successResponse($images);
}

function getImage($id) {
    $result = execDocker("inspect $id");
    
    if (!$result['success']) {
        errorResponse("Image not found: $id", 404);
    }
    
    $data = json_decode(implode('', $result['output']), true);
    successResponse($data[0] ?? null);
}

function pullImage() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['image'])) {
        errorResponse('Image name is required', 400);
    }
    
    $image = escapeshellarg($input['image']);
    $result = execDocker("pull $image");
    
    if (!$result['success']) {
        errorResponse('Failed to pull image: ' . implode(' ', $result['output']), 500);
    }
    
    successResponse([
        'image' => $input['image'],
        'message' => 'Image pulled successfully',
        'output' => $result['output']
    ]);
}

function buildImage() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['context'])) {
        errorResponse('Build context path is required', 400);
    }
    
    $context = escapeshellarg($input['context']);
    $tag = isset($input['tag']) ? '-t ' . escapeshellarg($input['tag']) : '';
    $dockerfile = isset($input['dockerfile']) ? '-f ' . escapeshellarg($input['dockerfile']) : '';
    
    $result = execDocker("build $tag $dockerfile $context");
    
    if (!$result['success']) {
        errorResponse('Failed to build image: ' . implode(' ', $result['output']), 500);
    }
    
    successResponse([
        'message' => 'Image built successfully',
        'output' => $result['output']
    ]);
}

function deleteImage($id) {
    $force = isset($_GET['force']) && $_GET['force'] === 'true' ? '-f' : '';
    $result = execDocker("rmi $force $id");
    
    if (!$result['success']) {
        errorResponse("Failed to delete image: $id", 500);
    }
    
    successResponse(['message' => "Image $id deleted"]);
}
