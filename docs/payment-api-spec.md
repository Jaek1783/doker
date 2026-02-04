# PortOne 결제 서비스 API 명세

## 개요

- **Base URL (Docker 기본 설정 기준)**: `http://localhost:8080/api/v1`
- **결제 도메인**: PortOne V1 API 기반
- **지원 기능**:
  - 일반 결제: 조회, 사전등록, 검증, 취소/환불
  - 가상계좌: 발급, 입금 처리(웹훅)
  - 정기결제: 빌링키 발급/삭제, 빌링키 결제, 예약 결제
  - 웹훅: 결제 완료/취소/입금/실패 이벤트 수신

모든 응답은 다음 공통 포맷을 따릅니다.

```json
{
  "status": "success | error",
  "data": { ... },      // 성공 시
  "message": "string",  // 선택
  "error": {            // 실패 시
    "code": 400,
    "message": "에러 메시지"
  }
}
```

---

## 1. Payments (결제)

### 1-1. 결제 목록 조회

- **HTTP**: `GET /payments`

**Query Parameters**

| 이름 | 타입 | 필수 | 설명 |
|------|------|------|------|
| `status` | string | X | 결제 상태 (`all`, `ready`, `paid`, `cancelled`, `failed`) |
| `page` | int | X | 페이지 번호 (기본: 1) |
| `limit` | int | X | 페이지 크기 (기본: 20) |

**Response 예시**

```json
{
  "status": "success",
  "data": {
    "payments": [
      {
        "imp_uid": "imp_123456789",
        "merchant_uid": "order_001",
        "amount": 10000,
        "status": "paid",
        "pay_method": "card"
      }
    ],
    "total": 1,
    "page": 1,
    "limit": 20
  }
}
```

---

### 1-2. 결제 단건 조회 (imp_uid)

- **HTTP**: `GET /payments/{imp_uid}`

**Path Parameters**

| 이름 | 설명 |
|------|------|
| `imp_uid` | PortOne 거래 고유번호 |

**Response 예시**

```json
{
  "status": "success",
  "data": {
    "imp_uid": "imp_123456789",
    "merchant_uid": "order_001",
    "amount": 10000,
    "status": "paid",
    "pay_method": "card",
    "card_name": "신한카드",
    "card_number": "1234-****-****-5678",
    "buyer_name": "홍길동",
    "buyer_email": "test@example.com",
    "paid_at": 1706745600
  }
}
```

---

### 1-3. 주문번호로 결제 조회 (merchant_uid)

- **HTTP**: `GET /payments/find/{merchant_uid}`

**Path Parameters**

| 이름 | 설명 |
|------|------|
| `merchant_uid` | 가맹점 주문번호 |

---

### 1-4. 결제 사전등록 (금액 위변조 방지)

- **HTTP**: `POST /payments/prepare`
- **설명**: 결제 전에 주문번호와 금액을 PortOne에 등록하여 결제 후 금액 검증에 사용합니다.

**Request Body**

