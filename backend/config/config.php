<?php
// backend/config/config.php

return [
    // Database (Supabase)
    'SUPABASE_HOST' => 'aws-1-us-east-2.pooler.supabase.com',
    'SUPABASE_PORT' => '6543',
    'SUPABASE_DB_NAME' => 'postgres',
    'SUPABASE_USER' => 'postgres.hvumjbxcybdmudbrzroq',
    'SUPABASE_PASSWORD' => 'novomeli360',

    // Mercado Livre Integration
    // PREENCHA ESTES DADOS COM AS SUAS CREDENCIAIS REAIS
    'MELI_APP_ID' => '4769748080121330',
    'MELI_SECRET_KEY' => 'DDBig6LnVPtFzueAGYvsyirsReBWYmk1',
    'MELI_REDIRECT_URI' => 'https://d3ecom.com.br/meli360/backend/auth/callback.php',

    // Frontend URL (para redirecionamento após login)
    'FRONTEND_URL' => 'https://d3ecom.com.br/meli360'
];
