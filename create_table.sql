CREATE DATABASE IF NOT EXISTS somethin_tools CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE somethin_tools;

-- 1. 每日收盤行情 (History)
CREATE TABLE IF NOT EXISTS stock_history (
    trade_date DATE,
    stock_id VARCHAR(10),
    stock_name VARCHAR(50),
    open_price DECIMAL(10,2),
    high_price DECIMAL(10,2),
    low_price DECIMAL(10,2),
    close_price DECIMAL(10,2),
    trade_volume BIGINT,
    PRIMARY KEY (trade_date, stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. 三大法人買賣超 (Institutional Investors)
CREATE TABLE IF NOT EXISTS stock_insti (
    trade_date DATE,
    stock_id VARCHAR(10),
    foreign_buy_sell BIGINT,
    trust_buy_sell BIGINT,
    dealer_buy_sell BIGINT,
    total_buy_sell BIGINT,
    PRIMARY KEY (trade_date, stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. 融資融券 (Margin Trading)
CREATE TABLE IF NOT EXISTS stock_margin (
    trade_date DATE,
    stock_id VARCHAR(10),
    margin_balance BIGINT, -- 融資餘額
    short_balance BIGINT,  -- 融券餘額
    PRIMARY KEY (trade_date, stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. 借券餘額 (SBL Total)
CREATE TABLE IF NOT EXISTS stock_sbl_total (
    trade_date DATE,
    stock_id VARCHAR(10),
    sbl_balance BIGINT,
    PRIMARY KEY (trade_date, stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. 借券賣出額度管制 (SBL Sold)
CREATE TABLE IF NOT EXISTS stock_sbl_sold (
    trade_date DATE,
    stock_id VARCHAR(10),
    sbl_sold_balance BIGINT,
    PRIMARY KEY (trade_date, stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;