```json
{
  "merchant_uid": "order_20240101_001",
  "amount": 10000,
  "name": "테스트 상품 (선택)",
  "buyer_name": "홍길동 (선택)",
  "buyer_email": "test@example.com (선택)",
  "buyer_tel": "010-1234-5678 (선택)"
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| `merchant_uid` | string | O | 가맹점 주문번호 (중복 불가) |
| `amount` | int | O | 결제 예정 금액 |
| `name` | string | X | 상품명 |
| `buyer_*` | string | X | 구매자 정보 (DB 저장용) |

**성공 Response**

```json
{
  "status": "success",
  "data": {
    "merchant_uid": "order_20240101_001",
    "amount": 10000
  },
  "message": "Payment prepared successfully"
}
```

---

### 1-5. 결제 검증

- **HTTP**: `POST /payments/verify`
- **설명**: 포트원 결제 완료 후, 금액/상태를 서버에서 재검증합니다.

**Request Body**

```json
{
  "imp_uid": "imp_123456789",
  "amount": 10000,
  "status": "paid"
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| `imp_uid` | string | O | PortOne 거래번호 |
| `amount` | int | X | 예상 결제 금액 (사전등록 금액) |
| `status` | string | X | 예상 상태 (`paid` 등) |

**성공 Response**

```json
{
  "status": "success",
  "data": {
    "valid": true,
    "payment": { "...": "PortOne 결제 원본 데이터" },
    "errors": []
  }
}
```

---

### 1-6. 결제 취소 / 환불

- **HTTP**: `POST /payments/cancel`

**Request Body**

```json
{
  "imp_uid": "imp_123456789",
  "merchant_uid": "order_001",
  "amount": 5000,
  "tax_free": 0,
  "checksum": 10000,
  "reason": "고객 요청에 의한 취소",
  "refund_holder": "홍길동",
  "refund_bank": "88",
  "refund_account": "110123456789",
  "refund_tel": "010-1234-5678"
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| `imp_uid` | string | △ | 포트원 거래번호 (또는 `merchant_uid`) |
| `merchant_uid` | string | △ | 가맹점 주문번호 (`imp_uid` 또는 `merchant_uid` 중 하나 필수) |
| `amount` | int | X | 취소 금액 (생략 시 전액취소) |
| `tax_free` | int | X | 면세 금액 |
| `checksum` | int | X | 취소 전 환불가능금액 (검증용) |
| `reason` | string | X | 취소 사유 |
| `refund_*` | string | X | 가상계좌 환불 계좌 정보 (필요 시) |

**성공 Response**

```json
{
  "status": "success",
  "data": {
    "imp_uid": "imp_123456789",
    "merchant_uid": "order_001",
    "status": "cancelled",
    "cancel_amount": 5000
  },
  "message": "Payment cancelled successfully"
}
```

---

### 1-7. 가상계좌 발급

- **HTTP**: `POST /payments/vbank`

**Request Body**

```json
{
  "merchant_uid": "vbank_20240101_001",
  "amount": 50000,
  "vbank_code": "88",
  "vbank_holder": "홍길동",
  "vbank_due": 1707350400,
  "name": "테스트 상품",
  "buyer_name": "홍길동",
  "buyer_email": "test@example.com",
  "buyer_tel": "010-1234-5678",
  "pg": "html5_inicis",
  "notice_url": "https://example.com/vbank/notice"
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| `merchant_uid` | string | O | 주문번호 |
| `amount` | int | O | 결제 금액 |
| `vbank_code` | string | O | 은행 코드 (예: `88` 신한, `04` 국민 등) |
| `vbank_holder` | string | O | 예금주 |
| `vbank_due` | int | X | 입금기한 (Unix timestamp, 기본: 7일 후) |
| 기타 |  | X | 주문자/알림 정보 |

**성공 Response**

```json
{
  "status": "success",
  "data": {
    "imp_uid": "imp_vbank_123",
    "merchant_uid": "vbank_20240101_001",
    "amount": 50000,
    "status": "ready",
    "vbank_name": "신한은행",
    "vbank_num": "110-123-456789",
    "vbank_holder": "홍길동",
    "vbank_date": 1707350400
  },
  "message": "Virtual account created successfully"
}
```

---

## 2. Subscriptions (정기결제)

### 2-1. 빌링키 발급

- **HTTP**: `POST /subscriptions/billing-key`

**Request Body**

```json
{
  "customer_uid": "customer_001",
  "card_number": "1234-5678-9012-3456",
  "expiry": "2025-12",
  "birth": "900101",
  "pwd_2digit": "00",
  "pg": "nice",
  "customer_name": "홍길동",
  "customer_tel": "010-1234-5678",
  "customer_email": "test@example.com"
}
```

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| `customer_uid` | string | O | 구매자 고유번호 (빌링키 식별자) |
| `card_number` | string | O | 카드번호 |
| `expiry` | string | O | 유효기간 (`YYYY-MM`) |
| `birth` | string | O | 생년월일 6자리 또는 사업자번호 10자리 |
| `pwd_2digit` | string | X | 카드 비밀번호 앞 2자리 |

**성공 Response (민감정보 제거 후)**

```json
{
  "status": "success",
  "data": {
    "customer_uid": "customer_001",
    "pg_provider": "nice",
    "card_name": "신한카드",
    "card_code": "361"
  },
  "message": "Billing key issued successfully"
}
```

---

### 2-2. 빌링키 조회

- **HTTP**: `GET /subscriptions/billing-key/{customer_uid}`

또는

- **HTTP**: `GET /subscriptions/billing-key?customer_uid[]=id1&customer_uid[]=id2`

---

### 2-3. 빌링키 삭제

- **HTTP**: `DELETE /subscriptions/billing-key/{customer_uid}`

---

### 2-4. 빌링키로 결제

- **HTTP**: `POST /subscriptions/pay`

**Request Body**

```json
{
  "customer_uid": "customer_001",
  "merchant_uid": "subscription_20240101_001",
  "amount": 9900,
  "name": "월간 구독",
  "tax_free": 0,
  "buyer_name": "홍길동",
  "buyer_email": "test@example.com",
  "buyer_tel": "010-1234-5678",
  "custom_data": "{\"plan\":\"basic\"}"
}
```

**성공 Response**

```json
{
  "status": "success",
  "data": {
    "imp_uid": "imp_sub_123",
    "merchant_uid": "subscription_20240101_001",
    "amount": 9900,
    "status": "paid",
    "paid_at": 1706745600
  },
  "message": "Payment successful"
}
```

---

### 2-5. 예약 결제 등록

- **HTTP**: `POST /subscriptions/schedule`

**Request Body**

```json
{
  "customer_uid": "customer_001",
  "schedules": [
    {
      "merchant_uid": "schedule_202402",
      "schedule_at": 1706745600,
      "amount": 9900,
      "name": "2월 정기결제",
      "buyer_name": "홍길동",
      "buyer_email": "test@example.com"
    }
  ]
}
```

---

### 2-6. 예약 결제 조회

- **HTTP**: `GET /subscriptions/schedule/{customer_uid}`

**Query Parameters**

| 이름 | 설명 |
|------|------|
| `by` | `customer`(기본) 또는 `merchant` |
| `page` | 페이지 번호 |
| `limit` | 페이지 크기 |

---

### 2-7. 예약 결제 취소

- **HTTP**: `DELETE /subscriptions/schedule/{customer_uid}`

**Request Body**

```json
{
  "merchant_uid": ["schedule_202402", "schedule_202403"]
}
```

---

## 3. Webhooks (웹훅)

### 3-1. PortOne 웹훅 수신

- **HTTP**: `POST /webhooks/portone`
- **설명**: PortOne에서 결제/취소/입금 등의 이벤트를 전송하는 엔드포인트입니다.

**Webhook URL (예시)**

```text
https://api.your-domain.com/api/v1/webhooks/portone
```

**Request Body (예시)**

```json
{
  "imp_uid": "imp_123456789",
  "merchant_uid": "order_001",
  "status": "paid"
}
```

**서버 처리**

- PortOne API를 통해 `imp_uid`로 결제 정보 재조회
- 상태별 처리:
  - `paid` → 결제 완료, `payments`/`payment_schedules` 업데이트
  - `cancelled` → 취소 정보 업데이트
  - `ready` + `pay_method = vbank` → 가상계좌 발급/입금대기
  - `failed` → 실패 정보 저장
- `webhook_logs` 테이블에 로그 저장

**성공 Response**

```json
{
  "status": "success",
  "data": {
    "status": "ok"
  }
}
```

---

## 4. 에러 응답 규칙

### 공통 에러 포맷

```json
{
  "status": "error",
  "error": {
    "code": 400,
    "message": "merchant_uid and amount are required"
  }
}
```

| HTTP 코드 | 설명 |
|-----------|------|
| 400 | 잘못된 요청 (파라미터 오류 등) |
| 404 | 리소스 없음 |
| 405 | 허용되지 않는 메서드 |
| 500 | 서버 내부 오류 |

---

## 5. 참고: 은행 코드 (가상계좌)

| 코드 | 은행명 |
|------|--------|
| 04 | KB국민은행 |
| 88 | 신한은행 |
| 20 | 우리은행 |
| 81 | 하나은행 |
| 11 | NH농협은행 |
| 03 | IBK기업은행 |
| 90 | 카카오뱅크 |
| 92 | 토스뱅크 |
| 89 | 케이뱅크 |
| 71 | 우체국 |

