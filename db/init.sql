-- Docker & Payment API Database Initialization Script
-- 이 스크립트는 Docker API 로그 및 포트원 결제 정보를 저장하기 위한 테이블을 생성합니다.
-- 참고: Docker API는 DB 없이도 동작 가능합니다. 결제 API는 DB 사용을 권장합니다.

-- 데이터베이스 생성
CREATE DATABASE IF NOT EXISTS docker_api
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE docker_api;

-- API 요청 로그 테이블 (선택사항)
CREATE TABLE IF NOT EXISTS api_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id VARCHAR(36) NOT NULL,
    method VARCHAR(10) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    request_body TEXT,
    response_code INT,
    response_body TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    execution_time_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at),
    INDEX idx_endpoint (endpoint),
    INDEX idx_method (method)
) ENGINE=InnoDB;

-- API 키 관리 테이블 (선택사항 - 인증이 필요한 경우)
CREATE TABLE IF NOT EXISTS api_keys (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    api_key VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    permissions JSON,
    is_active BOOLEAN DEFAULT TRUE,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_api_key (api_key),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB;

-- 시스템 설정 테이블
CREATE TABLE IF NOT EXISTS system_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 기본 설정 삽입
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('api_rate_limit', '100', 'API 호출 제한 (분당)'),
('log_retention_days', '30', '로그 보관 기간 (일)'),
('require_auth', 'false', 'API 인증 필수 여부')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- ========================================
-- 포트원 결제 API 테이블
-- ========================================

-- 결제 내역 테이블
CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    imp_uid VARCHAR(50) UNIQUE COMMENT '포트원 거래 고유번호',
    merchant_uid VARCHAR(100) NOT NULL COMMENT '가맹점 주문번호',
    customer_uid VARCHAR(100) NULL COMMENT '구매자 고유번호 (빌링키)',
    
    -- 결제 정보
    amount INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '결제 금액',
    status VARCHAR(20) NOT NULL DEFAULT 'prepared' COMMENT '결제 상태 (prepared, ready, paid, cancelled, failed)',
    pay_method VARCHAR(20) NULL COMMENT '결제 수단 (card, vbank, trans, phone 등)',
    
    -- PG 정보
    pg_provider VARCHAR(50) NULL COMMENT 'PG사 코드',
    pg_tid VARCHAR(100) NULL COMMENT 'PG사 거래 ID',
    
    -- 상품 정보
    product_name VARCHAR(255) NULL COMMENT '상품명',
    
    -- 구매자 정보
    buyer_name VARCHAR(100) NULL COMMENT '구매자 이름',
    buyer_email VARCHAR(255) NULL COMMENT '구매자 이메일',
    buyer_tel VARCHAR(50) NULL COMMENT '구매자 전화번호',
    buyer_addr TEXT NULL COMMENT '구매자 주소',
    buyer_postcode VARCHAR(20) NULL COMMENT '구매자 우편번호',
    
    -- 카드 결제 정보
    card_name VARCHAR(50) NULL COMMENT '카드사 이름',
    card_number VARCHAR(30) NULL COMMENT '카드번호 (마스킹)',
    card_quota TINYINT UNSIGNED NULL COMMENT '할부 개월수',
    
    -- 가상계좌 정보
    vbank_code VARCHAR(10) NULL COMMENT '가상계좌 은행코드',
    vbank_name VARCHAR(50) NULL COMMENT '가상계좌 은행명',
    vbank_num VARCHAR(50) NULL COMMENT '가상계좌 계좌번호',
    vbank_holder VARCHAR(100) NULL COMMENT '가상계좌 예금주',
    vbank_date DATETIME NULL COMMENT '가상계좌 입금기한',
    
    -- 취소/환불 정보
    cancel_amount INT UNSIGNED NULL COMMENT '취소 금액',
    cancel_reason VARCHAR(255) NULL COMMENT '취소 사유',
    
    -- 실패 정보
    fail_reason VARCHAR(255) NULL COMMENT '실패 사유',
    
    -- 기타
    custom_data JSON NULL COMMENT '추가 데이터',
    receipt_url VARCHAR(500) NULL COMMENT '영수증 URL',
    
    -- 타임스탬프
    paid_at DATETIME NULL COMMENT '결제 완료 시각',
    cancelled_at DATETIME NULL COMMENT '취소 시각',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_merchant_uid (merchant_uid),
    INDEX idx_customer_uid (customer_uid),
    INDEX idx_status (status),
    INDEX idx_pay_method (pay_method),
    INDEX idx_created_at (created_at),
    INDEX idx_paid_at (paid_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='결제 내역';

-- 빌링키 테이블 (정기결제용)
CREATE TABLE IF NOT EXISTS billing_keys (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_uid VARCHAR(100) NOT NULL UNIQUE COMMENT '구매자 고유번호 (빌링키 식별자)',
    
    -- PG 정보
    pg_provider VARCHAR(50) NULL COMMENT 'PG사 코드',
    pg_id VARCHAR(100) NULL COMMENT 'PG사 상점 ID',
    
    -- 카드 정보
    card_name VARCHAR(50) NULL COMMENT '카드사 이름',
    card_code VARCHAR(10) NULL COMMENT '카드사 코드',
    card_number VARCHAR(30) NULL COMMENT '카드번호 (마스킹)',
    
    -- 고객 정보
    customer_name VARCHAR(100) NULL COMMENT '카드 소유자 이름',
    customer_tel VARCHAR(50) NULL COMMENT '카드 소유자 연락처',
    customer_email VARCHAR(255) NULL COMMENT '카드 소유자 이메일',
    customer_addr TEXT NULL COMMENT '카드 소유자 주소',
    customer_postcode VARCHAR(20) NULL COMMENT '카드 소유자 우편번호',
    
    -- 상태
    status VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT '상태 (active, deleted)',
    
    -- 타임스탬프
    inserted_at DATETIME NULL COMMENT '포트원 등록 시각',
    deleted_at DATETIME NULL COMMENT '삭제 시각',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_status (status),
    INDEX idx_customer_email (customer_email),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='빌링키 (정기결제용)';

-- 예약 결제 테이블
CREATE TABLE IF NOT EXISTS payment_schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_uid VARCHAR(100) NOT NULL COMMENT '구매자 고유번호',
    merchant_uid VARCHAR(100) NOT NULL COMMENT '가맹점 주문번호',
    imp_uid VARCHAR(50) NULL COMMENT '결제 완료 후 포트원 거래번호',
    
    -- 결제 정보
    amount INT UNSIGNED NOT NULL COMMENT '결제 예정 금액',
    product_name VARCHAR(255) NULL COMMENT '상품명',
    
    -- 구매자 정보
    buyer_name VARCHAR(100) NULL COMMENT '구매자 이름',
    buyer_email VARCHAR(255) NULL COMMENT '구매자 이메일',
    buyer_tel VARCHAR(50) NULL COMMENT '구매자 전화번호',
    
    -- 예약 정보
    schedule_at DATETIME NOT NULL COMMENT '예약 결제 시각',
    status VARCHAR(20) NOT NULL DEFAULT 'scheduled' COMMENT '상태 (scheduled, paid, failed, cancelled)',
    fail_reason VARCHAR(255) NULL COMMENT '실패 사유',
    
    -- 타임스탬프
    paid_at DATETIME NULL COMMENT '결제 완료 시각',
    cancelled_at DATETIME NULL COMMENT '취소 시각',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_customer_merchant (customer_uid, merchant_uid),
    INDEX idx_schedule_at (schedule_at),
    INDEX idx_status (status),
    INDEX idx_customer_uid (customer_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='예약 결제';

-- 웹훅 로그 테이블
CREATE TABLE IF NOT EXISTS webhook_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(50) NOT NULL COMMENT '웹훅 소스 (portone, business)',
    event_type VARCHAR(50) NULL COMMENT '이벤트 타입',
    imp_uid VARCHAR(50) NULL COMMENT '포트원 거래번호',
    merchant_uid VARCHAR(100) NULL COMMENT '가맹점 주문번호',
    
    -- 데이터
    payload JSON NULL COMMENT '웹훅 원본 데이터',
    
    -- 처리 정보
    status VARCHAR(20) NOT NULL DEFAULT 'received' COMMENT '처리 상태 (received, processed, error, triggered)',
    message TEXT NULL COMMENT '처리 메시지',
    
    -- 타임스탬프
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_source (source),
    INDEX idx_imp_uid (imp_uid),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='웹훅 로그';

-- 기본 설정 추가 (포트원 관련)
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('portone_api_version', 'v1', '포트원 API 버전'),
('portone_webhook_enabled', 'true', '포트원 웹훅 활성화 여부'),
('payment_notification_email', '', '결제 알림 수신 이메일')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- 초기화 완료 메시지
SELECT 'Docker & Payment API Database initialized successfully!' AS message;
