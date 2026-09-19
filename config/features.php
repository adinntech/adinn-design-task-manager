<?php

return [
    // Development-only Excel import on the Admin "Manage Email" page. Turn off
    // (set MANAGE_EMAIL_EXCEL_IMPORT=false in .env) once the one-time data
    // import is done — the upload section and its route both respect this,
    // so hiding it needs no code changes.
    'manage_email_excel_import' => (bool) env('MANAGE_EMAIL_EXCEL_IMPORT', true),
];
