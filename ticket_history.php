<?php

function ticket_history(
    PDO $pdo,
    int $ticketId,
    string $action,
    ?int $userId = null
)
{
    if($ticketId <= 0 || trim($action) == '')
    {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO ticket_history
        (
            ticket_id,
            action,
            created_by
        )
        VALUES
        (
            ?,
            ?,
            ?
        )
    ");

    $stmt->execute([
        $ticketId,
        $action,
        $userId
    ]);
}
