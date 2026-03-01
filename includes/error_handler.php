<?php
// includes/error_handler.php
// Task T38 — Global PHP error/exception handler
// Include at the very top of index.php or bootstrap.php

require_once __DIR__ . '/Logger.php';

// ── PHP Error handler ──────────────────────────────────────────────────────────
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {

    // Respect the @ operator (error suppression)
    if (!(error_reporting() & $errno)) {
        return false;
    }

    $level = match (true) {
        in_array($errno, [E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED]) => Logger::DEBUG,
        in_array($errno, [E_WARNING, E_USER_WARNING, E_STRICT])                       => Logger::WARNING,
        default                                                                         => Logger::ERROR,
    };

    $category = match (true) {
        str_contains($errstr, 'mysql') || str_contains($errstr, 'PDO')
            || str_contains($errfile, 'models') => 'database',
        str_contains($errfile, 'auth')           => 'auth',
        default                                  => Logger::CAT_ERROR,
    };

    Logger::log($level, $category, $errstr, [
        'file'  => $errfile,
        'line'  => $errline,
        'errno' => $errno,
    ]);

    // Let PHP's internal handler continue for warnings/notices in dev
    return false;
});

// ── Exception handler ──────────────────────────────────────────────────────────
set_exception_handler(function (Throwable $e): void {

    Logger::critical(Logger::CAT_EXCEPTION, $e->getMessage(), [
        'class' => get_class($e),
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    // Avoid leaking stack traces in production
    if ((defined('APP_DEBUG') && APP_DEBUG) || (($_SERVER['REMOTE_ADDR'] ?? '') === '127.0.0.1')) {
        echo '<pre style="background:#fdd;padding:16px;border-radius:6px;font-family:monospace;font-size:13px">';
        echo '<strong>' . get_class($e) . '</strong>: ' . htmlspecialchars($e->getMessage()) . "\n";
        echo 'File: ' . $e->getFile() . ':' . $e->getLine() . "\n\n";
        echo htmlspecialchars($e->getTraceAsString());
        echo '</pre>';
    } else {
        http_response_code(500);
        $page500 = __DIR__ . '/../error_pages/500.html';
        if (file_exists($page500)) {
            include $page500;
        } else {
            echo '<!DOCTYPE html><html><head><title>500 — Server Error</title></head><body>'
               . '<h1>Something went wrong</h1><p>Our team has been notified. Please try again shortly.</p>'
               . '</body></html>';
        }
    }

    exit(1);
});

// ── Shutdown function (fatal errors) ──────────────────────────────────────────
register_shutdown_function(function (): void {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        Logger::critical('fatal_error', $error['message'], [
            'file' => $error['file'],
            'line' => $error['line'],
            'type' => $error['type'],
        ]);
    }
});
