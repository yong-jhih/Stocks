CREATE DATABASE IF NOT EXISTS somethin_tools CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE somethin_tools;

-- 1. 每日收盤行情 (History)
CREATE TABLE IF NOT EXISTS stock_history (
    trade_date DATE NOT NULL COMMENT '日期',
    stock_id VARCHAR(6) NOT NULL COMMENT '代碼',
    stock_name VARCHAR(20) COMMENT '股名',
    open_price DECIMAL(10, 2) DEFAULT 0.00 COMMENT '開盤價',
    high_price DECIMAL(10, 2) DEFAULT 0.00 COMMENT '最高價',
    low_price DECIMAL(10, 2) DEFAULT 0.00 COMMENT '最低價',
    close_price DECIMAL(10, 2) DEFAULT 0.00 COMMENT '收盤價',
    trade_volume BIGINT DEFAULT 0 COMMENT '成交股數',
    trade_value BIGINT DEFAULT 0 COMMENT '成交金額',
    PRIMARY KEY (trade_date, stock_id),
    INDEX idx_stock (stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. 三大法人買賣超 (Institutional Investors)
CREATE TABLE IF NOT EXISTS stock_insti (
    trade_date DATE NOT NULL COMMENT '日期',
    stock_id VARCHAR(6) NOT NULL COMMENT '代碼',
    foreign_buy_sell INT DEFAULT 0 COMMENT '外資買賣超股數',
    trust_buy_sell INT DEFAULT 0 COMMENT '投信買賣超股數',
    dealer_buy_sell INT DEFAULT 0 COMMENT '自營商買賣超股數',
    total_buy_sell INT DEFAULT 0 COMMENT '三大法人合計買賣超股數',
    PRIMARY KEY (trade_date, stock_id),
    INDEX idx_stock (stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. 融資融券 (Margin Trading)
CREATE TABLE IF NOT EXISTS stock_margin (
    trade_date DATE NOT NULL COMMENT '日期',
    stock_id VARCHAR(6) NOT NULL COMMENT '代碼',
    margin_balance INT DEFAULT 0 COMMENT '融資今日餘額',
    margin_balance_diff INT DEFAULT 0 COMMENT '融資餘額增減',
    short_balance INT DEFAULT 0 COMMENT '融券今日餘額',
    short_balance_diff INT DEFAULT 0 COMMENT '融券餘額增減',
    PRIMARY KEY (trade_date, stock_id),
    INDEX idx_stock (stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. 借券餘額 (SBL Total - TWT72U)
CREATE TABLE IF NOT EXISTS stock_sbl_total (
    trade_date DATE NOT NULL COMMENT '日期',
    stock_id VARCHAR(6) NOT NULL COMMENT '代碼',
    sbl_balance INT DEFAULT 0 COMMENT '借券餘額',
    PRIMARY KEY (trade_date, stock_id),
    INDEX idx_stock (stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. 借券賣出額度管制 (SBL Sold - TWT93U)
CREATE TABLE IF NOT EXISTS stock_sbl_sold (
    trade_date DATE NOT NULL COMMENT '日期',
    stock_id VARCHAR(6) NOT NULL COMMENT '代碼',
    sbl_sold_balance INT DEFAULT 0 COMMENT '借券賣出餘額',
    sbl_sold INT DEFAULT 0 COMMENT '當日借券賣出',
    sbl_return INT DEFAULT 0 COMMENT '當日還券',
    PRIMARY KEY (trade_date, stock_id),
    INDEX idx_stock (stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. 系統日誌 (System Logs)
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '記錄時間',
    log_type VARCHAR(50) NOT NULL COMMENT '日誌類型 (如: Fetch, DB_Insert, Error)',
    content TEXT COMMENT '詳細內容',
    result VARCHAR(10) NOT NULL COMMENT '結果 (Success, Fail, Warning)',
    INDEX idx_time (log_time),
    INDEX idx_type (log_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. etf 成分股
CREATE TABLE IF NOT EXISTS etf_component (
    trade_date DATE NOT NULL COMMENT '日期',
    etf_id VARCHAR(6) NOT NULL COMMENT 'ETF代碼',
    etf_name VARCHAR(20) COMMENT 'ETF名',
    stock_id VARCHAR(6) NOT NULL COMMENT '代碼',
    stock_name VARCHAR(20) COMMENT '股名',
    amount BIGINT DEFAULT 0 COMMENT '股數',
    weight DECIMAL(10, 2) COMMENT '權重',
    PRIMARY KEY (trade_date, etf_id, stock_id),
    INDEX idx_stock (stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. 股票基本資料
CREATE TABLE IF NOT EXISTS stock_profile (
    stock_id VARCHAR(6) NOT NULL COMMENT '股票代號',
    stock_name VARCHAR(20) NOT NULL COMMENT '股票名稱',
    industry VARCHAR(20) DEFAULT '' COMMENT '主產業',
    update_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
    PRIMARY KEY (stock_id),
    INDEX idx_industry (industry)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. 次產業
CREATE TABLE IF NOT EXISTS stock_sub_industry (
    stock_id VARCHAR(6) NOT NULL COMMENT '股票代號',
    sub_industry VARCHAR(20) NOT NULL COMMENT '次產業',
    create_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT '建立時間',
    PRIMARY KEY (
        stock_id,
        sub_industry),
    INDEX idx_sub_industry (sub_industry),
    CONSTRAINT fk_stock_sub_industry_stock
        FOREIGN KEY (stock_id)
        REFERENCES stock_profile(stock_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. 概念
CREATE TABLE IF NOT EXISTS stock_concept (
    stock_id VARCHAR(6) NOT NULL COMMENT '股票代號',
    concept VARCHAR(100) NOT NULL COMMENT '概念題材',
    create_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT '建立時間',
    PRIMARY KEY (
        stock_id,
        concept),
    INDEX idx_concept (concept),
    CONSTRAINT fk_stock_concept_stock
        FOREIGN KEY (stock_id)
        REFERENCES stock_profile(stock_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;