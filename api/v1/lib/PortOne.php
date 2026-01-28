<?php
/**
 * PortOne V1 API Client Library
 * 
 * 포트원(구 아임포트) V1 API 클라이언트
 * - 인증 토큰 관리
 * - 결제 조회/취소
 * - 빌링키 관리
 * - 정기결제/예약결제
 * - 가상계좌 발급
 */

class PortOne
{
    private const API_BASE_URL = 'https://api.iamport.kr';
    
    private string $apiKey;
    private string $apiSecret;
    private ?string $accessToken = null;
    private ?int $tokenExpiry = null;
    
    /**
     * PortOne 클라이언트 생성자
     * 
     * @param string $apiKey 포트원 REST API Key
     * @param string $apiSecret 포트원 REST API Secret
     */
    public function __construct(string $apiKey, string $apiSecret)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
    }
    
    /**
     * 환경변수에서 클라이언트 인스턴스 생성
     * 
     * @return self
     * @throws Exception API 키가 설정되지 않은 경우
     */
    public static function fromEnv(): self
    {
        $apiKey = getenv('PORTONE_API_KEY') ?: '';
        $apiSecret = getenv('PORTONE_API_SECRET') ?: '';
        
        if (empty($apiKey) || empty($apiSecret)) {
            throw new Exception('PortOne API credentials not configured');
        }
        
        return new self($apiKey, $apiSecret);
    }
    
    // ========================================
    // 인증 관련 메서드
    // ========================================
    
    /**
     * 액세스 토큰 발급/갱신
     * 
     * @return string 액세스 토큰
     * @throws Exception 토큰 발급 실패 시
     */
    public function getAccessToken(): string
    {
        // 토큰이 유효하면 재사용
        if ($this->accessToken && $this->tokenExpiry && time() < $this->tokenExpiry - 60) {
            return $this->accessToken;
        }
        
        $response = $this->httpRequest('POST', '/users/getToken', [
            'imp_key' => $this->apiKey,
            'imp_secret' => $this->apiSecret
        ], false);
        
        if ($response['code'] !== 0) {
            throw new Exception('Failed to get access token: ' . ($response['message'] ?? 'Unknown error'));
        }
        
        $this->accessToken = $response['response']['access_token'];
        $this->tokenExpiry = $response['response']['expired_at'];
        
        return $this->accessToken;
    }
    
    // ========================================
    // 결제 조회 관련 메서드
    // ========================================
    
    /**
     * 결제 단건 조회 (imp_uid로 조회)
     * 
     * @param string $impUid 포트원 거래 고유번호
     * @return array 결제 정보
     */
    public function getPayment(string $impUid): array
    {
        return $this->httpRequest('GET', "/payments/{$impUid}");
    }
    
    /**
     * 결제 단건 조회 (merchant_uid로 조회)
     * 
     * @param string $merchantUid 가맹점 주문번호
     * @return array 결제 정보
     */
    public function getPaymentByMerchantUid(string $merchantUid): array
    {
        return $this->httpRequest('GET', "/payments/find/{$merchantUid}");
    }
    
    /**
     * 결제 목록 조회
     * 
     * @param array $params 조회 파라미터 (status, page, limit 등)
     * @return array 결제 목록
     */
    public function getPayments(array $params = []): array
    {
        $query = http_build_query($params);
        $endpoint = '/payments' . ($query ? "?{$query}" : '');
        return $this->httpRequest('GET', $endpoint);
    }
    
    /**
     * 결제 상태별 목록 조회
     * 
     * @param string $status 결제 상태 (all, ready, paid, cancelled, failed)
     * @param int $page 페이지 번호
     * @param int $limit 페이지당 개수
     * @return array 결제 목록
     */
    public function getPaymentsByStatus(string $status = 'all', int $page = 1, int $limit = 20): array
    {
        return $this->httpRequest('GET', "/payments/status/{$status}?page={$page}&limit={$limit}");
    }
    
    // ========================================
    // 결제 사전등록 관련 메서드
    // ========================================
    
    /**
     * 결제 사전등록 (금액 위변조 방지)
     * 
     * @param string $merchantUid 가맹점 주문번호
     * @param int $amount 결제 예정 금액
     * @return array 사전등록 결과
     */
    public function preparePayment(string $merchantUid, int $amount): array
    {
        return $this->httpRequest('POST', '/payments/prepare', [
            'merchant_uid' => $merchantUid,
            'amount' => $amount
        ]);
    }
    
    /**
     * 사전등록 정보 조회
     * 
     * @param string $merchantUid 가맹점 주문번호
     * @return array 사전등록 정보
     */
    public function getPreparedPayment(string $merchantUid): array
    {
        return $this->httpRequest('GET', "/payments/prepare/{$merchantUid}");
    }
    
    // ========================================
    // 결제 취소/환불 관련 메서드
    // ========================================
    
    /**
     * 결제 취소
     * 
     * @param array $params 취소 파라미터
     *   - imp_uid: 포트원 거래 고유번호 (imp_uid 또는 merchant_uid 중 하나 필수)
     *   - merchant_uid: 가맹점 주문번호
     *   - amount: 취소 금액 (부분취소 시 필수, 미입력시 전액취소)
     *   - tax_free: 취소 금액 중 면세금액
     *   - checksum: 취소 전 환불가능금액 (검증용)
     *   - reason: 취소 사유
     *   - refund_holder: 환불계좌 예금주 (가상계좌 환불 시)
     *   - refund_bank: 환불계좌 은행코드 (가상계좌 환불 시)
     *   - refund_account: 환불계좌 번호 (가상계좌 환불 시)
     *   - refund_tel: 환불계좌 연락처 (가상계좌 환불 시, 일부 PG사 필수)
     * @return array 취소 결과
     */
    public function cancelPayment(array $params): array
    {
        return $this->httpRequest('POST', '/payments/cancel', $params);
    }
    
    // ========================================
    // 가상계좌 관련 메서드
    // ========================================
    
    /**
     * 가상계좌 발급 (비인증 결제)
     * 
     * @param array $params 가상계좌 발급 파라미터
     *   - merchant_uid: 가맹점 주문번호 (필수)
     *   - amount: 결제 금액 (필수)
     *   - vbank_code: 가상계좌 은행코드 (필수)
     *   - vbank_due: 입금기한 (UNIX timestamp 또는 YYYYMMDD)
     *   - vbank_holder: 가상계좌 예금주 (필수)
     *   - name: 상품명
     *   - buyer_name: 주문자명
     *   - buyer_email: 주문자 이메일
     *   - buyer_tel: 주문자 연락처
     *   - buyer_addr: 주문자 주소
     *   - buyer_postcode: 주문자 우편번호
     *   - pg: PG사 구분코드
     *   - notice_url: 입금통보 URL
     * @return array 가상계좌 발급 결과
     */
    public function createVirtualAccount(array $params): array
    {
        return $this->httpRequest('POST', '/vbanks', $params);
    }
    
    /**
     * 가상계좌 발급 수정 (입금기한 연장 등)
     * 
     * @param string $impUid 포트원 거래 고유번호
     * @param int $vbankDue 새로운 입금기한 (UNIX timestamp)
     * @return array 수정 결과
     */
    public function updateVirtualAccount(string $impUid, int $vbankDue): array
    {
        return $this->httpRequest('PUT', "/vbanks/{$impUid}", [
            'vbank_due' => $vbankDue
        ]);
    }
    
    // ========================================
    // 빌링키 관련 메서드
    // ========================================
    
    /**
     * 빌링키 발급 (비인증 - REST API 방식)
     * 
     * @param string $customerUid 구매자 고유번호 (빌링키와 1:1 매핑)
     * @param array $params 빌링키 발급 파라미터
     *   - card_number: 카드번호 (필수)
     *   - expiry: 카드 유효기간 YYYY-MM (필수)
     *   - birth: 생년월일6자리 또는 사업자등록번호10자리 (필수)
     *   - pwd_2digit: 카드 비밀번호 앞 2자리 (일부 PG사 필수)
     *   - pg: PG사 구분코드
     *   - customer_name: 카드 소유자 이름
     *   - customer_tel: 카드 소유자 연락처
     *   - customer_email: 카드 소유자 이메일
     *   - customer_addr: 카드 소유자 주소
     *   - customer_postcode: 카드 소유자 우편번호
     * @return array 빌링키 발급 결과
     */
    public function issueBillingKey(string $customerUid, array $params): array
    {
        return $this->httpRequest('POST', "/subscribe/customers/{$customerUid}", $params);
    }
    
    /**
     * 빌링키 정보 조회
     * 
     * @param string $customerUid 구매자 고유번호
     * @return array 빌링키 정보
     */
    public function getBillingKey(string $customerUid): array
    {
        return $this->httpRequest('GET', "/subscribe/customers/{$customerUid}");
    }
    
    /**
     * 복수 빌링키 정보 조회
     * 
     * @param array $customerUids 구매자 고유번호 배열
     * @return array 빌링키 정보 목록
     */
    public function getBillingKeys(array $customerUids): array
    {
        $uids = implode(',', $customerUids);
        return $this->httpRequest('GET', "/subscribe/customers?customer_uid[]={$uids}");
    }
    
    /**
     * 빌링키 삭제
     * 
     * @param string $customerUid 구매자 고유번호
     * @return array 삭제 결과
     */
    public function deleteBillingKey(string $customerUid): array
    {
        return $this->httpRequest('DELETE', "/subscribe/customers/{$customerUid}");
    }
    
    // ========================================
    // 빌링키 결제 관련 메서드
    // ========================================
    
    /**
     * 빌링키로 결제 요청 (비인증 결제)
     * 
     * @param array $params 결제 파라미터
     *   - customer_uid: 구매자 고유번호 (필수)
     *   - merchant_uid: 가맹점 주문번호 (필수)
     *   - amount: 결제 금액 (필수)
     *   - name: 상품명 (필수)
     *   - tax_free: 면세금액
     *   - buyer_name: 주문자명
     *   - buyer_email: 주문자 이메일
     *   - buyer_tel: 주문자 연락처
     *   - buyer_addr: 주문자 주소
     *   - buyer_postcode: 주문자 우편번호
     *   - card_quota: 카드 할부개월수
     *   - custom_data: 거래 추가정보 (JSON 문자열)
     *   - notice_url: 결제결과 통보 URL
     * @return array 결제 결과
     */
    public function payWithBillingKey(array $params): array
    {
        return $this->httpRequest('POST', '/subscribe/payments/again', $params);
    }
    
    // ========================================
    // 예약 결제 관련 메서드
    // ========================================
    
    /**
     * 예약 결제 등록
     * 
     * @param array $params 예약 결제 파라미터
     *   - customer_uid: 구매자 고유번호 (필수)
     *   - schedules: 예약 결제 목록 (필수)
     *     각 항목: merchant_uid, schedule_at, amount, name, buyer_name, buyer_email, buyer_tel 등
     * @return array 예약 결제 등록 결과
     */
    public function schedulePayment(array $params): array
    {
        return $this->httpRequest('POST', '/subscribe/payments/schedule', $params);
    }
    
    /**
     * 예약 결제 조회 (customer_uid 기준)
     * 
     * @param string $customerUid 구매자 고유번호
     * @param int $page 페이지 번호
     * @param int $limit 페이지당 개수
     * @return array 예약 결제 목록
     */
    public function getScheduledPayments(string $customerUid, int $page = 1, int $limit = 20): array
    {
        return $this->httpRequest('GET', "/subscribe/payments/schedule/customers/{$customerUid}?page={$page}&limit={$limit}");
    }
    
    /**
     * 예약 결제 조회 (merchant_uid 기준)
     * 
     * @param string $merchantUid 가맹점 주문번호
     * @return array 예약 결제 정보
     */
    public function getScheduledPaymentByMerchantUid(string $merchantUid): array
    {
        return $this->httpRequest('GET', "/subscribe/payments/schedule/{$merchantUid}");
    }
    
    /**
     * 예약 결제 취소
     * 
     * @param string $customerUid 구매자 고유번호
     * @param array $merchantUids 취소할 가맹점 주문번호 배열
     * @return array 취소 결과
     */
    public function unschedulePayment(string $customerUid, array $merchantUids): array
    {
        return $this->httpRequest('POST', '/subscribe/payments/unschedule', [
            'customer_uid' => $customerUid,
            'merchant_uid' => $merchantUids
        ]);
    }
    
    // ========================================
    // 유틸리티 메서드
    // ========================================
    
    /**
     * 결제 금액 검증
     * 
     * @param string $impUid 포트원 거래 고유번호
     * @param int $expectedAmount 예상 금액
     * @return bool 금액 일치 여부
     */
    public function verifyPaymentAmount(string $impUid, int $expectedAmount): bool
    {
        $payment = $this->getPayment($impUid);
        
        if ($payment['code'] !== 0) {
            return false;
        }
        
        return $payment['response']['amount'] === $expectedAmount;
    }
    
    /**
     * 결제 상태 검증
     * 
     * @param string $impUid 포트원 거래 고유번호
     * @param string $expectedStatus 예상 상태 (ready, paid, cancelled, failed)
     * @return bool 상태 일치 여부
     */
    public function verifyPaymentStatus(string $impUid, string $expectedStatus): bool
    {
        $payment = $this->getPayment($impUid);
        
        if ($payment['code'] !== 0) {
            return false;
        }
        
        return $payment['response']['status'] === $expectedStatus;
    }
    
    /**
     * 은행 코드 목록 조회
     * 
     * @return array 은행 코드 목록
     */
    public static function getBankCodes(): array
    {
        return [
            '04' => 'KB국민은행',
            '23' => 'SC제일은행',
            '39' => '경남은행',
            '34' => '광주은행',
            '03' => '기업은행',
            '11' => '농협',
            '31' => '대구은행',
            '32' => '부산은행',
            '02' => '산업은행',
            '45' => '새마을금고',
            '07' => '수협',
            '88' => '신한은행',
            '48' => '신협',
            '05' => '외환은행',
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
    }
    
    // ========================================
    // HTTP 요청 처리
    // ========================================
    
    /**
     * HTTP 요청 실행
     * 
     * @param string $method HTTP 메서드
     * @param string $endpoint API 엔드포인트
     * @param array|null $data 요청 데이터
     * @param bool $withAuth 인증 토큰 포함 여부
     * @return array API 응답
     * @throws Exception 요청 실패 시
     */
    private function httpRequest(string $method, string $endpoint, ?array $data = null, bool $withAuth = true): array
    {
        $url = self::API_BASE_URL . $endpoint;
        
        $headers = [
            'Content-Type: application/json'
        ];
        
        if ($withAuth) {
            $token = $this->getAccessToken();
            $headers[] = "Authorization: Bearer {$token}";
        }
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'GET':
            default:
                break;
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL Error: {$error}");
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON Decode Error: " . json_last_error_msg());
        }
        
        return $decoded;
    }
}
