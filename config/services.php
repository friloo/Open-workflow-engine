<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'microsoft-azure' => [
        'client_id' => env('M365_CLIENT_ID'),
        'client_secret' => env('M365_CLIENT_SECRET'),
        'redirect' => env('M365_REDIRECT_URI'),
        'tenant' => env('M365_TENANT_ID', 'common'),
        'proxy' => env('M365_PROXY'),
    ],

    'oidc' => [
        'enabled' => env('OIDC_ENABLED', false),
        'issuer' => env('OIDC_ISSUER'),
        'client_id' => env('OIDC_CLIENT_ID'),
        'client_secret' => env('OIDC_CLIENT_SECRET'),
        'redirect' => env('OIDC_REDIRECT_URI'),
        'scopes' => env('OIDC_SCOPES', 'openid email profile'),
        'button_label' => env('OIDC_BUTTON_LABEL', 'Mit Single Sign-On anmelden'),
        'auto_provision' => env('OIDC_AUTO_PROVISION', true),
        'default_role' => env('OIDC_DEFAULT_ROLE', 'employee'),
    ],

    'google' => [
        'enabled' => env('GOOGLE_ENABLED', false),
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'hosted_domain' => env('GOOGLE_HOSTED_DOMAIN'),
        'auto_provision' => env('GOOGLE_AUTO_PROVISION', true),
        'default_role' => env('GOOGLE_DEFAULT_ROLE', 'employee'),
    ],

    'ldap' => [
        'enabled' => env('LDAP_ENABLED', false),
        'host' => env('LDAP_HOST'),
        'port' => env('LDAP_PORT', 389),
        'use_tls' => env('LDAP_USE_TLS', false),
        'base_dn' => env('LDAP_BASE_DN'),
        'bind_dn' => env('LDAP_BIND_DN'),
        'bind_password' => env('LDAP_BIND_PASSWORD'),
        // {username} wird durch die Eingabe ersetzt (RFC4515-escaped).
        // AD-Default; fuer OpenLDAP/389DS typisch: (&(objectClass=inetOrgPerson)(uid={username}))
        'user_filter' => env('LDAP_USER_FILTER', '(&(objectClass=user)(sAMAccountName={username}))'),
        'email_attribute' => env('LDAP_EMAIL_ATTRIBUTE', 'mail'),
        'name_attribute' => env('LDAP_NAME_ATTRIBUTE', 'displayName'),
        'auto_provision' => env('LDAP_AUTO_PROVISION', true),
        'default_role' => env('LDAP_DEFAULT_ROLE', 'employee'),
    ],

    'saml' => [
        'enabled' => env('SAML_ENABLED', false),
        'idp_entity_id' => env('SAML_IDP_ENTITY_ID'),
        'idp_sso_url' => env('SAML_IDP_SSO_URL'),
        'idp_x509_cert' => env('SAML_IDP_X509_CERT'),
        'sp_entity_id' => env('SAML_SP_ENTITY_ID'),
        'email_attribute' => env('SAML_EMAIL_ATTRIBUTE', 'email'),
        'name_attribute' => env('SAML_NAME_ATTRIBUTE', 'displayName'),
        'button_label' => env('SAML_BUTTON_LABEL', 'Mit SAML anmelden'),
        'auto_provision' => env('SAML_AUTO_PROVISION', true),
        'default_role' => env('SAML_DEFAULT_ROLE', 'employee'),
        'want_assertions_signed' => env('SAML_WANT_ASSERTIONS_SIGNED', false),
        'want_messages_signed' => env('SAML_WANT_MESSAGES_SIGNED', false),
    ],

];
