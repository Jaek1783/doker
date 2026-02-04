<?php
/**
 * Subscriptions Handler
 * 
 * 정기결제 관련 API 엔드포인트 핸들러
 * - 빌링키 발급/조회/삭제
 * - 빌링키로 결제
 * - 예약 결제 등록/조회/취소
 */

require_once __DIR__ . '/../lib/PortOne.php';

/**
 * 정기결제 API 핸들러
 * 
 * @param string $method HTTP 메서드
 * @param string|null $resourceId 리소스 타입 (billing-key, pay, schedule)
 * @param string|null $action customer_uid 또는 merchant_uid
 */
function handleSubscriptions(string $method, ?string $resourceId, ?string $action): void
{
    try {
        $portone = PortOne::fromEnv();
    } catch (Exception $e) {
        errorResponse('PortOne API not configured: ' . $e->getMessage(), 500);
    }
    
    $pdo = getCurrentSiteDbConnection();
    if (!$pdo) {
        errorResponse('Site database not available', 500);
    }
    
    // 라우팅
    switch ($resourceId) {
        case 'billing-key':
            handleBillingKey($method, $portone, $pdo, $action);
            break;
        case 'pay':
            handleSubscriptionPay($method, $portone, $pdo);
            break;
        case 'schedule':
            handleSchedule($method, $portone, $pdo, $action);
            break;
        case '':
        case null:
            // GET /subscriptions - 구독 목록
            if ($method === 'GET') {
                handleGetSubscriptions($pdo);
            } else {
                errorResponse('Method not allowed', 405);
            }
            break;
        default:
            errorResponse('Unknown resource', 404);
    }
}

// ========================================
// 빌링키 관련 핸들러
// ========================================

/**
 * 빌링키 관련 요청 처리
 */
function handleBillingKey(string $method, PortOne $portone, ?PDO $pdo, ?string $customerUid): void
{
    switch ($method) {
        case 'GET':
            handleGetBillingKey($portone, $pdo, $customerUid);
            break;
        case 'POST':
            handleIssueBillingKey($portone, $pdo);
            break;
        case 'DELETE':
            handleDeleteBillingKey($portone, $pdo, $customerUid);
            break;
        default:
            errorResponse('Method not allowed', 405);
    }
}

/**
 * 빌링키 조회
 */
function handleGetBillingKey(PortOne $portone, ?PDO $pdo, ?string $customerUid): void
{
    if (!$customerUid) {
        // 복수 조회 - customer_uid[] 파라미터
        $customerUids = $_GET['customer_uid'] ?? [];
        if (empty($customerUids)) {
            // DB에서 빌링키 목록 조회
            if ($pdo) {
                $billingKeys = getBillingKeysFromDb($pdo);
                successResponse(['billing_keys' => $billingKeys]);
            }
            errorResponse('customer_uid is required', 400);
        }
        
        if (!is_array($customerUids)) {
            $customerUids = [$customerUids];
        }
        
        $result = $portone->getBillingKeys($customerUids);
        
        if ($result['code'] !== 0) {
            errorResponse($result['message'] ?? 'Failed to get billing keys', 400);
        }
        
        successResponse(['billing_keys' => $result['response']]);
    }
    
    // 단건 조회
    $result = $portone->getBillingKey($customerUid);
    
    if ($result['code'] !== 0) {
        errorResponse($result['message'] ?? 'Billing key not found', 404);
    }
    
    // DB에서 추가 정보 조회
    $billingKey = $result['response'];
    if ($pdo) {
        $billingKey = enrichBillingKeyFromDb($pdo, $billingKey);
    }
    
    successResponse($billingKey);
}

/**
 * 빌링키 발급
 */
function handleIssueBillingKey(PortOne $portone, ?PDO $pdo): void
{
    $input = getJsonInputSubscription();
    
    // 필수 파라미터 검증
    $required = ['customer_uid', 'card_number', 'expiry', 'birth'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            errorResponse("{$field} is required", 400);
        }
    }
    
    $customerUid = $input['customer_uid'];
    
    $billingParams = [
        'card_number' => $input['card_number'],
        'expiry' => $input['expiry'],
        'birth' => $input['birth']
    ];
    
    // 선택 파라미터
    $optionalFields = [
        'pwd_2digit', 'pg', 'customer_name', 'customer_tel',
        'customer_email', 'customer_addr', 'customer_postcode'
    ];
    
    foreach ($optionalFields as $field) {
        if (!empty($input[$field])) {
            $billingParams[$field] = $input[$field];
        }
    }
    
    // 포트원 API 호출
    $result = $portone->issueBillingKey($customerUid, $billingParams);
    
    if ($result['code'] !== 0) {
        errorResponse($result['message'] ?? 'Failed to issue billing key', 400);
    }
    
    // DB에 빌링키 정보 저장
    if ($pdo) {
        saveBillingKey($pdo, $customerUid, $result['response'], $input);
    }
    
    // 카드 정보는 응답에서 제외 (보안)
    $response = $result['response'];
    unset($response['card_number']);
    
    successResponse($response, 'Billing key issued successfully');
}

