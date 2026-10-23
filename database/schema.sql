-- MySQL 5.7+/8.x, MariaDB 10.3+. Import order: schema.sql, seed.sql, (optional) default_admin.sql.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS platforms (
    id                 SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug               VARCHAR(32)  NOT NULL COMMENT 'Stable identifier sent by the front-end, e.g. "whatsapp"',
    name               VARCHAR(64)  NOT NULL,
    share_url_template VARCHAR(512) NOT NULL COMMENT 'Placeholders: {url}, {title}, {description}',
    icon               VARCHAR(64)  NOT NULL DEFAULT 'bi-share-fill' COMMENT 'Bootstrap Icons class',
    color              CHAR(7)      NOT NULL DEFAULT '#6C757D' COMMENT 'Brand colour used for buttons and graphs',
    sort_order         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active          TINYINT(1)   NOT NULL DEFAULT 1,
    created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_platforms_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Unique on a SHA-256 hash: 2048-char URLs are too long for a unique index.
CREATE TABLE IF NOT EXISTS pages (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    url        VARCHAR(2048) NOT NULL,
    url_hash   CHAR(64)      NOT NULL,
    title      VARCHAR(255)  NULL,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pages_url_hash (url_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS share_clicks (
    id          BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    platform_id SMALLINT UNSIGNED NOT NULL,
    page_id     INT UNSIGNED      NOT NULL,
    ip_hash     CHAR(64)          NULL COMMENT 'HMAC-SHA256 of the visitor IP (raw IPs are never stored)',
    user_agent  VARCHAR(255)      NULL,
    created_at  DATETIME          NOT NULL,
    PRIMARY KEY (id),
    KEY idx_clicks_created (created_at),
    KEY idx_clicks_platform_created (platform_id, created_at),
    KEY idx_clicks_page_created (page_id, created_at),
    KEY idx_clicks_ip_created (ip_hash, created_at),
    CONSTRAINT fk_clicks_platform FOREIGN KEY (platform_id) REFERENCES platforms (id) ON DELETE RESTRICT,
    CONSTRAINT fk_clicks_page FOREIGN KEY (page_id) REFERENCES pages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username      VARCHAR(64)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    last_login_at DATETIME     NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_hash      CHAR(64)    NOT NULL,
    username     VARCHAR(64) NOT NULL,
    success      TINYINT(1)  NOT NULL,
    attempted_at DATETIME    NOT NULL,
    PRIMARY KEY (id),
    KEY idx_login_attempts_ip (ip_hash, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
