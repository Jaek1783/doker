# Docker & Payment API Server

Docker 컨테이너 관리 및 포트원(PortOne) 결제 연동을 위한 RESTful API 서버입니다.

## 기능

### Docker Management
- **Containers**: 컨테이너 생성, 시작, 중지, 재시작, 삭제, 로그 조회
- **Images**: 이미지 목록, Pull, Build, 삭제
- **Volumes**: 볼륨 생성, 조회, 삭제
- **Networks**: 네트워크 생성, 연결, 분리, 삭제
- **System**: Docker 시스템 정보, 버전, 디스크 사용량
- **Compose**: Docker Compose 프로젝트 관리

### Payment (PortOne V1 API)
- **Payments**: 결제 조회, 사전등록, 취소/환불, 가상계좌 발급
- **Subscriptions**: 빌링키 발급/삭제, 정기결제, 예약결제
- **Webhooks**: 결제 완료/취소/입금 알림 수신

## 요구사항

- PHP 8.0+
- Apache with mod_rewrite
- Docker (Docker 관리 기능 사용 시)
- MySQL 8.0+ (결제 정보 저장용)
- curl 확장 (포트원 API 통신용)

## 설치

### Docker를 이용한 설치 (권장)

```bash
# 1. 환경변수 설정
cp .env.example .env
# .env 파일을 열어 PORTONE_API_KEY, PORTONE_API_SECRET 등 설정

# 2. 컨테이너 실행
docker-compose up -d

# 3. (개발용) phpMyAdmin 포함 실행
docker-compose --profile dev up -d
```

### 수동 설치

1. 웹 서버 DocumentRoot를 이 디렉토리로 설정
2. `.env.example`을 `.env`로 복사하고 설정 수정
3. MySQL 데이터베이스 초기화:
   ```bash
   mysql -u root -p < db/init.sql
   ```

## 포트원 설정