/**
 * 빌링키 삭제
 */
function handleDeleteBillingKey(PortOne $portone, ?PDO $pdo, ?string $customerUid): void
{
    if (!$customerUid) {
        errorResponse('customer_uid is required', 400);
    }
    
    // 포트원 API 호출
    $result = $portone->deleteBillingKey($customerUid);
    
    if ($result['code'] !== 0) {
        errorResponse($result['message'] ?? 'Failed to delete billing key', 400);
    }
    
    // DB에서 빌링키 삭제
    if ($pdo) {
        deleteBillingKeyFromDb($pdo, $customerUid);
    }
    
    successResponse(['customer_uid' => $customerUid], 'Billing key deleted successfully');
}

// ========================================
// 빌링키 결제 핸들러
// ========================================

/**
 * 빌링키로 결제 요청
 */
function handleSubscriptionPay(string $method, PortOne $portone, ?PDO $pdo): void
{
    if ($method !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    $input = getJsonInputSubscription();
    
    // 필수 파라미터 검증
    $required = ['customer_uid', 'merchant_uid', 'amount', 'name'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            errorResponse("{$field} is required", 400);
        }
    }
    
    $payParams = [
        'customer_uid' => $input['customer_uid'],
        'merchant_uid' => $input['merchant_uid'],
        'amount' => (int)$input['amount'],
        'name' => $input['name']
    ];
    
    // 선택 파라미터
    $optionalFields = [
        'tax_free', 'buyer_name', 'buyer_email', 'buyer_tel',
        'buyer_addr', 'buyer_postcode', 'card_quota', 'custom_data', 'notice_url'
    ];
    
    foreach ($optionalFields as $field) {
        if (isset($input[$field])) {
            $payParams[$field] = $input[$field];
        }
    }
    
    // 포트원 API 호출
    $result = $portone->payWithBillingKey($payParams);
    
    if ($result['code'] !== 0) {
        errorResponse($result['message'] ?? 'Payment failed', 400);
    }
    
    // DB에 결제 내역 저장
    if ($pdo) {
        saveSubscriptionPayment($pdo, $result['response']);
    }
    
    successResponse($result['response'], 'Payment successful');
}

// ========================================
// 예약 결제 핸들러
// ========================================

/**
 * 예약 결제 관련 요청 처리
 */
function handleSchedule(string $method, PortOne $portone, ?PDO $pdo, ?string $resourceId): void
{
    switch ($method) {
        case 'GET':
            handleGetSchedule($portone, $pdo, $resourceId);
            break;
        case 'POST':
            handleCreateSchedule($portone, $pdo);
            break;
        case 'DELETE':
            handleDeleteSchedule($portone, $pdo, $resourceId);
            break;
        default:
            errorResponse('Method not allowed', 405);
    }
}

/**
 * 예약 결제 조회
 */
function handleGetSchedule(PortOne $portone, ?PDO $pdo, ?string $resourceId): void
{
    if (!$resourceId) {
        // DB에서 예약 결제 목록 조회
        if ($pdo) {
            $schedules = getSchedulesFromDb($pdo);
            successResponse(['schedules' => $schedules]);
        }
        errorResponse('customer_uid or merchant_uid is required', 400);
    }
    
    // customer_uid로 조회인지 merchant_uid로 조회인지 확인
    $byMerchant = $_GET['by'] ?? 'customer';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    
    if ($byMerchant === 'merchant') {
        // merchant_uid로 조회
        $result = $portone->getScheduledPaymentByMerchantUid($resourceId);
    } else {
        // customer_uid로 조회
        $result = $portone->getScheduledPayments($resourceId, $page, $limit);
    }
    
    if ($result['code'] !== 0) {
        errorResponse($result['message'] ?? 'Schedule not found', 404);
    }
    
    successResponse($result['response']);
}

/**
 * 예약 결제 등록
 */
