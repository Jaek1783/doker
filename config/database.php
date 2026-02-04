<?php
/**
 * Database & API Configuration
 * 
 * Docker API 및 포트원 결제 API 설정
 * - Platform DB 연결 설정
 * - 사이트별 결제 DB 연결 설정
 * - 포트원 API 설정
 * - 기타 API 설정
 */

// ========================================
// 데이터베이스 설정 (Platform DB)
// ========================================

$dbConfig = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_NAME') ?: 'docker_api',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: '',
    'charset' => 'utf8mb4'
];

/**
 * Platform DB 연결 함수
 * 
 * @return PDO|null PDO 인스턴스 또는 연결 실패 시 null
 */
function getDbConnection(): ?PDO {
    global $dbConfig;
    
    static $pdo = null;
    
    if ($pdo !== null) {
        return $pdo;
    }
    
    try {
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        return $pdo;
    } catch (PDOException $e) {
        if (defined('API_DEBUG') && API_DEBUG) {
            error_log('Platform DB connection failed: ' . $e->getMessage());
        }
        return null;
    }
}

/**
 * 사이트 전용 DB에 연결
 * 
 * @param string $dbName 사이트 전용 DB 이름
 * @return PDO|null
 */
function getSiteDbConnectionByDbName(string $dbName): ?PDO {
    global $dbConfig;

    static $sitePdos = [];

    if (isset($sitePdos[$dbName])) {
        return $sitePdos[$dbName];
    }

    try {
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbName};charset={$dbConfig['charset']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        $sitePdos[$dbName] = $pdo;
        return $pdo;
    } catch (PDOException $e) {
        if (defined('API_DEBUG') && API_DEBUG) {
            error_log('Site DB connection failed: ' . $e->getMessage());
        }
        return null;
    }
}

/**
 * site_id로 사이트 DB에 연결
 * 
 * @param string $siteId
 * @return PDO|null
 */
function getSiteDbConnectionBySiteId(string $siteId): ?PDO {
    $platformPdo = getDbConnection();
    if (!$platformPdo) {
        return null;
    }

    $stmt = $platformPdo->prepare('SELECT db_name FROM sites WHERE site_id = ? AND status = "active" LIMIT 1');
    $stmt->execute([$siteId]);
    $row = $stmt->fetch();

    if (!$row || empty($row['db_name'])) {
        return null;
    }

    return getSiteDbConnectionByDbName($row['db_name']);
}

/**
 * 현재 요청 컨텍스트에서 사이트 DB 연결 가져오기
 * 
 * 미들웨어에서 전역 변수 CURRENT_SITE_DB_NAME 를 설정한다고 가정
 */
function getCurrentSiteDbConnection(): ?PDO {
    if (!isset($GLOBALS['CURRENT_SITE_DB_NAME'])) {
        return null;
    }
    return getSiteDbConnectionByDbName($GLOBALS['CURRENT_SITE_DB_NAME']);
}

// ========================================
// API 기본 설정
// ========================================

define('API_VERSION', '1.0.0');
define('API_NAME', 'Docker & Payment Management API');
define('API_DEBUG', getenv('API_DEBUG') === 'true');

// ========================================
// 포트원 (PortOne) 설정
// ========================================

define('PORTONE_API_KEY', getenv('PORTONE_API_KEY') ?: '');
define('PORTONE_API_SECRET', getenv('PORTONE_API_SECRET') ?: '');
define('PORTONE_IMP_CODE', getenv('PORTONE_IMP_CODE') ?: '');
define('PORTONE_WEBHOOK_ENABLED', getenv('PORTONE_WEBHOOK_ENABLED') !== 'false');

// 포트원 API 기본 URL
define('PORTONE_API_URL', 'https://api.iamport.kr');

// ========================================
// 결제 설정
// ========================================

define('PAYMENT_DEFAULT_CURRENCY', getenv('PAYMENT_DEFAULT_CURRENCY') ?: 'KRW');
define('PAYMENT_NOTIFICATION_EMAIL', getenv('PAYMENT_NOTIFICATION_EMAIL') ?: '');

// ========================================
// 은행 코드 (가상계좌용)
// ========================================

$BANK_CODES = [
    '04' => 'KB국민은행',
    '23' => 'SC제일은행',
    '39' => '경남은행',
    '34' => '광주은행',
    '03' => 'IBK기업은행',
    '11' => 'NH농협은행',
    '31' => 'DGB대구은행',
    '32' => 'BNK부산은행',
    '02' => 'KDB산업은행',
    '45' => '새마을금고',
    '07' => 'Sh수협은행',
    '88' => '신한은행',
    '48' => '신협',
    '20' => '우리은행',
    '71' => '우체국',
    '37' => '전북은행',
    '35' => '제주은행',
    '12' => '지역농축협',
    '81' => '하나은행',
    '27' => '한국씨티은행',
    '89' => '케이뱅크',
    '90' => '카카오뱅크',
    '92' => '토스뱅크'
];

/**
 * 은행 코드로 은행명 조회
 * 
 * @param string $code 은행 코드
 * @return string 은행명 또는 'Unknown'
 */
function getBankName(string $code): string {
    global $BANK_CODES;
    return $BANK_CODES[$code] ?? 'Unknown';
}

/**
 * 포트원 설정 확인
 * 
 * @return bool 포트원 API 키가 설정되어 있으면 true
 */
function isPortOneConfigured(): bool {
    return !empty(PORTONE_API_KEY) && !empty(PORTONE_API_SECRET);
}
