<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Spreadsheet Driver
    |--------------------------------------------------------------------------
    |
    | "mysql" menyimpan data ke tabel MySQL (tujuan utama aplikasi).
    | "google" menyimpan data ke Google Sheets melalui service account.
    | "local" menyimpan data ke file JSON lokal (sumber migrasi dan test).
    |
    */

    'driver' => env('SHEETS_DRIVER', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Google Sheets
    |--------------------------------------------------------------------------
    */

    'spreadsheet_id' => env('GOOGLE_SHEETS_ID'),

    'credentials_path' => env(
        'GOOGLE_SERVICE_ACCOUNT_JSON',
        storage_path('app/private/google/service-account.json'),
    ),

    /*
    |--------------------------------------------------------------------------
    | Local Driver
    |--------------------------------------------------------------------------
    */

    'local_path' => env('SHEETS_LOCAL_PATH', storage_path('app/private/demo-sheets.json')),

    /*
    |--------------------------------------------------------------------------
    | MySQL Driver
    |--------------------------------------------------------------------------
    |
    | Data spreadsheet disimpan sebagai tabel MySQL dengan prefix tertentu.
    | Prefix menjaga tabel aplikasi tetap terpisah dari tabel framework.
    |
    */

    'mysql' => [
        'connection' => env('SHEETS_MYSQL_CONNECTION'),
        'table_prefix' => env('SHEETS_MYSQL_PREFIX', 'sheet_'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Semua pembacaan sheet di-cache agar hemat kuota Google Sheets API.
    |
    */

    'cache_ttl' => (int) env('SHEETS_CACHE_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | Tab dan Header
    |--------------------------------------------------------------------------
    |
    | Setiap tab di spreadsheet memiliki header sesuai urutan di bawah ini.
    |
    */

    'tabs' => [

        'study_programs' => [
            'id', 'code', 'name', 'degree', 'active',
            'created_at', 'updated_at', 'deleted_at',
        ],

        'users' => [
            'id', 'name', 'email', 'npm', 'password_hash', 'role', 'class_name',
            'cohort', 'phone', 'active', 'created_at', 'updated_at', 'deleted_at',
            'prodi_id',
        ],

        'courses' => [
            'id', 'code', 'name', 'credits', 'lecturer', 'semester', 'class_name',
            'description', 'created_at', 'updated_at', 'deleted_at', 'prodi_ids', 'meeting_link',
        ],

        'schedules' => [
            'id', 'course_id', 'day', 'start_time', 'end_time', 'room', 'class_name',
            'created_at', 'updated_at', 'deleted_at', 'mode', 'platform', 'meeting_link',
        ],

        'groups' => [
            'id', 'course_id', 'name', 'leader_id', 'description',
            'created_at', 'updated_at', 'deleted_at',
        ],

        'group_members' => [
            'id', 'group_id', 'user_id', 'role', 'created_at', 'deleted_at',
        ],

        'tasks' => [
            'id', 'course_id', 'group_id', 'title', 'description', 'type', 'deadline',
            'created_by', 'created_at', 'updated_at', 'deleted_at',
            'group_ids', 'meeting_number', 'material_url', 'start_at', 'deadline_note',
            'collection_link', 'status',
        ],

        'submissions' => [
            'id', 'task_id', 'user_id', 'group_id', 'status', 'link', 'notes', 'grade',
            'feedback', 'submitted_at', 'created_at', 'updated_at', 'deleted_at',
        ],

        'announcements' => [
            'id', 'title', 'body', 'audience', 'published_at', 'created_by',
            'created_at', 'updated_at', 'deleted_at', 'prodi_ids', 'course_id',
        ],

        'settings' => [
            'key', 'value', 'updated_at',
        ],

        'notification_reads' => [
            'id', 'user_id', 'notification_key', 'read_at',
            'created_at', 'updated_at', 'deleted_at',
        ],

        'api_tokens' => [
            'id', 'user_id', 'name', 'token_hash', 'last_used_at', 'expires_at',
            'revoked_at', 'created_at', 'updated_at', 'deleted_at',
        ],

    ],

];
