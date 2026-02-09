-- Migration: Adiciona colunas de redefinição de senha à tabela saas_users
-- Executar no banco de dados PostgreSQL:
--   psql -U seu_usuario -d seu_banco -f add_reset_token_columns.sql

ALTER TABLE saas_users
    ADD COLUMN IF NOT EXISTS reset_token VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS reset_expires_at TIMESTAMP WITH TIME ZONE DEFAULT NULL;
