<?php
/**
 * Payments Handler
 * 
 * 결제 관련 API 엔드포인트 핸들러
 * - 결제 조회 (단건/목록/상태별)
 * - 결제 사전등록 (금액 위변조 방지)
 * - 결제 취소/환불
 * - 가상계좌 발급
 */

require_once __DIR__ . '/../lib/PortOne.php';

/**
 * 결제 API 핸들러
 * 
 * @param string $method HTTP 메서드
 * @param string|null $resourceId 리소스 ID (imp_uid 또는 merchant_uid)
 * @param string|null $action 액션 (prepare, cancel, vbank 등)
 */
function handlePayments(string $method, ?string $resourceId, ?string $action): void
{
    try {
        $portone = PortOne::fromEnv();
    } catch (Exception $e) {
        errorResponse('PortOne API not configured: ' . $e->getMessage(), 500);
    }
    
    // 현재 요청은 index.php 에서 API 키로 사이트 컨텍스트가 설정된 상태여야 한다.
    $pdo = getCurrentSiteDbConnection();
    if (!$pdo) {
        errorResponse('Site database not available', 500);
    }
    
    switch ($method) {
        case 'GET':
            handleGetPayments($portone, $pdo, $resourceId, $action);
            break;
        case 'POST':
            handlePostPayments($portone, $pdo, $resourceId, $action);
            break;
        default:
            errorResponse('Method not allowed', 405);
    }
}

/**
 * GET 요청 처리
 */
function handleGetPayments(PortOne $portone, ?PDO $pdo, ?string $resourceId, ?string $action): void
{
    // GET /payments - 결제 목록 조회
    if (!$resourceId) {
        $status = $_GET['status'] ?? 'all';
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        
        $result = $portone->getPaymentsByStatus($status, $page, $limit);
        
        if ($result['code'] !== 0) {
            errorResponse($result['message'] ?? 'Failed to get payments', 400);
        }
        
        // DB에서 추가 정보 조회 (있는 경우)
        $payments = $result['response']['list'] ?? [];
        if ($pdo && !empty($payments)) {
            $payments = enrichPaymentsFromDb($pdo, $payments);
        }
        
        successResponse([
            'payments' => $payments,
            'total' => $result['response']['total'] ?? count($payments),
            'page' => $page,
            'limit' => $limit
        ]);
    }
    
    // GET /payments/prepare/{merchant_uid} - 사전등록 정보 조회
    if ($resourceId === 'prepare' && $action) {
        $merchantUid = $action;
        $result = $portone->getPreparedPayment($merchantUid);
        
        if ($result['code'] !== 0) {
            errorResponse($result['message'] ?? 'Prepared payment not found', 404);
        }
        
        successResponse($result['response']);
    }
    
    // GET /payments/find/{merchant_uid} - merchant_uid로 조회
    if ($resourceId === 'find' && $action) {
        $result = $portone->getPaymentByMerchantUid($action);
        
        if ($result['code'] !== 0) {
            errorResponse($result['message'] ?? 'Payment not found', 404);
        }
        
        successResponse($result['response']);
    }
    
    // GET /payments/{imp_uid}
    $result = $portone->getPayment($resourceId);
    
    if ($result['code'] !== 0) {
        errorResponse($result['message'] ?? 'Payment not found', 404);
    }
    
    // DB에서 추가 정보 조회
    $payment = $result['response'];
    if ($pdo) {
        $payment = enrichPaymentFromDb($pdo, $payment);
    }
    
    successResponse($payment);
}

/**
 * POST 요청 처리
 */