1. [포트원 관리자 콘솔](https://admin.portone.io/)에서 REST API 키 발급
2. `.env` 파일에 설정:
   ```env
   PORTONE_API_KEY=your_api_key
   PORTONE_API_SECRET=your_api_secret
   ```
3. 웹훅 URL 등록: `https://your-domain.com/api/v1/webhooks/portone`

## API 엔드포인트

### Base URL
```
/api/v1
```

### Containers
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /containers | 모든 컨테이너 목록 |
| GET | /containers/{id} | 컨테이너 상세 정보 |
| POST | /containers | 새 컨테이너 생성 |
| POST | /containers/{id}/start | 컨테이너 시작 |
| POST | /containers/{id}/stop | 컨테이너 중지 |
| POST | /containers/{id}/restart | 컨테이너 재시작 |
| DELETE | /containers/{id} | 컨테이너 삭제 |
| GET | /containers/{id}/logs | 컨테이너 로그 |
| GET | /containers/{id}/stats | 리소스 사용량 |

### Images
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /images | 모든 이미지 목록 |
| GET | /images/{id} | 이미지 상세 정보 |
| POST | /images/pull | 이미지 Pull |
| DELETE | /images/{id} | 이미지 삭제 |

### Volumes
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /volumes | 모든 볼륨 목록 |
| POST | /volumes | 새 볼륨 생성 |
| GET | /volumes/{name} | 볼륨 상세 정보 |
| DELETE | /volumes/{name} | 볼륨 삭제 |

### Networks
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /networks | 모든 네트워크 목록 |
| POST | /networks | 새 네트워크 생성 |
| GET | /networks/{id} | 네트워크 상세 정보 |
| DELETE | /networks/{id} | 네트워크 삭제 |
| POST | /networks/{id}/connect | 컨테이너 연결 |
| POST | /networks/{id}/disconnect | 컨테이너 분리 |

### System
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /system/info | Docker 시스템 정보 |
| GET | /system/version | Docker 버전 |
| GET | /system/df | 디스크 사용량 |
| POST | /system/prune | 미사용 리소스 정리 |

---

## Payment API (PortOne)

### Payments (결제)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /payments | 결제 목록 조회 |
| GET | /payments/{imp_uid} | 결제 단건 조회 |
| GET | /payments/find/{merchant_uid} | 주문번호로 조회 |
| POST | /payments/prepare | 결제 사전등록 |
| POST | /payments/cancel | 결제 취소/환불 |
| POST | /payments/vbank | 가상계좌 발급 |
| POST | /payments/verify | 결제 검증 |

### Subscriptions (정기결제)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /subscriptions | 구독 목록 조회 |
| GET | /subscriptions/billing-key/{customer_uid} | 빌링키 조회 |
| POST | /subscriptions/billing-key | 빌링키 발급 |
| DELETE | /subscriptions/billing-key/{customer_uid} | 빌링키 삭제 |
| POST | /subscriptions/pay | 빌링키 결제 |
| GET | /subscriptions/schedule/{customer_uid} | 예약결제 조회 |
| POST | /subscriptions/schedule | 예약결제 등록 |
| DELETE | /subscriptions/schedule/{customer_uid} | 예약결제 취소 |

### Webhooks
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | /webhooks/portone | 포트원 웹훅 수신 |

---

## 사용 예시

### 컨테이너 목록 조회
```bash
curl http://localhost/api/v1/containers
```

### 새 컨테이너 생성
```bash
curl -X POST http://localhost/api/v1/containers \
  -H "Content-Type: application/json" \
  -d '{"image": "nginx:latest", "name": "my-nginx", "ports": ["8080:80"]}'
```

### 컨테이너 중지
```bash
curl -X POST http://localhost/api/v1/containers/my-nginx/stop
```

### 이미지 Pull
```bash
curl -X POST http://localhost/api/v1/images/pull \
  -H "Content-Type: application/json" \
  -d '{"image": "redis:latest"}'
```

---

## Payment API 사용 예시

### 결제 사전등록 (금액 위변조 방지)
```bash
curl -X POST http://localhost:8080/api/v1/payments/prepare \
  -H "Content-Type: application/json" \
  -d '{
    "merchant_uid": "order_20240101_001",
    "amount": 10000
  }'
```

### 결제 조회
```bash
# imp_uid로 조회
curl http://localhost:8080/api/v1/payments/imp_123456789

# merchant_uid로 조회
curl http://localhost:8080/api/v1/payments/find/order_20240101_001
```

### 결제 검증
```bash
curl -X POST http://localhost:8080/api/v1/payments/verify \
  -H "Content-Type: application/json" \
  -d '{
    "imp_uid": "imp_123456789",
    "amount": 10000,
    "status": "paid"
  }'
```

### 결제 취소
```bash
curl -X POST http://localhost:8080/api/v1/payments/cancel \
  -H "Content-Type: application/json" \
  -d '{
    "imp_uid": "imp_123456789",
    "reason": "고객 요청에 의한 취소",
    "amount": 5000
  }'
```

### 가상계좌 발급
```bash
curl -X POST http://localhost:8080/api/v1/payments/vbank \
  -H "Content-Type: application/json" \
  -d '{
    "merchant_uid": "vbank_20240101_001",
    "amount": 50000,
    "vbank_code": "88",
    "vbank_holder": "홍길동",
    "name": "테스트 상품",
    "buyer_name": "홍길동",
    "buyer_email": "test@example.com",
    "buyer_tel": "010-1234-5678"
  }'
```

### 빌링키 발급 (정기결제용)
```bash
curl -X POST http://localhost:8080/api/v1/subscriptions/billing-key \
  -H "Content-Type: application/json" \
  -d '{
    "customer_uid": "customer_001",
    "card_number": "1234-5678-9012-3456",
    "expiry": "2025-12",
    "birth": "900101",
    "pwd_2digit": "00",
    "customer_name": "홍길동",
    "customer_email": "test@example.com"
  }'
```

### 빌링키로 결제
```bash
curl -X POST http://localhost:8080/api/v1/subscriptions/pay \
  -H "Content-Type: application/json" \
  -d '{
    "customer_uid": "customer_001",
    "merchant_uid": "subscription_20240101_001",
    "amount": 9900,
    "name": "월간 구독"
  }'
```

### 예약 결제 등록
```bash
curl -X POST http://localhost:8080/api/v1/subscriptions/schedule \
  -H "Content-Type: application/json" \
  -d '{
    "customer_uid": "customer_001",
    "schedules": [
      {
        "merchant_uid": "schedule_20240201",
        "schedule_at": 1706745600,
        "amount": 9900,
        "name": "2월 정기결제"
      },
      {
        "merchant_uid": "schedule_20240301",
        "schedule_at": 1709251200,
        "amount": 9900,
        "name": "3월 정기결제"
      }
    ]
  }'
```

### 예약 결제 취소
```bash
curl -X DELETE http://localhost:8080/api/v1/subscriptions/schedule/customer_001 \
  -H "Content-Type: application/json" \
  -d '{
    "merchant_uid": ["schedule_20240201", "schedule_20240301"]
  }'
```

## 웹훅 설정

포트원 관리자 콘솔에서 웹훅 URL 등록:
```
https://your-domain.com/api/v1/webhooks/portone
```

지원하는 이벤트:
- `paid`: 결제 완료
- `cancelled`: 결제 취소
- `ready`: 가상계좌 발급 완료
- `vbank_deposit`: 가상계좌 입금 완료
- `failed`: 결제 실패

## 은행 코드 (가상계좌용)

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

## 라이선스

MIT License
