<?php
/**
 * Docker API Server - Documentation Page
 * 
 * 이 페이지는 Docker 관리용 API 서버의 문서 페이지입니다.
 */

// API 정보 정의
$apiInfo = [
    'name' => 'Docker Management API',
    'version' => '1.0.0',
    'description' => 'Docker 컨테이너, 이미지, 볼륨, 네트워크를 관리하기 위한 RESTful API',
    'base_url' => '/api/v1',
    'endpoints' => [
        [
            'category' => 'Containers',
            'icon' => '📦',
            'routes' => [
                ['method' => 'GET', 'path' => '/containers', 'description' => '모든 컨테이너 목록 조회'],
                ['method' => 'GET', 'path' => '/containers/{id}', 'description' => '특정 컨테이너 상세 정보'],
                ['method' => 'POST', 'path' => '/containers', 'description' => '새 컨테이너 생성'],
                ['method' => 'POST', 'path' => '/containers/{id}/start', 'description' => '컨테이너 시작'],
                ['method' => 'POST', 'path' => '/containers/{id}/stop', 'description' => '컨테이너 중지'],
                ['method' => 'POST', 'path' => '/containers/{id}/restart', 'description' => '컨테이너 재시작'],
                ['method' => 'DELETE', 'path' => '/containers/{id}', 'description' => '컨테이너 삭제'],
                ['method' => 'GET', 'path' => '/containers/{id}/logs', 'description' => '컨테이너 로그 조회'],
                ['method' => 'GET', 'path' => '/containers/{id}/stats', 'description' => '컨테이너 리소스 사용량'],
            ]
        ],
        [
            'category' => 'Images',
            'icon' => '🖼️',
            'routes' => [
                ['method' => 'GET', 'path' => '/images', 'description' => '모든 이미지 목록 조회'],
                ['method' => 'GET', 'path' => '/images/{id}', 'description' => '특정 이미지 상세 정보'],
                ['method' => 'POST', 'path' => '/images/pull', 'description' => '이미지 Pull'],
                ['method' => 'DELETE', 'path' => '/images/{id}', 'description' => '이미지 삭제'],
                ['method' => 'POST', 'path' => '/images/build', 'description' => 'Dockerfile로 이미지 빌드'],
            ]
        ],
        [
            'category' => 'Volumes',
            'icon' => '💾',
            'routes' => [
                ['method' => 'GET', 'path' => '/volumes', 'description' => '모든 볼륨 목록 조회'],
                ['method' => 'POST', 'path' => '/volumes', 'description' => '새 볼륨 생성'],
                ['method' => 'GET', 'path' => '/volumes/{name}', 'description' => '특정 볼륨 상세 정보'],
                ['method' => 'DELETE', 'path' => '/volumes/{name}', 'description' => '볼륨 삭제'],
            ]
        ],
        [
            'category' => 'Networks',
            'icon' => '🌐',
            'routes' => [
                ['method' => 'GET', 'path' => '/networks', 'description' => '모든 네트워크 목록 조회'],
                ['method' => 'POST', 'path' => '/networks', 'description' => '새 네트워크 생성'],
                ['method' => 'GET', 'path' => '/networks/{id}', 'description' => '특정 네트워크 상세 정보'],
                ['method' => 'DELETE', 'path' => '/networks/{id}', 'description' => '네트워크 삭제'],
                ['method' => 'POST', 'path' => '/networks/{id}/connect', 'description' => '컨테이너를 네트워크에 연결'],
                ['method' => 'POST', 'path' => '/networks/{id}/disconnect', 'description' => '컨테이너를 네트워크에서 분리'],
            ]
        ],
        [
            'category' => 'System',
            'icon' => '⚙️',
            'routes' => [
                ['method' => 'GET', 'path' => '/system/info', 'description' => 'Docker 시스템 정보'],
                ['method' => 'GET', 'path' => '/system/version', 'description' => 'Docker 버전 정보'],
                ['method' => 'GET', 'path' => '/system/df', 'description' => '디스크 사용량 정보'],
                ['method' => 'POST', 'path' => '/system/prune', 'description' => '사용하지 않는 리소스 정리'],
            ]
        ],
        [
            'category' => 'Compose',
            'icon' => '🚀',
            'routes' => [
                ['method' => 'GET', 'path' => '/compose/projects', 'description' => 'Compose 프로젝트 목록'],
                ['method' => 'POST', 'path' => '/compose/up', 'description' => 'Compose 프로젝트 시작'],
                ['method' => 'POST', 'path' => '/compose/down', 'description' => 'Compose 프로젝트 중지'],
                ['method' => 'GET', 'path' => '/compose/{project}/status', 'description' => '프로젝트 상태 조회'],
            ]
        ],
    ]
];

// 서버 상태 확인
$serverStatus = [
    'api' => 'online',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
];

