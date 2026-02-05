-- Create table items
CREATE TABLE IF NOT EXISTS items (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    ml_id VARCHAR(50) UNIQUE NOT NULL,
    account_id UUID, -- Can be linked to an auth.users table or similar if exists
    title TEXT,
    price NUMERIC(10, 2),
    status VARCHAR(50),
    permalink TEXT,
    thumbnail TEXT,
    
    -- Analisador Fields
    date_created TIMESTAMP WITH TIME ZONE,
    last_sale_date TIMESTAMP WITH TIME ZONE,
    sold_quantity INT DEFAULT 0,
    
    -- 360 Fields
    shipping_mode VARCHAR(50),
    logistic_type VARCHAR(50),
    free_shipping BOOLEAN DEFAULT FALSE,
    tags JSONB DEFAULT '[]'::JSONB,
    
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Index for faster queries
CREATE INDEX IF NOT EXISTS idx_items_account_id ON items(account_id);
CREATE INDEX IF NOT EXISTS idx_items_ml_id ON items(ml_id);


-- View to calculate days_without_sale dynamically
DROP VIEW IF EXISTS items_view;
CREATE OR REPLACE VIEW items_view AS
SELECT 
    *,
    EXTRACT(DAY FROM (NOW() - COALESCE(last_sale_date, date_created))) AS days_without_sale
FROM items;

-- Create table accounts (For OAuth)
CREATE TABLE IF NOT EXISTS accounts (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    ml_user_id VARCHAR(50) UNIQUE NOT NULL,
    nickname VARCHAR(100),
    access_token TEXT NOT NULL,
    refresh_token TEXT NOT NULL,
    expires_at TIMESTAMP WITH TIME ZONE, -- Made nullable for migration compatibility
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create table users
CREATE TABLE IF NOT EXISTS users (
    id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
    name VARCHAR(255),
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Create table user_accounts (Many-to-Many link)
CREATE TABLE IF NOT EXISTS user_accounts (
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    account_id UUID REFERENCES accounts(id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, account_id)
);