function handlePostPayments(PortOne $portone, ?PDO $pdo, ?string $resourceId, ?string $action): void
{
    $input = getJsonInput();
    
    // POST /payments/prepare - 결제 사전등록
    if ($resourceId === 'prepare') {
        handlePreparePayment($portone, $pdo, $input);
        return;
    }
    
    // POST /payments/cancel - 결제 취소
    if ($resourceId === 'cancel') {
        handleCancelPayment($portone, $pdo, $input);
        return;
    }
    
    // POST /payments/vbank - 가상계좌 발급
    if ($resourceId === 'vbank') {
        handleCreateVirtualAccount($portone, $pdo, $input);
        return;
    }
    
    // POST /payments/verify - 결제 검증
    if ($resourceId === 'verify') {
        handleVerifyPayment($portone, $input);
        return;
    }
    
    errorResponse('Unknown action', 404);
}

/**
 * 결제 사전등록 처리
 */
function handlePreparePayment(PortOne $portone, ?PDO $pdo, array $input): void
{
    // 필수 파라미터 검증
    if (empty($input['merchant_uid']) || !isset($input['amount'])) {
        errorResponse('merchant_uid and amount are required', 400);
    }
    
    $merchantUid = $input['merchant_uid'];
    $amount = (int)$input['amount'];
    
    if ($amount <= 0) {
        errorResponse('Amount must be greater than 0', 400);
    }
    
    // 포트원 API 호출
    $result = $portone->preparePayment($merchantUid, $amount);
    
    if ($result['code'] !== 0) {
        errorResponse($result['message'] ?? 'Failed to prepare payment', 400);
    }
    
    // DB에 사전등록 정보 저장
    if ($pdo) {
        savePaymentPrepare($pdo, $merchantUid, $amount, $input);
    }
    
    successResponse([
        'merchant_uid' => $merchantUid,
        'amount' => $amount
    ], 'Payment prepared successfully');
}

/**
 * 결제 취소 처리
 */
function handleCancelPayment(PortOne $portone, ?PDO $pdo, array $input): void
{
    // imp_uid 또는 merchant_uid 중 하나는 필수
    if (empty($input['imp_uid']) && empty($input['merchant_uid'])) {
        errorResponse('imp_uid or merchant_uid is required', 400);
    }
    
    $cancelParams = [];
    
    if (!empty($input['imp_uid'])) {
        $cancelParams['imp_uid'] = $input['imp_uid'];
    }
    if (!empty($input['merchant_uid'])) {
        $cancelParams['merchant_uid'] = $input['merchant_uid'];
    }
    if (isset($input['amount'])) {
        $cancelParams['amount'] = (int)$input['amount'];
    }
    if (isset($input['tax_free'])) {
        $cancelParams['tax_free'] = (int)$input['tax_free'];
    }
    if (isset($input['checksum'])) {
        $cancelParams['checksum'] = (int)$input['checksum'];
    }
    if (!empty($input['reason'])) {
        $cancelParams['reason'] = $input['reason'];
    }
    
    // 가상계좌 환불 계좌 정보
    if (!empty($input['refund_holder'])) {
        $cancelParams['refund_holder'] = $input['refund_holder'];
    }
    if (!empty($input['refund_bank'])) {
        $cancelParams['refund_bank'] = $input['refund_bank'];
    }
    if (!empty($input['refund_account'])) {
        $cancelParams['refund_account'] = $input['refund_account'];
    }
    if (!empty($input['refund_tel'])) {
        $cancelParams['refund_tel'] = $input['refund_tel'];
    }
    
    // 포트원 API 호출
    $result = $portone->cancelPayment($cancelParams);
    
    if ($result['code'] !== 0) {
        errorResponse($result['message'] ?? 'Failed to cancel payment', 400);
    }
    
    // DB에 취소 내역 저장
    if ($pdo) {
        saveCancelRecord($pdo, $result['response']);
    }
    
    successResponse($result['response'], 'Payment cancelled successfully');
}

/**
 * 가상계좌 발급 처리
 */
