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

-- 7. 篩選
CREATE TABLE IF NOT EXISTS daily_dashboard_results (
    trade_date DATE NOT NULL COMMENT '交易日期',
    stock_id VARCHAR(10) NOT NULL COMMENT '股票代碼',
    stock_name VARCHAR(50) COMMENT '股票名稱',
    concept VARCHAR(255) COMMENT '產業概念',
    close_price DECIMAL(10, 2) COMMENT '收盤價',
    vol_k INT COMMENT '成交量(張)',
    vol_ratio DECIMAL(10, 2) COMMENT '昨量比',
    rank10 VARCHAR(20) COMMENT '10日位階',
    amp10 VARCHAR(20) COMMENT '10日振幅',
    
    -- 均線與均量
    ma5 DECIMAL(10, 2),
    ma10 DECIMAL(10, 2),
    ma20 DECIMAL(10, 2),
    vma5 INT COMMENT '5日均量(張)',
    vma10 INT COMMENT '10日均量(張)',
    vma20 INT COMMENT '20日均量(張)',
    
    -- 乖離率
    bia5 VARCHAR(20) COMMENT '5日乖離率',
    bia10 VARCHAR(20) COMMENT '10日乖離率',
    bia20 VARCHAR(20) COMMENT '20日乖離率',
    
    -- 籌碼集中度
    con1 VARCHAR(20) COMMENT '1日集中度',
    con5 VARCHAR(20) COMMENT '5日集中度',
    con10 VARCHAR(20) COMMENT '10日集中度',
    con20 VARCHAR(20) COMMENT '20日集中度',
    
    -- 融資相關
    margin_balance_diff INT COMMENT '融資當日增減',
    margin_balance_diff_sum5 INT COMMENT '融資5日累計',
    margin_balance_diff_sum10 INT COMMENT '融資10日累計',
    margin_balance_diff_sum20 INT COMMENT '融資20日累計',
    margin_balance INT COMMENT '融資餘額',
    
    -- 外資與投信累計 (單位: 張)
    foreign_sum5 INT COMMENT '外資5日累計',
    foreign_sum10 INT COMMENT '外資10日累計',
    foreign_sum20 INT COMMENT '外資20日累計',
    trust_sum5 INT COMMENT '投信5日累計',
    trust_sum10 INT COMMENT '投信10日累計',
    trust_sum20 INT COMMENT '投信20日累計',
    
    -- 連買天數
    foreign_streak_days INT COMMENT '外資連買天數',
    trust_streak_days INT COMMENT '投信連買天數',
    
    -- 借券與空方力量
    squeeze DECIMAL(10, 2) COMMENT '券補力',
    bullet DECIMAL(10, 2) COMMENT '券砸力',
    net_sbl INT COMMENT '券淨賣還(張)',
    net_sbl_sum5 INT COMMENT '券淨賣還5日累計',
    net_sbl_sum10 INT COMMENT '券淨賣還10日累計',
    net_sbl_sum20 INT COMMENT '券淨賣還20日累計',
    sbl_total INT COMMENT '借券餘額(張)',
    sbl_sold_balance INT COMMENT '借券賣出餘額(張)',
    
    -- 系統保留欄位
    action_tip TEXT COMMENT '提示建議',
    tags TEXT COMMENT '標籤',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (trade_date, stock_id),
    INDEX idx_date (trade_date),
    INDEX idx_stock (stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. 00981A 成分股
CREATE TABLE IF NOT EXISTS 00981A_component (
    trade_date DATE NOT NULL COMMENT '日期',
    stock_id VARCHAR(10) NOT NULL COMMENT '代碼',
    stock_name VARCHAR(50) COMMENT '股名',
    amount BIGINT DEFAULT 0 COMMENT '股數',
    weight DECIMAL(10, 2) COMMENT '權重',
    PRIMARY KEY (trade_date, stock_id),
    INDEX idx_stock (stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. 0050 成分股
CREATE TABLE IF NOT EXISTS 0050_component (
    trade_date DATE NOT NULL COMMENT '日期',
    stock_id VARCHAR(10) NOT NULL COMMENT '代碼',
    stock_name VARCHAR(50) COMMENT '股名',
    amount BIGINT DEFAULT 0 COMMENT '股數',
    weight DECIMAL(10, 2) COMMENT '權重',
    PRIMARY KEY (trade_date, stock_id),
    INDEX idx_stock (stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;