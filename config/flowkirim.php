<?php

return [
    'api_token' => env('FLOWKIRIM_API_TOKEN'),
    // Marketing docs mention api.flowkirim.com, but that host currently has no DNS.
    // Working gateway: https://scan.flowkirim.com
    'base_url' => env('FLOWKIRIM_BASE_URL', 'https://scan.flowkirim.com'),
    'send_path' => env('FLOWKIRIM_SEND_PATH', '/api/whatsapp/messages/text'),
    'timeout' => (int) env('FLOWKIRIM_TIMEOUT', 20),
    // FlowKirim Baileys API expects snake_case session_id (not camelCase sessionId).
    'device_field' => env('FLOWKIRIM_DEVICE_FIELD', 'session_id'),
    // Append WhatsApp JID suffix when sending (docs show 628…@s.whatsapp.net).
    'append_jid_suffix' => filter_var(env('FLOWKIRIM_APPEND_JID_SUFFIX', true), FILTER_VALIDATE_BOOL),
    'link_preview' => filter_var(env('FLOWKIRIM_LINK_PREVIEW', true), FILTER_VALIDATE_BOOL),
    // Prepend Unicode LRM so mixed Arabic+Latin templates stay left-aligned in WhatsApp.
    'force_ltr' => filter_var(env('FLOWKIRIM_FORCE_LTR', true), FILTER_VALIDATE_BOOL),
];