function handleCreateVirtualAccount(PortOne $portone, ?PDO $pdo, array $input): void
{
    // 필수 파라미터 검증
    $required = ['merchant_uid', 'amount', 'vbank_code', 'vbank_holder'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            errorResponse("{$field} is required", 400);
        }
    }
    
    $vbankParams = [
        'merchant_uid' => $input['merchant_uid'],
        'amount' => (int)$input['amount'],
        'vbank_code' => $input['vbank_code'],
        'vbank_holder' => $input['vbank_holder']
    ];
    
    // 선택 파라미터
    $optionalFields = [
        'vbank_due', 'name', 'buyer_name', 'buyer_email', 
        'buyer_tel', 'buyer_addr', 'buyer_postcode', 'pg', 'notice_url'
    ];
    
    foreach ($optionalFields as $field) {
        if (!empty($input[$field])) {
            $vbankParams[$field] = $input[$field];
        }
    }
    
    // 기본 입금기한 설정 (7일 후)
    if (empty($vbankParams['vbank_due'])) {
        $vbankParams['vbank_due'] = time() + (7 * 24 * 60 * 60);
    }
    
    // 포트원 API 호출
    $result = $portone->createVirtualAccount($vbankParams);
    
    if ($result['code'] !== 0) {
        errorResponse($result['message'] ?? 'Failed to create virtual account', 400);
    }
    
    // DB에 가상계좌 정보 저장
    if ($pdo) {
        saveVirtualAccountRecord($pdo, $result['response']);
    }
    
    successResponse($result['response'], 'Virtual account created successfully');
}

/**
 * 결제 검증 처리
 */
function handleVerifyPayment(PortOne $portone, array $input): void
{
    if (empty($input['imp_uid'])) {
        errorResponse('imp_uid is required', 400);
    }
    
    $impUid = $input['imp_uid'];
    $expectedAmount = isset($input['amount']) ? (int)$input['amount'] : null;
    $expectedStatus = $input['status'] ?? null;
    
    // 결제 정보 조회
    $result = $portone->getPayment($impUid);
    
    if ($result['code'] !== 0) {
        errorResponse('Payment not found', 404);
    }
    
    $payment = $result['response'];
    $isValid = true;
    $errors = [];
    
    // 금액 검증
    if ($expectedAmount !== null && $payment['amount'] !== $expectedAmount) {
        $isValid = false;
        $errors[] = sprintf(
            'Amount mismatch: expected %d, got %d',
            $expectedAmount,
            $payment['amount']
        );
    }
    
    // 상태 검증
    if ($expectedStatus !== null && $payment['status'] !== $expectedStatus) {
        $isValid = false;
        $errors[] = sprintf(
            'Status mismatch: expected %s, got %s',
            $expectedStatus,
            $payment['status']
        );
    }
    
    successResponse([
        'valid' => $isValid,
        'payment' => $payment,
        'errors' => $errors
    ]);
}

// ========================================
// 헬퍼 함수
// ========================================

/**
 * JSON 입력 파싱
 */
function getJsonInput(): array
{
    $input = file_get_contents('php://input');
    $decoded = json_decode($input, true);
    return $decoded ?? [];
}

/**
 * DB에서 결제 추가 정보 조회 (단건)
 */
