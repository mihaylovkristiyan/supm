<?php
function setSecurityHeaders() {
    // Set Content Security Policy (CSP) headers
    $csp = "default-src 'self'; " .
           "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://code.jquery.com https://cdn.jsdelivr.net https://cdn.datatables.net https://cdnjs.cloudflare.com; " .
           "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdn.datatables.net https://cdnjs.cloudflare.com; " .
           "img-src 'self' data: https:; " .
           "font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
           "connect-src 'self' https://cdn.datatables.net; " .
           "frame-src 'none'; " .
           "object-src 'none'; " .
           "base-uri 'self';";

    if (!headers_sent()) {
        header("Content-Security-Policy: " . $csp);
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

        // Set strict transport security if using HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
        }
    }
} 