function handleCreateSchedule(PortOne $portone, ?PDO $pdo): void
{
    $input = getJsonInputSubscription();
    
    // 필수 파라미터 검증
    if (empty($input['customer_uid'])) {
        errorResponse('customer_uid is required', 400);
    }
    if (empty($input['schedules']) || !is_array($input['schedules'])) {
        errorResponse('schedules array is required', 400);
    }
    
    // 각 스케줄 검증
    foreach ($input['schedules'] as $index => $schedule) {
        $required = ['merchant_uid', 'schedule_at', 'amount', 'name'];
        foreach ($required as $field) {
            if (empty($schedule[$field])) {
                errorResponse("schedules[{$index}].{$field} is required", 400);
            }
        }
    }
    
    // 포트원 API 호출
    $result = $portone->schedulePayment([
        'customer_uid' => $input['customer_uid'],
        'schedules' => $input['schedules']
    ]);
    
    if ($result['code'] !== 0) {
        errorResponse($result['message'] ?? 'Failed to create schedule', 400);
    }
    
    // DB에 예약 결제 정보 저장
    if ($pdo) {
        foreach ($input['schedules'] as $schedule) {
            saveSchedule($pdo, $input['customer_uid'], $schedule);
        }
    }
    
    successResponse($result['response'], 'Schedule created successfully');
}

/**
 * 예약 결제 취소
 */
function handleDeleteSchedule(PortOne $portone, ?PDO $pdo, ?string $customerUid): void
{
    if (!$customerUid) {
        errorResponse('customer_uid is required', 400);
    }
    
    $input = getJsonInputSubscription();
    $merchantUids = $input['merchant_uid'] ?? [];
    
    if (empty($merchantUids)) {
        errorResponse('merchant_uid array is required', 400);
    }
    
    if (!is_array($merchantUids)) {
        $merchantUids = [$merchantUids];
    }
    
    // 포트원 API 호출
    $result = $portone->unschedulePayment($customerUid, $merchantUids);
    
    if ($result['code'] !== 0) {
        errorResponse($result['message'] ?? 'Failed to delete schedule', 400);
    }
    
    // DB에서 예약 결제 삭제
    if ($pdo) {
        foreach ($merchantUids as $merchantUid) {
            deleteScheduleFromDb($pdo, $customerUid, $merchantUid);
        }
    }
    
    successResponse($result['response'], 'Schedule cancelled successfully');
}

// ========================================
// 구독 목록 조회
// ========================================

/**
 * 구독 목록 조회 (DB 기반)
 */
function handleGetSubscriptions(?PDO $pdo): void
{
    if (!$pdo) {
        errorResponse('Database not configured', 500);
    }
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $status = $_GET['status'] ?? null;
    
    $subscriptions = getSubscriptionsFromDb($pdo, $page, $limit, $status);
    
    successResponse($subscriptions);
}

// ========================================
// 헬퍼 함수
// ========================================

/**
 * JSON 입력 파싱
 */
function getJsonInputSubscription(): array
{
    $input = file_get_contents('php://input');
    $decoded = json_decode($input, true);
    return $decoded ?? [];
}

/**
 * DB에서 빌링키 정보 조회
 */