function enrichPaymentFromDb(PDO $pdo, array $payment): array
{
    try {
        $stmt = $pdo->prepare('
            SELECT * FROM payments 
            WHERE imp_uid = ? OR merchant_uid = ?
            LIMIT 1
        ');
        $stmt->execute([$payment['imp_uid'] ?? '', $payment['merchant_uid'] ?? '']);
        $dbRecord = $stmt->fetch();
        
        if ($dbRecord) {
            $payment['_db'] = $dbRecord;
        }
    } catch (PDOException $e) {
        // DB 조회 실패 시 무시
    }
    
    return $payment;
}

/**
 * DB에서 결제 추가 정보 조회 (복수)
 */
function enrichPaymentsFromDb(PDO $pdo, array $payments): array
{
    if (empty($payments)) {
        return $payments;
    }
    
    try {
        $impUids = array_column($payments, 'imp_uid');
        $placeholders = implode(',', array_fill(0, count($impUids), '?'));
        
        $stmt = $pdo->prepare("
            SELECT * FROM payments 
            WHERE imp_uid IN ({$placeholders})
        ");
        $stmt->execute($impUids);
        $dbRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // imp_uid로 인덱싱
        $dbMap = [];
        foreach ($dbRecords as $record) {
            $dbMap[$record['imp_uid']] = $record;
        }
        
        // 결제 정보에 DB 데이터 추가
        foreach ($payments as &$payment) {
            if (isset($dbMap[$payment['imp_uid']])) {
                $payment['_db'] = $dbMap[$payment['imp_uid']];
            }
        }
    } catch (PDOException $e) {
        // DB 조회 실패 시 무시
    }
    
    return $payments;
}

/**
 * 사전등록 정보 DB 저장
 */
function savePaymentPrepare(PDO $pdo, string $merchantUid, int $amount, array $input): void
{
    try {
        $stmt = $pdo->prepare('
            INSERT INTO payments (merchant_uid, amount, status, product_name, buyer_name, buyer_email, buyer_tel, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                amount = VALUES(amount),
                product_name = VALUES(product_name),
                buyer_name = VALUES(buyer_name),
                buyer_email = VALUES(buyer_email),
                buyer_tel = VALUES(buyer_tel),
                updated_at = NOW()
        ');
        $stmt->execute([
            $merchantUid,
            $amount,
            'prepared',
            $input['name'] ?? null,
            $input['buyer_name'] ?? null,
            $input['buyer_email'] ?? null,
            $input['buyer_tel'] ?? null
        ]);
    } catch (PDOException $e) {
        // 저장 실패 시 무시 (로깅만)
        error_log('Failed to save payment prepare: ' . $e->getMessage());
    }
}

/**
 * 취소 내역 DB 저장
 */
function saveCancelRecord(PDO $pdo, array $cancelData): void
{
    try {
        // 결제 상태 업데이트
        $stmt = $pdo->prepare('
            UPDATE payments 
            SET status = ?, 
                cancel_amount = ?,
                cancel_reason = ?,
                cancelled_at = NOW(),
                updated_at = NOW()
            WHERE imp_uid = ?
        ');
        $stmt->execute([
            $cancelData['status'] ?? 'cancelled',
            $cancelData['cancel_amount'] ?? 0,
            $cancelData['cancel_reason'] ?? null,
            $cancelData['imp_uid']
        ]);
    } catch (PDOException $e) {
        error_log('Failed to save cancel record: ' . $e->getMessage());
    }
}

/**
 * 가상계좌 정보 DB 저장
 */
function saveVirtualAccountRecord(PDO $pdo, array $vbankData): void
{
    try {
        $stmt = $pdo->prepare('
            INSERT INTO payments (
                imp_uid, merchant_uid, amount, status, pay_method,
                vbank_code, vbank_name, vbank_num, vbank_holder, vbank_date,
                buyer_name, buyer_email, buyer_tel, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                vbank_code = VALUES(vbank_code),
                vbank_name = VALUES(vbank_name),
                vbank_num = VALUES(vbank_num),
                vbank_holder = VALUES(vbank_holder),
                vbank_date = VALUES(vbank_date),
                updated_at = NOW()
        ');
        $stmt->execute([
            $vbankData['imp_uid'],
            $vbankData['merchant_uid'],
            $vbankData['amount'],
            $vbankData['status'] ?? 'ready',
            'vbank',
            $vbankData['vbank_code'] ?? null,
            $vbankData['vbank_name'] ?? null,
            $vbankData['vbank_num'] ?? null,
            $vbankData['vbank_holder'] ?? null,
            $vbankData['vbank_date'] ?? null,
            $vbankData['buyer_name'] ?? null,
            $vbankData['buyer_email'] ?? null,
            $vbankData['buyer_tel'] ?? null
        ]);
    } catch (PDOException $e) {
        error_log('Failed to save vbank record: ' . $e->getMessage());
    }
}
