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
CREATE OR REPLACE VIEW items_view AS
SELECT 
    *,
    EXTRACT(DAY FROM (NOW() - COALESCE(last_sale_date, date_created))) AS days_without_sale
FROM items;
