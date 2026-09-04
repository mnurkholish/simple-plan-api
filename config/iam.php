<?php

return [
    'enabled' => env('IAM_ENABLED', false),
    'app_key' => env('IAM_APP_KEY', 'client-app'),
    'base_url' => env('IAM_BASE_URL', 'http://localhost:8000'),
    'jwt_secret' => env('IAM_JWT_SECRET', 'change-me'),
    'jwt_algorithm' => env('IAM_JWT_ALGORITHM', 'HS256'),
    'jwt_leeway' => (int) env('IAM_JWT_LEEWAY', 0),
    'issuer' => env('IAM_ISSUER', env('IAM_BASE_URL', null)),
    'audience' => env('IAM_AUDIENCE', null),
    'identifier_field' => env('IAM_IDENTIFIER_FIELD', 'email'),
    'roles_field' => env('IAM_ROLES_FIELD', 'roles'),
    'unit_kerja_field' => env('IAM_UNIT_KERJA_FIELD', 'unit_kerja'),
    'sync_roles' => env('IAM_SYNC_ROLES', true),
    'sync_unit_kerja' => env('IAM_SYNC_UNIT_KERJA', true),
    'require_roles' => env('IAM_REQUIRE_ROLES', false),
    'allow_roleless_sso' => env('IAM_ALLOW_ROLELESS_SSO', true),
    'required_roles' => env('IAM_REQUIRED_ROLES')
        ? array_map('trim', explode(',', env('IAM_REQUIRED_ROLES')))
        : [],
    'user_fields' => [
        'iam_id' => 'sub',
        'name' => 'name',
        'email' => 'email',
        'nip' => 'nip',
    ],
    'exchange_ttl_seconds' => (int) env('IAM_EXCHANGE_TTL_SECONDS', 120),
    'logout_url' => env('IAM_LOGOUT_URL', null),
];
