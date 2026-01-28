<?php
/**
 * Webhooks Handler
 * 
 * 포트원 웹훅 수신 및 처리 핸들러
 * - 결제 완료 (paid)
 * - 결제 취소 (cancelled)
 * - 가상계좌 입금 (vbank_deposit)
 * - 예약 결제 완료 (schedule_paid)
 */

require_once __DIR__ . '/../lib/PortOne.php';

/**
 * 웹훅 API 핸들러
 * 
 * @param string $method HTTP 메서드
 * @param string|null $resourceId 웹훅 소스 (portone)
 * @param string|null $action 추가 액션
 */
function handleWebhooks(string $method, ?string $resourceId, ?string $action): void
{
    // 웹훅은 POST만 허용
    if ($method !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    
    // 포트원 웹훅
    if ($resourceId === 'portone') {
        handlePortoneWebhook();
        return;
    }
    
    // 웹훅 로그 조회 (GET /webhooks/logs)
    if ($resourceId === 'logs' && $method === 'GET') {
        handleGetWebhookLogs();
        return;
    }
    
    errorResponse('Unknown webhook source', 404);
}

/**
 * 포트원 웹훅 처리
 */
function handlePortoneWebhook(): void
{
    $pdo = getDbConnection();
    
    // 웹훅 데이터 파싱
    $input = getWebhookInput();
    
    // 필수 필드 검증
    if (empty($input['imp_uid'])) {
        logWebhook($pdo, 'portone', $input, 'error', 'Missing imp_uid');
        errorResponse('imp_uid is required', 400);
    }
    
    $impUid = $input['imp_uid'];
    $merchantUid = $input['merchant_uid'] ?? null;
    $status = $input['status'] ?? null;
    
    // 웹훅 수신 로그 저장
    logWebhook($pdo, 'portone', $input, 'received');
    
    try {
        // 포트원 API로 결제 정보 검증 (보안 - 위변조 방지)
        $portone = PortOne::fromEnv();
        $paymentResult = $portone->getPayment($impUid);
        
        if ($paymentResult['code'] !== 0) {
            logWebhook($pdo, 'portone', $input, 'error', 'Payment not found in PortOne');
            errorResponse('Payment verification failed', 400);
        }
        
        $payment = $paymentResult['response'];
        
        // 상태에 따른 처리
        switch ($payment['status']) {
            case 'paid':
                handlePaidWebhook($pdo, $payment, $input);
                break;
            case 'cancelled':
                handleCancelledWebhook($pdo, $payment, $input);
                break;
            case 'ready':
                // 가상계좌 발급 완료 또는 입금 대기
                handleReadyWebhook($pdo, $payment, $input);
                break;
            case 'failed':
                handleFailedWebhook($pdo, $payment, $input);
                break;
            default:
                logWebhook($pdo, 'portone', $input, 'warning', 'Unknown status: ' . $payment['status']);
        }
        
        // 성공 응답
        logWebhook($pdo, 'portone', $input, 'processed', 'Status: ' . $payment['status']);
        successResponse(['status' => 'ok']);
        
    } catch (Exception $e) {
        logWebhook($pdo, 'portone', $input, 'error', $e->getMessage());
        errorResponse('Webhook processing failed: ' . $e->getMessage(), 500);
    }
}

/**
 * 결제 완료 웹훅 처리
 */
function handlePaidWebhook(?PDO $pdo, array $payment, array $webhookData): void
{
    if (!$pdo) return;
    
    try {
        // 결제 정보 저장/업데이트
        $stmt = $pdo->prepare('
            INSERT INTO payments (
                imp_uid, merchant_uid, customer_uid, amount, status,
                pay_method, pg_provider, pg_tid, product_name,
                buyer_name, buyer_email, buyer_tel,
                card_name, card_number, card_quota,
                vbank_code, vbank_name, vbank_num, vbank_holder,
                paid_at, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), NOW())
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                pay_method = VALUES(pay_method),
                pg_provider = VALUES(pg_provider),
                pg_tid = VALUES(pg_tid),
                card_name = VALUES(card_name),
                card_number = VALUES(card_number),
                card_quota = VALUES(card_quota),
                paid_at = VALUES(paid_at),
                updated_at = NOW()
        ');
        
        $stmt->execute([
            $payment['imp_uid'],
            $payment['merchant_uid'],
            $payment['customer_uid'] ?? null,
            $payment['amount'],
            $payment['status'],
            $payment['pay_method'] ?? null,
            $payment['pg_provider'] ?? null,
            $payment['pg_tid'] ?? null,
            $payment['name'] ?? null,
            $payment['buyer_name'] ?? null,
            $payment['buyer_email'] ?? null,
            $payment['buyer_tel'] ?? null,
            $payment['card_name'] ?? null,
            $payment['card_number'] ?? null,
            $payment['card_quota'] ?? null,
            $payment['vbank_code'] ?? null,
            $payment['vbank_name'] ?? null,
            $payment['vbank_num'] ?? null,
            $payment['vbank_holder'] ?? null,
            $payment['paid_at'] ?? null
        ]);
        
        // 예약 결제인 경우 스케줄 상태 업데이트
        if (!empty($payment['customer_uid'])) {
            $stmt = $pdo->prepare('
                UPDATE payment_schedules 
                SET status = "paid", paid_at = NOW(), imp_uid = ?
                WHERE customer_uid = ? AND merchant_uid = ?
            ');
            $stmt->execute([
                $payment['imp_uid'],
                $payment['customer_uid'],
                $payment['merchant_uid']
            ]);
        }
        
        // 결제 완료 후처리 (비즈니스 로직)
        triggerPaymentCompleteEvent($pdo, $payment);
        
    } catch (PDOException $e) {
        error_log('Paid webhook DB error: ' . $e->getMessage());
    }
}

/**
 * 결제 취소 웹훅 처리
 */
function handleCancelledWebhook(?PDO $pdo, array $payment, array $webhookData): void
{
    if (!$pdo) return;
    
    try {
        // 결제 상태 업데이트
        $stmt = $pdo->prepare('
            UPDATE payments 
            SET status = ?,
                cancel_amount = ?,
                cancel_reason = ?,
                cancelled_at = FROM_UNIXTIME(?),
                updated_at = NOW()
            WHERE imp_uid = ?
        ');
        
        $stmt->execute([
            $payment['status'],
            $payment['cancel_amount'] ?? 0,
            $payment['cancel_reason'] ?? null,
            $payment['cancelled_at'] ?? time(),
            $payment['imp_uid']
        ]);
        
        // 취소 완료 후처리
        triggerPaymentCancelEvent($pdo, $payment);
        
    } catch (PDOException $e) {
        error_log('Cancelled webhook DB error: ' . $e->getMessage());
    }
}

/**
 * 가상계좌 발급/입금대기 웹훅 처리
 */
function handleReadyWebhook(?PDO $pdo, array $payment, array $webhookData): void
{
    if (!$pdo) return;
    
    // 가상계좌 입금 통보인지 확인
    $isVbankDeposit = ($payment['pay_method'] === 'vbank' && 
                       isset($webhookData['status']) && 
                       $webhookData['status'] === 'paid');
    
    if ($isVbankDeposit) {
        // 가상계좌 입금 완료 처리
        handleVbankDeposit($pdo, $payment, $webhookData);
        return;
    }
    
    try {
        // 가상계좌 발급 완료 저장
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
            $payment['imp_uid'],
            $payment['merchant_uid'],
            $payment['amount'],
            $payment['status'],
            $payment['pay_method'] ?? 'vbank',
            $payment['vbank_code'] ?? null,
            $payment['vbank_name'] ?? null,
            $payment['vbank_num'] ?? null,
            $payment['vbank_holder'] ?? null,
            $payment['vbank_date'] ?? null,
            $payment['buyer_name'] ?? null,
            $payment['buyer_email'] ?? null,
            $payment['buyer_tel'] ?? null
        ]);
        
    } catch (PDOException $e) {
        error_log('Ready webhook DB error: ' . $e->getMessage());
    }
}

/**
 * 가상계좌 입금 완료 처리
 */
function handleVbankDeposit(?PDO $pdo, array $payment, array $webhookData): void
{
    if (!$pdo) return;
    
    try {
        // 결제 상태를 paid로 업데이트
        $stmt = $pdo->prepare('
            UPDATE payments 
            SET status = "paid",
                paid_at = NOW(),
                updated_at = NOW()
            WHERE imp_uid = ?
        ');
        $stmt->execute([$payment['imp_uid']]);
        
        // 입금 완료 후처리
        triggerVbankDepositEvent($pdo, $payment);
        
    } catch (PDOException $e) {
        error_log('Vbank deposit webhook DB error: ' . $e->getMessage());
    }
}

/**
 * 결제 실패 웹훅 처리
 */
function handleFailedWebhook(?PDO $pdo, array $payment, array $webhookData): void
{
    if (!$pdo) return;
    
    try {
        // 결제 실패 정보 저장
        $stmt = $pdo->prepare('
            INSERT INTO payments (
                imp_uid, merchant_uid, amount, status, pay_method,
                fail_reason, buyer_name, buyer_email, buyer_tel, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                fail_reason = VALUES(fail_reason),
                updated_at = NOW()
        ');
        
        $stmt->execute([
            $payment['imp_uid'],
            $payment['merchant_uid'],
            $payment['amount'],
            $payment['status'],
            $payment['pay_method'] ?? null,
            $payment['fail_reason'] ?? null,
            $payment['buyer_name'] ?? null,
            $payment['buyer_email'] ?? null,
            $payment['buyer_tel'] ?? null
        ]);
        
        // 예약 결제 실패 처리
        if (!empty($payment['customer_uid'])) {
            $stmt = $pdo->prepare('
                UPDATE payment_schedules 
                SET status = "failed", fail_reason = ?
                WHERE customer_uid = ? AND merchant_uid = ?
            ');
            $stmt->execute([
                $payment['fail_reason'] ?? 'Unknown error',
                $payment['customer_uid'],
                $payment['merchant_uid']
            ]);
        }
        
    } catch (PDOException $e) {
        error_log('Failed webhook DB error: ' . $e->getMessage());
    }
}

// ========================================
// 이벤트 트리거 (비즈니스 로직 확장점)
// ========================================

/**
 * 결제 완료 이벤트 트리거
 */
function triggerPaymentCompleteEvent(?PDO $pdo, array $payment): void
{
    // 여기에 비즈니스 로직 추가
    // 예: 주문 상태 변경, 이메일 발송, 재고 차감 등
    
    // 이벤트 로그
    logBusinessEvent($pdo, 'payment_complete', $payment['imp_uid'], $payment);
}

/**
 * 결제 취소 이벤트 트리거
 */
function triggerPaymentCancelEvent(?PDO $pdo, array $payment): void
{
    // 여기에 비즈니스 로직 추가
    // 예: 주문 취소 처리, 재고 복원 등
    
    logBusinessEvent($pdo, 'payment_cancel', $payment['imp_uid'], $payment);
}

/**
 * 가상계좌 입금 완료 이벤트 트리거
 */
function triggerVbankDepositEvent(?PDO $pdo, array $payment): void
{
    // 여기에 비즈니스 로직 추가
    // 예: 주문 확정, 배송 시작 등
    
    logBusinessEvent($pdo, 'vbank_deposit', $payment['imp_uid'], $payment);
}

/**
 * 비즈니스 이벤트 로그
 */
function logBusinessEvent(?PDO $pdo, string $eventType, string $impUid, array $data): void
{
    if (!$pdo) return;
    
    try {
        $stmt = $pdo->prepare('
            INSERT INTO webhook_logs (
                source, event_type, imp_uid, payload, status, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([
            'business',
            $eventType,
            $impUid,
            json_encode($data),
            'triggered'
        ]);
    } catch (PDOException $e) {
        error_log('Business event log error: ' . $e->getMessage());
    }
}

// ========================================
// 웹훅 로그 관련
// ========================================

/**
 * 웹훅 로그 저장
 */
function logWebhook(?PDO $pdo, string $source, array $data, string $status, ?string $message = null): void
{
    if (!$pdo) return;
    
    try {
        $stmt = $pdo->prepare('
            INSERT INTO webhook_logs (
                source, imp_uid, merchant_uid, payload, status, message, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([
            $source,
            $data['imp_uid'] ?? null,
            $data['merchant_uid'] ?? null,
            json_encode($data),
            $status,
            $message
        ]);
    } catch (PDOException $e) {
        error_log('Webhook log error: ' . $e->getMessage());
    }
}

/**
 * 웹훅 로그 조회
 */
function handleGetWebhookLogs(): void
{
    $pdo = getDbConnection();
    
    if (!$pdo) {
        errorResponse('Database not configured', 500);
    }
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 50);
    $source = $_GET['source'] ?? null;
    $status = $_GET['status'] ?? null;
    $impUid = $_GET['imp_uid'] ?? null;
    $offset = ($page - 1) * $limit;
    
    try {
        $sql = 'SELECT * FROM webhook_logs WHERE 1=1';
        $params = [];
        
        if ($source) {
            $sql .= ' AND source = ?';
            $params[] = $source;
        }
        if ($status) {
            $sql .= ' AND status = ?';
            $params[] = $status;
        }
        if ($impUid) {
            $sql .= ' AND imp_uid = ?';
            $params[] = $impUid;
        }
        
        $sql .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // payload JSON 디코딩
        foreach ($logs as &$log) {
            if (!empty($log['payload'])) {
                $log['payload'] = json_decode($log['payload'], true);
            }
        }
        
        successResponse([
            'logs' => $logs,
            'page' => $page,
            'limit' => $limit
        ]);
        
    } catch (PDOException $e) {
        errorResponse('Failed to get webhook logs: ' . $e->getMessage(), 500);
    }
}

// ========================================
// 헬퍼 함수
// ========================================

/**
 * 웹훅 입력 파싱
 */
function getWebhookInput(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    // JSON 형식
    if (strpos($contentType, 'application/json') !== false) {
        $input = file_get_contents('php://input');
        $decoded = json_decode($input, true);
        return $decoded ?? [];
    }
    
    // Form 데이터 형식
    if (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
        return $_POST;
    }
    
    // 기본: JSON 시도
    $input = file_get_contents('php://input');
    $decoded = json_decode($input, true);
    
    if ($decoded !== null) {
        return $decoded;
    }
    
    // Form 데이터 파싱 시도
    parse_str($input, $parsed);
    return $parsed;
}
