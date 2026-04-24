<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

function save_password_reset_token(string $email, string $token, string $expiresAt): void
{
    if ($email === '' || $token === '' || $expiresAt === '') {
        throw new Exception('Email, token y fecha de vencimiento no pueden estar vacios');
    }

    $stmtToken = db()->prepare(
        "INSERT INTO password_reset_token (email, token, expires_at, used)
         VALUES (?, ?, ?, FALSE)
         ON DUPLICATE KEY UPDATE
           token = VALUES(token),
           expires_at = VALUES(expires_at),
           used = FALSE,
           created_at = CURRENT_TIMESTAMP"
    );

    if (!$stmtToken) {
        throw new Exception('Error preparando consulta de token: ' . db()->error);
    }

    $stmtToken->bind_param('sss', $email, $token, $expiresAt);

    if (!$stmtToken->execute()) {
        $error = $stmtToken->error;
        $stmtToken->close();
        throw new Exception('Error guardando token de reset: ' . $error);
    }

    $stmtToken->close();
}

function delete_expired_password_reset_tokens(): int
{
    $stmt = db()->prepare("DELETE FROM password_reset_token WHERE expires_at < NOW()");
    if (!$stmt) {
        throw new Exception('Error preparando limpieza de tokens expirados: ' . db()->error);
    }

    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Error eliminando tokens expirados: ' . $error);
    }

    $deletedRows = $stmt->affected_rows;
    $stmt->close();

    return $deletedRows;
}

function find_password_reset_token(string $token): ?array
{
    if ($token === '') {
        return null;
    }

    $stmt = db()->prepare("SELECT email, expires_at, used FROM password_reset_token WHERE token = ? LIMIT 1");
    if (!$stmt) {
        throw new Exception('Error preparando consulta de token: ' . db()->error);
    }

    $stmt->bind_param('s', $token);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Error ejecutando consulta de token: ' . $error);
    }

    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    return $row;
}

function get_password_reset_token_status(array $tokenData): string
{
    if ((int)($tokenData['used'] ?? 0) === 1) {
        return 'used';
    }

    $expiresAtRaw = (string)($tokenData['expires_at'] ?? '');
    if ($expiresAtRaw === '') {
        return 'invalid';
    }

    $expiresAt = new DateTime($expiresAtRaw);
    $now = new DateTime();

    if ($now > $expiresAt) {
        return 'expired';
    }

    return 'valid';
}

function mark_password_reset_token_as_used(string $token): void
{
    if ($token === '') {
        throw new Exception('Token vacio al marcar como usado');
    }

    $stmt = db()->prepare("UPDATE password_reset_token SET used = TRUE WHERE token = ?");
    if (!$stmt) {
        throw new Exception('Error preparando actualizacion de token: ' . db()->error);
    }

    $stmt->bind_param('s', $token);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception('Error marcando token como usado: ' . $error);
    }

    $stmt->close();
}
