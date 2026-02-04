<?php
/**
 * 테넌트(사이트) 관리 유틸리티
 */

require_once __DIR__ . '/../../config/database.php';

/**
 * 랜덤 site_id 생성
 *
 * @return string
 */
function generateSiteId(): string
{
    // 영문 소문자 + 숫자 조합의 짧은 ID
    return 'site_' . bin2hex(random_bytes(6));
}

/**
 * 랜덤 API 키 생성
 *
 * @return string
 */
function generateSiteApiKey(): string
{
    return bin2hex(random_bytes(32));
}

/**
 * API 키를 저장용 해시로 변환 (SHA-256)
 *
 * @param string $apiKey
 * @return string
 */
function hashApiKey(string $apiKey): string
{
    return hash('sha256', $apiKey);
}

/**
 * 사이트용 DB 이름 생성
 *
 * @param string $siteId
 * @return string
 */
function generateSiteDbName(string $siteId): string
{
    // site_ 접두어 제거 후 사용
    $suffix = preg_replace('/^site_/', '', $siteId);
    return 'payment_site_' . $suffix;
}

/**
 * 사이트 전용 DB 생성
 *
 * @param string $dbName
 * @return bool
 */
function createSiteDatabase(string $dbName): bool
{
    $pdo = getDbConnection();
    if (!$pdo) {
        return false;
    }

    try {
        // DB명은 따옴표 없이 사용하되, 알파벳/숫자/_ 만 허용
        if (!preg_match('/^[A-Za-z0-9_]+$/', $dbName)) {
            throw new RuntimeException('Invalid database name');
        }
        $pdo->exec("CREATE DATABASE IF NOT EXISTS {$dbName} DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_unicode_ci");
        return true;
    } catch (Throwable $e) {
        if (API_DEBUG) {
            error_log('Failed to create site database: ' . $e->getMessage());
        }
        return false;
    }
}

/**
 * 지정된 DB에 사이트 스키마 적용
 *
 * @param string $dbName
 * @return bool
 */
function applySiteSchema(string $dbName): bool
{
    $schemaPath = __DIR__ . '/../../db/site_schema.sql';
    if (!file_exists($schemaPath)) {
        if (API_DEBUG) {
            error_log("Site schema file not found: {$schemaPath}");
        }
        return false;
    }

    $sql = file_get_contents($schemaPath);
    if ($sql === false) {
        return false;
    }

    global $dbConfig;

    try {
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbName};charset={$dbConfig['charset']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);

        // 여러 스테이트먼트를 순차 실행
        $statements = array_filter(array_map('trim', explode(";\n", $sql)));
        foreach ($statements as $statement) {
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
        }

        return true;
    } catch (Throwable $e) {
        if (API_DEBUG) {
            error_log('Failed to apply site schema: ' . $e->getMessage());
        }
        return false;
    }
}