function enrichBillingKeyFromDb(PDO $pdo, array $billingKey): array
{
    try {
        $stmt = $pdo->prepare('
            SELECT * FROM billing_keys 
            WHERE customer_uid = ?
            LIMIT 1
        ');
        $stmt->execute([$billingKey['customer_uid'] ?? '']);
        $dbRecord = $stmt->fetch();
        
        if ($dbRecord) {
            $billingKey['_db'] = $dbRecord;
        }
    } catch (PDOException $e) {
        // 무시
    }
    
    return $billingKey;
}

/**
 * DB에서 빌링키 목록 조회
 */
function getBillingKeysFromDb(PDO $pdo): array
{
    try {
        $stmt = $pdo->query('
            SELECT * FROM billing_keys 
            WHERE status = "active"
            ORDER BY created_at DESC
            LIMIT 100
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * 빌링키 저장
 */
function saveBillingKey(PDO $pdo, string $customerUid, array $response, array $input): void
{
    try {
        $stmt = $pdo->prepare('
            INSERT INTO billing_keys (
                customer_uid, pg_provider, pg_id, card_name, card_code,
                card_number, customer_name, customer_tel, customer_email,
                status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                pg_provider = VALUES(pg_provider),
                pg_id = VALUES(pg_id),
                card_name = VALUES(card_name),
                card_code = VALUES(card_code),
                card_number = VALUES(card_number),
                customer_name = VALUES(customer_name),
                status = VALUES(status),
                updated_at = NOW()
        ');
        $stmt->execute([
            $customerUid,
            $response['pg_provider'] ?? null,
            $response['pg_id'] ?? null,
            $response['card_name'] ?? null,
            $response['card_code'] ?? null,
            $response['card_number'] ?? null,
            $input['customer_name'] ?? null,
            $input['customer_tel'] ?? null,
            $input['customer_email'] ?? null,
            'active'
        ]);
    } catch (PDOException $e) {
        error_log('Failed to save billing key: ' . $e->getMessage());
    }
}

/**
 * 빌링키 삭제 (DB)
 */
function deleteBillingKeyFromDb(PDO $pdo, string $customerUid): void
{
    try {
        $stmt = $pdo->prepare('
            UPDATE billing_keys 
            SET status = "deleted", deleted_at = NOW()
            WHERE customer_uid = ?
        ');
        $stmt->execute([$customerUid]);
    } catch (PDOException $e) {
        error_log('Failed to delete billing key: ' . $e->getMessage());
    }
}

/**
 * 빌링키 결제 내역 저장
 */
function saveSubscriptionPayment(PDO $pdo, array $paymentData): void
{
    try {
        $stmt = $pdo->prepare('
            INSERT INTO payments (
                imp_uid, merchant_uid, customer_uid, amount, status,
                pay_method, pg_provider, pg_tid, product_name,
                buyer_name, buyer_email, buyer_tel, paid_at, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), NOW())
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                paid_at = VALUES(paid_at),
                updated_at = NOW()
        ');
        $stmt->execute([
            $paymentData['imp_uid'],
            $paymentData['merchant_uid'],
            $paymentData['customer_uid'] ?? null,
            $paymentData['amount'],
            $paymentData['status'],
            $paymentData['pay_method'] ?? 'card',
            $paymentData['pg_provider'] ?? null,
            $paymentData['pg_tid'] ?? null,
            $paymentData['name'] ?? null,
            $paymentData['buyer_name'] ?? null,
            $paymentData['buyer_email'] ?? null,
            $paymentData['buyer_tel'] ?? null,
            $paymentData['paid_at'] ?? null
        ]);
    } catch (PDOException $e) {
        error_log('Failed to save subscription payment: ' . $e->getMessage());
    }
}

/**
 * 예약 결제 저장
 */
function saveSchedule(PDO $pdo, string $customerUid, array $schedule): void
{
    try {
        $stmt = $pdo->prepare('
            INSERT INTO payment_schedules (
                customer_uid, merchant_uid, amount, product_name,
                schedule_at, status, created_at
            ) VALUES (?, ?, ?, ?, FROM_UNIXTIME(?), ?, NOW())
            ON DUPLICATE KEY UPDATE
                amount = VALUES(amount),
                product_name = VALUES(product_name),
                schedule_at = VALUES(schedule_at),
                status = VALUES(status),
                updated_at = NOW()
        ');
        $stmt->execute([
            $customerUid,
            $schedule['merchant_uid'],
            $schedule['amount'],
            $schedule['name'] ?? null,
            $schedule['schedule_at'],
            'scheduled'
        ]);
    } catch (PDOException $e) {
        error_log('Failed to save schedule: ' . $e->getMessage());
    }
}

/**
 * 예약 결제 삭제 (DB)
 */
function deleteScheduleFromDb(PDO $pdo, string $customerUid, string $merchantUid): void
{
    try {
        $stmt = $pdo->prepare('
            UPDATE payment_schedules 
            SET status = "cancelled", cancelled_at = NOW()
            WHERE customer_uid = ? AND merchant_uid = ?
        ');
        $stmt->execute([$customerUid, $merchantUid]);
    } catch (PDOException $e) {
        error_log('Failed to delete schedule: ' . $e->getMessage());
    }
}

/**
 * DB에서 예약 결제 목록 조회
 */
function getSchedulesFromDb(PDO $pdo): array
{
    try {
        $stmt = $pdo->query('
            SELECT * FROM payment_schedules 
            WHERE status = "scheduled"
            ORDER BY schedule_at ASC
            LIMIT 100
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * DB에서 구독 목록 조회
 */
function getSubscriptionsFromDb(PDO $pdo, int $page, int $limit, ?string $status): array
{
    try {
        $offset = ($page - 1) * $limit;
        
        $sql = '
            SELECT bk.*, 
                   (SELECT COUNT(*) FROM payments p WHERE p.customer_uid = bk.customer_uid) as payment_count,
                   (SELECT SUM(amount) FROM payments p WHERE p.customer_uid = bk.customer_uid AND p.status = "paid") as total_paid
            FROM billing_keys bk
        ';
        
        if ($status) {
            $sql .= ' WHERE bk.status = ?';
        }
        
        $sql .= ' ORDER BY bk.created_at DESC LIMIT ? OFFSET ?';
        
        $stmt = $pdo->prepare($sql);
        
        if ($status) {
            $stmt->execute([$status, $limit, $offset]);
        } else {
            $stmt->execute([$limit, $offset]);
        }
        
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 전체 개수 조회
        $countSql = 'SELECT COUNT(*) FROM billing_keys';
        if ($status) {
            $countSql .= ' WHERE status = ?';
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute([$status]);
        } else {
            $countStmt = $pdo->query($countSql);
        }
        $total = $countStmt->fetchColumn();
        
        return [
            'subscriptions' => $subscriptions,
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit
        ];
    } catch (PDOException $e) {
        return [
            'subscriptions' => [],
            'total' => 0,
            'page' => $page,
            'limit' => $limit
        ];
    }
}
