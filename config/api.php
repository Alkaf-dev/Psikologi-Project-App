<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Token Lifetime
    |--------------------------------------------------------------------------
    |
    | Token API disimpan sebagai hash pada sheet api_tokens. Nilai ini adalah
    | masa berlaku default dalam hari. Klien dapat meminta masa berlaku yang
    | lebih pendek saat login (1-365 hari).
    |
    */

    'token_ttl_days' => (int) env('API_TOKEN_TTL_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    */

    'rate_limit_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 60),

    'login_rate_limit_per_minute' => (int) env('API_LOGIN_RATE_LIMIT_PER_MINUTE', 10),

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */

    'default_per_page' => (int) env('API_DEFAULT_PER_PAGE', 25),

    'max_per_page' => (int) env('API_MAX_PER_PAGE', 500),

];
