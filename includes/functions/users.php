<?php
// ===== FONCTIONS POUR MOT DE PASSE OUBLIÉ =====
// Ces fonctions utilisent directement la base de données (PDO)
// et ne sont pas déclarées ailleurs.

function find_user_by_email(string $email): ?array
{
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function find_user_by_id(int $id): ?array
{
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM utilisateur WHERE id_user = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function create_password_reset_token(int $userId, int $expiryHours = 1): string
{
    $pdo = get_db_connection();
    // Supprimer les anciens tokens
    $stmt = $pdo->prepare("DELETE FROM password_reset WHERE user_id = ?");
    $stmt->execute([$userId]);

    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryHours} hours"));

    $stmt = $pdo->prepare("INSERT INTO password_reset (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $token, $expiresAt]);

    return $token;
}

function validate_reset_token(string $token): ?array
{
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM password_reset WHERE token = ? AND used = 0 AND expires_at > NOW()");
    $stmt->execute([$token]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
    return $reset ?: null;
}

function mark_reset_token_as_used(string $token): void
{
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("UPDATE password_reset SET used = 1 WHERE token = ?");
    $stmt->execute([$token]);
}

function update_user_password(int $userId, string $newPassword): void
{
    $pdo = get_db_connection();
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE utilisateur SET password_hash = ? WHERE id_user = ?");
    $stmt->execute([$hash, $userId]);
}
?>