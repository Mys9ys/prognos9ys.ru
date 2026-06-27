<?php

/**
 * Снимает блокировку file-based PHP session для параллельных ajax-запросов mob_app / Bitrix Engine.
 * Авторизация mob_app идёт через userToken; для login/register сессию не отпускаем.
 */
function prognos9ys_release_php_session_if_needed(): void
{
    if (!prognos9ys_should_release_php_session()) {
        return;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
}

function prognos9ys_should_release_php_session(): bool
{
    if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
        return false;
    }

    $haystack = strtolower(
        (string)($_SERVER['REQUEST_URI'] ?? '')
        . ' '
        . (string)($_SERVER['SCRIPT_NAME'] ?? '')
    );

    $keepLocked = [
        '/mob_app/ajax/auth/',
        '/mob_app/ajax/register/',
        '/mob_app/ajax/recover_pass/',
        '/mob_app/ajax/send_code/',
    ];

    foreach ($keepLocked as $fragment) {
        if (strpos($haystack, $fragment) !== false) {
            return false;
        }
    }

    $releaseFor = [
        '/mob_app/ajax/',
        '/bitrix/services/main/ajax.php',
    ];

    foreach ($releaseFor as $fragment) {
        if (strpos($haystack, $fragment) !== false) {
            return true;
        }
    }

    return false;
}
