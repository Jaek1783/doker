-- Standard site-level payment schema
-- 각 사이트 전용 DB에 적용할 스키마
-- 이미 존재하는 테이블이 있으면 CREATE TABLE IF NOT EXISTS 로 인해 건너뜀

-- 1. payments (결제 이력)
CREATE TABLE IF NOT EXISTS payments (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT NULL,
    product_id      BIGINT NULL,
    product_name    VARCHAR(255) NULL,
    customer_uid    VARCHAR(100) NULL,
    merchant_uid    VARCHAR(255) NOT NULL,
    amount          INT UNSIGNED NOT NULL DEFAULT 0,
    payment         VARCHAR(50) NULL,
    payment_type    VARCHAR(50) NULL,
    buyer_email     VARCHAR(255) NULL,
    buyer_name      VARCHAR(100) NULL,
    buyer_tel       VARCHAR(50) NULL,
    status          VARCHAR(50) NOT NULL DEFAULT 'pending',
    payment_date    DATETIME NULL,
    refund_date     DATETIME NULL,
    refund_amount   INT UNSIGNED NULL,
    imp_uid         VARCHAR(100) NULL,
    invoice         VARCHAR(500) NULL,
    valid           TINYINT(1) NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_id (user_id),
    KEY idx_product_id (product_id),
    KEY idx_customer_uid (customer_uid),
    KEY idx_merchant_uid (merchant_uid),
    KEY idx_status (status),
    KEY idx_payment_date (payment_date),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. subscriptions (구독)
CREATE TABLE IF NOT EXISTS subscriptions (
    subscription_id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_uid                BIGINT NOT NULL,
    product_id              BIGINT NOT NULL,
    customer_uid            VARCHAR(100) NOT NULL,
    status                  VARCHAR(50) NOT NULL DEFAULT 'pending',
    billing_cycle           VARCHAR(50) NOT NULL DEFAULT 'monthly',
    amount                  INT UNSIGNED NOT NULL DEFAULT 0,
    current_period_start    DATETIME NULL,
    current_period_end      DATETIME NULL,
    next_billing_date       DATETIME NULL,
    auto_renew              TINYINT(1) NOT NULL DEFAULT 1,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    last_payment_id         BIGINT NULL,
    canceled_at             DATETIME NULL,
    cancel_reason           VARCHAR(500) NULL,
    suspended_at            DATETIME NULL,
    retry_count             INT UNSIGNED NOT NULL DEFAULT 0,
    last_reminder_sent      DATETIME NULL,
    reminder_count          INT UNSIGNED NOT NULL DEFAULT 0,
    KEY idx_user_uid (user_uid),
    KEY idx_product_id (product_id),
    KEY idx_customer_uid (customer_uid),
    KEY idx_status (status),
    KEY idx_next_billing_date (next_billing_date),
    KEY idx_auto_renew (auto_renew)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. products (상품)
CREATE TABLE IF NOT EXISTS products (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    price           INT UNSIGNED NOT NULL DEFAULT 0,
    description     TEXT NULL,
    duration_days   INT UNSIGNED NULL,
    sub_date        TINYINT UNSIGNED NULL COMMENT '매월 결제일(일)',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. subscription_payments (구독별 결제 기록)
CREATE TABLE IF NOT EXISTS subscription_payments (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    subscription_id BIGINT UNSIGNED NOT NULL,
    merchant_uid    VARCHAR(255) NOT NULL,
    amount          INT UNSIGNED NOT NULL DEFAULT 0,
    status          VARCHAR(50) NOT NULL DEFAULT 'pending',
    billing_date    DATETIME NULL,
    paid_at         DATETIME NULL,
    payment_uid     VARCHAR(100) NULL,
    period_start    DATETIME NULL,
    period_end      DATETIME NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_subscription_id (subscription_id),
    KEY idx_merchant_uid (merchant_uid),
    KEY idx_status (status),
    KEY idx_billing_date (billing_date),
    KEY idx_paid_at (paid_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. custom_payment_links (임의 결제 링크)
CREATE TABLE IF NOT EXISTS custom_payment_links (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_uid        BIGINT NOT NULL,
    amount          INT UNSIGNED NOT NULL DEFAULT 0,
    description     VARCHAR(255) NULL,
    token           VARCHAR(255) NOT NULL,
    expire_date     DATETIME NULL,
    created_by      BIGINT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status          VARCHAR(50) NOT NULL DEFAULT 'pending',
    paid_at         DATETIME NULL,
    imp_uid         VARCHAR(100) NULL,
    merchant_uid    VARCHAR(255) NULL,
    buyer_name      VARCHAR(100) NULL,
    buyer_email     VARCHAR(255) NULL,
    buyer_tel       VARCHAR(50) NULL,
    UNIQUE KEY uniq_token (token),
    KEY idx_user_uid (user_uid),
    KEY idx_status (status),
    KEY idx_expire_date (expire_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. payment_retry_schedule (결제 재시도 스케줄)
CREATE TABLE IF NOT EXISTS payment_retry_schedule (
    retry_id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    subscription_id     BIGINT UNSIGNED NOT NULL,
    original_payment_id BIGINT UNSIGNED NOT NULL,
    retry_date          DATETIME NOT NULL,
    retry_count         INT UNSIGNED NOT NULL DEFAULT 0,
    status              VARCHAR(50) NOT NULL DEFAULT 'pending',
    processed_at        DATETIME NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_subscription_id (subscription_id),
    KEY idx_retry_date (retry_date),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. subscription_reschedule_logs (재예약 로그)
CREATE TABLE IF NOT EXISTS subscription_reschedule_logs (
    log_id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    subscription_id    BIGINT UNSIGNED NOT NULL,
    customer_uid       VARCHAR(100) NOT NULL,
    user_uid           BIGINT NOT NULL,
    old_billing_date   DATETIME NULL,
    new_billing_date   DATETIME NULL,
    reschedule_days    INT NOT NULL DEFAULT 0,
    reason             VARCHAR(500) NULL,
    rescheduled_by     BIGINT NULL,
    rescheduled_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_subscription_id (subscription_id),
    KEY idx_user_uid (user_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. subscription_cancellations (구독 취소 로그)
CREATE TABLE IF NOT EXISTS subscription_cancellations (
    cancellation_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    subscription_id BIGINT UNSIGNED NOT NULL,
    customer_uid    VARCHAR(100) NOT NULL,
    user_uid        BIGINT NOT NULL,
    reason          VARCHAR(500) NULL,
    canceled_by     BIGINT NULL,
    canceled_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_subscription_id (subscription_id),
    KEY idx_user_uid (user_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. subscription_suspensions (구독 중단 로그)
CREATE TABLE IF NOT EXISTS subscription_suspensions (
    suspension_id  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    subscription_id BIGINT UNSIGNED NOT NULL,
    customer_uid   VARCHAR(100) NOT NULL,
    user_uid       BIGINT NOT NULL,
    reason         VARCHAR(500) NULL,
    suspended_by   BIGINT NULL,
    suspended_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_subscription_id (subscription_id),
    KEY idx_user_uid (user_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. payment_tracking_settings (결제/시청 추적 설정)
CREATE TABLE IF NOT EXISTS payment_tracking_settings (
    setting_id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    setting_key     VARCHAR(50) NOT NULL UNIQUE,
    setting_value   TEXT NOT NULL,
    setting_type    VARCHAR(20) NOT NULL DEFAULT 'string',
    description     TEXT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. payment_video_tracking (결제별 시청 추적)
CREATE TABLE IF NOT EXISTS payment_video_tracking (
    tracking_id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_uid             BIGINT NOT NULL,
    payment_id           BIGINT NULL,
    merchant_uid         VARCHAR(255) NULL,
    product_name         VARCHAR(255) NULL,
    payment_date         DATETIME NULL,
    tracking_start_date  DATETIME NULL,
    tracking_end_date    DATETIME NULL,
    total_videos_watched INT UNSIGNED NOT NULL DEFAULT 0,
    is_active            TINYINT(1) NOT NULL DEFAULT 1,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_user_uid (user_uid),
    KEY idx_payment_id (payment_id),
    KEY idx_merchant_uid (merchant_uid),
    KEY idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. overdue_reminder_logs (미납 알림 발송 로그)
CREATE TABLE IF NOT EXISTS overdue_reminder_logs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    subscription_id BIGINT UNSIGNED NULL,
    user_uid        VARCHAR(50) NOT NULL,
    user_name       VARCHAR(100) NULL,
    email           VARCHAR(255) NOT NULL,
    amount          DECIMAL(10,0) NOT NULL,
    days_overdue    INT NOT NULL,
    urgency_level   VARCHAR(20) NULL,
    sent_by         VARCHAR(50) NULL,
    sent_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status          ENUM('sent','failed','pending') NOT NULL DEFAULT 'sent',
    response_message TEXT NULL,
    KEY idx_subscription_id (subscription_id),
    KEY idx_email (email),
    KEY idx_status (status),
    KEY idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. payments_options (결제/포트원 설정 키-값)
CREATE TABLE IF NOT EXISTS payments_options (
    meta    VARCHAR(100) NOT NULL,
    `key`   VARCHAR(100) NOT NULL,
    value   TEXT NOT NULL,
    PRIMARY KEY (meta, `key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