// JSON 요청인 경우 JSON으로 응답
if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'data' => $apiInfo,
        'server' => $serverStatus
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// 메소드 색상 지정
function getMethodColor($method) {
    $colors = [
        'GET' => '#61affe',
        'POST' => '#49cc90',
        'PUT' => '#fca130',
        'PATCH' => '#50e3c2',
        'DELETE' => '#f93e3e',
    ];
    return $colors[$method] ?? '#999';
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($apiInfo['name']) ?> - Documentation</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0d1117;
            --bg-secondary: #161b22;
            --bg-tertiary: #21262d;
            --text-primary: #f0f6fc;
            --text-secondary: #8b949e;
            --border-color: #30363d;
            --accent-blue: #58a6ff;
            --accent-green: #3fb950;
            --accent-purple: #a371f7;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Header */
        .header {
            text-align: center;
            padding: 4rem 2rem;
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 3rem;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header .version {
            display: inline-block;
            background: var(--accent-green);
            color: var(--bg-primary);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .header .description {
            color: var(--text-secondary);
            font-size: 1.125rem;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Status Bar */
        .status-bar {
            display: flex;
            justify-content: center;
            gap: 2rem;
            padding: 1rem;
            background: var(--bg-secondary);
            border-radius: 12px;
            margin-bottom: 3rem;
            flex-wrap: wrap;
        }

        .status-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent-green);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Base URL */
        .base-url {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem 1.5rem;
            margin-bottom: 3rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .base-url label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        .base-url code {
            font-family: 'JetBrains Mono', monospace;
            background: var(--bg-tertiary);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            color: var(--accent-blue);
        }

        /* Category */
        .category {
            margin-bottom: 2.5rem;
        }

        .category-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        .category-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .category-icon {
            font-size: 1.5rem;
        }

        /* Endpoint */
        .endpoint {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 0.75rem;
            overflow: hidden;
            transition: all 0.2s ease;
        }

        .endpoint:hover {
            border-color: var(--accent-blue);
            transform: translateX(4px);
        }

        .endpoint-content {
            display: flex;
            align-items: center;
            padding: 1rem 1.25rem;
            gap: 1rem;
        }

        .method {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.375rem 0.75rem;
            border-radius: 4px;
            min-width: 70px;
            text-align: center;
            color: white;
        }

        .path {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.875rem;
            color: var(--text-primary);
            flex: 1;
        }

        .path .param {
            color: var(--accent-purple);
        }

        .description {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 3rem 2rem;
            border-top: 1px solid var(--border-color);
            margin-top: 3rem;
            color: var(--text-secondary);
        }

        .footer a {
            color: var(--accent-blue);
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        /* Quick Links */
        .quick-links {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .quick-link {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .quick-link:hover {
            background: var(--accent-blue);
            border-color: var(--accent-blue);
            color: white;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.75rem;
            }

            .endpoint-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .status-bar {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <span class="version">v<?= htmlspecialchars($apiInfo['version']) ?></span>
        <h1><?= htmlspecialchars($apiInfo['name']) ?></h1>
        <p class="description"><?= htmlspecialchars($apiInfo['description']) ?></p>
        
        <div class="quick-links">
            <a href="/api/v1/system/info" class="quick-link">시스템 정보</a>
            <a href="/api/v1/containers" class="quick-link">컨테이너 목록</a>
            <a href="/api/v1/images" class="quick-link">이미지 목록</a>
        </div>
    </header>

    <div class="container">
        <div class="status-bar">
            <div class="status-item">
                <span class="status-dot"></span>
                <span>API Status: <strong><?= htmlspecialchars($serverStatus['api']) ?></strong></span>
            </div>
            <div class="status-item">
                <span>PHP: <strong><?= htmlspecialchars($serverStatus['php_version']) ?></strong></span>
            </div>
            <div class="status-item">
                <span>Last Updated: <strong><?= htmlspecialchars($serverStatus['timestamp']) ?></strong></span>
            </div>
        </div>

        <div class="base-url">
            <label>Base URL:</label>
            <code><?= htmlspecialchars($apiInfo['base_url']) ?></code>
        </div>

        <?php foreach ($apiInfo['endpoints'] as $category): ?>
        <div class="category">
            <div class="category-header">
                <span class="category-icon"><?= $category['icon'] ?></span>
                <h2><?= htmlspecialchars($category['category']) ?></h2>
            </div>
            
            <?php foreach ($category['routes'] as $route): ?>
            <div class="endpoint">
                <div class="endpoint-content">
                    <span class="method" style="background-color: <?= getMethodColor($route['method']) ?>">
                        <?= htmlspecialchars($route['method']) ?>
                    </span>
                    <span class="path">
                        <?= preg_replace('/\{([^}]+)\}/', '<span class="param">{$1}</span>', htmlspecialchars($apiInfo['base_url'] . $route['path'])) ?>
                    </span>
                    <span class="description"><?= htmlspecialchars($route['description']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <footer class="footer">
        <p>Docker Management API Server</p>
        <p>Built with PHP <?= phpversion() ?></p>
    </footer>
</body>
</html>
