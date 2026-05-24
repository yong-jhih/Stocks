CREATE DATABASE IF NOT EXISTS somethin_tools CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE somethin_tools;

-- 1. 每日收盤行情 (History)
CREATE TABLE IF NOT EXISTS stock_history (
    trade_date DATE NOT NULL COMMENT '日期',
    stock_id VARCHAR(10) NOT NULL COMMENT '代碼',
    stock_name VARCHAR(50) COMMENT '股名',
    open_price DECIMAL(10, 2) DEFAULT 0.00 COMMENT '開盤價',
    high_price DECIMAL(10, 2) DEFAULT 0.00 COMMENT '最高價',
    low_price DECIMAL(10, 2) DEFAULT 0.00 COMMENT '最低價',
    close_price DECIMAL(10, 2) DEFAULT 0.00 COMMENT '收盤價',
    trade_volume BIGINT DEFAULT 0 COMMENT '成交股數',
    PRIMARY KEY (trade_date, stock_id),
    INDEX idx_stock (stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. 三大法人買賣超 (Institutional Investors)
CREATE TABLE IF NOT EXISTS stock_insti (
    trade_date DATE NOT NULL COMMENT '日期',
    stock_id VARCHAR(10) NOT NULL COMMENT '代碼',
    foreign_buy_sell BIGINT DEFAULT 0 COMMENT '外資買賣超股數',
    trust_buy_sell BIGINT DEFAULT 0 COMMENT '投信買賣超股數',
    dealer_buy_sell BIGINT DEFAULT 0 COMMENT '自營商買賣超股數',
    total_buy_sell BIGINT DEFAULT 0 COMMENT '三大法人合計買賣超股數',
    PRIMARY KEY (trade_date, stock_id),
    INDEX idx_stock (stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. 融資融券 (Margin Trading)
CREATE TABLE IF NOT EXISTS stock_margin (
    trade_date DATE NOT NULL COMMENT '日期',
    stock_id VARCHAR(10) NOT NULL COMMENT '代碼',
    margin_balance BIGINT DEFAULT 0 COMMENT '融資今日餘額',
    margin_balance_diff BIGINT DEFAULT 0 COMMENT '融資餘額增減',
    short_balance BIGINT DEFAULT 0 COMMENT '融券今日餘額',
    short_balance_diff BIGINT DEFAULT 0 COMMENT '融券餘額增減',
    PRIMARY KEY (trade_date, stock_id),
    INDEX idx_stock (stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. 借券餘額 (SBL Total - TWT72U)
CREATE TABLE IF NOT EXISTS stock_sbl_total (
    trade_date DATE NOT NULL COMMENT '日期',
    stock_id VARCHAR(10) NOT NULL COMMENT '代碼',
    sbl_balance BIGINT DEFAULT 0 COMMENT '借券餘額',
    PRIMARY KEY (trade_date, stock_id),
    INDEX idx_stock (stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. 借券賣出額度管制 (SBL Sold - TWT93U)
CREATE TABLE IF NOT EXISTS stock_sbl_sold (
    trade_date DATE NOT NULL COMMENT '日期',
    stock_id VARCHAR(10) NOT NULL COMMENT '代碼',
    sbl_sold_balance BIGINT DEFAULT 0 COMMENT '借券賣出餘額',
    sbl_sold BIGINT DEFAULT 0 COMMENT '當日借券賣出',
    sbl_return BIGINT DEFAULT 0 COMMENT '當日還券',
    PRIMARY KEY (trade_date, stock_id),
    INDEX idx_stock (stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. 系統日誌 (System Logs)
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '記錄時間',
    log_type VARCHAR(50) NOT NULL COMMENT '日誌類型 (如: Fetch, DB_Insert, Error)',
    content TEXT COMMENT '詳細內容',
    result VARCHAR(20) NOT NULL COMMENT '結果 (Success, Fail, Warning)',
    INDEX idx_time (log_time),
    INDEX idx_type (log_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DELETE FROM system_logs WHERE log_time < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- 7. 00981A 成分股
CREATE TABLE IF NOT EXISTS 00981A_component (
    trade_date DATE NOT NULL COMMENT '日期',
    stock_id VARCHAR(10) NOT NULL COMMENT '代碼',
    stock_name VARCHAR(50) COMMENT '股名',
    amount BIGINT DEFAULT 0 COMMENT '股數',
    weight DECIMAL(10, 2) COMMENT '權重',
    PRIMARY KEY (trade_date, stock_id),
    INDEX idx_stock (stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. 股票基本資料 / 產業概念
CREATE TABLE IF NOT EXISTS stock_profile (
    stock_id VARCHAR(10) NOT NULL COMMENT '股票代號',
    stock_name VARCHAR(50) NOT NULL COMMENT '股票名稱',
    market VARCHAR(20) DEFAULT '' COMMENT '市場別(上市/上櫃/興櫃)',
    industry VARCHAR(100) DEFAULT '' COMMENT '產業分類',
    sub_industry VARCHAR(100) DEFAULT '' COMMENT '次產業',
    concepts TEXT COMMENT '概念題材(逗號分隔)',
    update_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP COMMENT '更新時間',
    PRIMARY KEY (stock_id),
    INDEX idx_industry (industry),
    INDEX idx_market (market)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;