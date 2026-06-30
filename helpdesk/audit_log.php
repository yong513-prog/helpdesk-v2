<?php

function audit_log($pdo, $action, $details)
{
    try
    {
        $user_id = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? 'System';

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs
            (
                user_id,
                username,
                action,
                details
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?
            )
        ");

        $stmt->execute([
            $user_id,
            $username,
            $action,
            $details
        ]);
    }
    catch(Exception $e)
    {
        // Keep the main system running even if audit log fails.
    }
}
