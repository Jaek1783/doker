<?php
/**
 * Sites Handler
 *
 * 멀티사이트 SaaS 결제 플랫폼용 사이트 등록/조회 핸들러
 */

require_once __DIR__ . '/../lib/Tenant.php';

/**
 * 사이트 API 핸들러
 *
 * @param string      $method
 * @param string|null $resourceId
 * @param string|null $action
 */
function handleSites(string $method, ?string $resourceId, ?string $action): void
{
    switch ($method) {
        case 'POST':
            if ($resourceId === 'register' || $resourceId === null || $resourceId === '') {
                handleRegisterSite();
                return;
            }
            errorResponse('Unknown sites action', 404);
            break;

        case 'GET':
            // 간단한 사이트 목록 (플랫폼 관리용)
            if ($resourceId === null || $resourceId === '') {
                handleListSites();
                return;
            }
            errorResponse('Unknown sites action', 404);
            break;

        default:
            errorResponse('Method not allowed', 405);
    }
}

/**
 * 플랫폼 관리자 인증 (간단 버전)
 *
 * 헤더 X-Platform-Admin-Key 와 환경변수 PLATFORM_ADMIN_KEY 비교
 */
function requirePlatformAdmin(): void
{
    $expected = getenv('PLATFORM_ADMIN_KEY') ?: '';
    if ($expected === '') {
        // 설정이 없으면 모든 요청 허용 (개발용)
        return;
    }

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $provided = $headers['X-Platform-Admin-Key'] ?? '';

    if (!$provided || !hash_equals($expected, $provided)) {
        errorResponse('Forbidden (platform admin key required)', 403);
    }
}

/**
 * 사이트 등록
 *
 * POST /api/v1/sites 또는 POST /api/v1/sites/register
 */
function handleRegisterSite(): void
{
    requirePlatformAdmin();

    $platformPdo = getDbConnection();
    if (!$platformPdo) {
        errorResponse('Platform database not available', 500);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    $name = trim($input['name'] ?? '');
    $domain = trim($input['domain'] ?? '');
    $contactEmail = trim($input['contact_email'] ?? '');

    if ($name === '') {
        errorResponse('name is required', 400);
    }

    // site_id 및 DB 이름 생성
    $siteId = generateSiteId();
    $dbName = generateSiteDbName($siteId);

    // 사이트 레코드 생성 (트랜잭션)
    try {
        $platformPdo->beginTransaction();

        $stmt = $platformPdo->prepare('
            INSERT INTO sites (site_id, name, domain, contact_email, db_name, status, created_at)
            VALUES (?, ?, ?, ?, ?, "active", NOW())
        ');
        $stmt->execute([$siteId, $name, $domain ?: null, $contactEmail ?: null, $dbName]);

        // 사이트용 DB 생성 및 스키마 적용
        if (!createSiteDatabase($dbName)) {
            $platformPdo->rollBack();
            errorResponse('Failed to create site database', 500);
        }
        if (!applySiteSchema($dbName)) {
            $platformPdo->rollBack();
            errorResponse('Failed to apply site schema', 500);
        }

        // API 키 생성
        $apiKey = generateSiteApiKey();
        $apiKeyHash = hashApiKey($apiKey);
        $stmt = $platformPdo->prepare('
            INSERT INTO site_api_keys (site_id, api_key_hash, name, status, created_at)
            VALUES (?, ?, "default", "active", NOW())
        ');
        $stmt->execute([$siteId, $apiKeyHash]);

        $platformPdo->commit();
    } catch (Throwable $e) {
        if ($platformPdo->inTransaction()) {
            $platformPdo->rollBack();
        }
        if (API_DEBUG) {
            error_log('Failed to register site: ' . $e->getMessage());
        }
        errorResponse('Failed to register site', 500);
    }

    $dashboardBase = rtrim((getenv('DASHBOARD_BASE_URL') ?: ''), '/');
    if ($dashboardBase === '') {
        // 현재 호스트 기준 기본값 추정
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dashboardBase = $scheme . '://' . $host . '/dashboard';
    }

    $data = [
        'site_id' => $siteId,
        'db_name' => $dbName,
        'api_key' => $apiKey, // 원본은 이 응답에서만 노출
        'dashboard_url' => $dashboardBase . '?site_id=' . urlencode($siteId)
    ];

    successResponse($data, 'Site registered successfully');
}

/**
 * 사이트 목록 조회 (플랫폼 관리자용 간단 엔드포인트)
 *
 * GET /api/v1/sites
 */
function handleListSites(): void
{
    requirePlatformAdmin();

    $platformPdo = getDbConnection();
    if (!$platformPdo) {
        errorResponse('Platform database not available', 500);
    }

    $stmt = $platformPdo->query('
        SELECT site_id, name, domain, status, db_name, created_at
        FROM sites
        ORDER BY created_at DESC
        LIMIT 100
    ');
    $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

    successResponse(['sites' => $sites]);
}

