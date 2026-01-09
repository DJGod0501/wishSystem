<?php
// audit_log_helper.php
// Minimal helper to insert stage/date audit logs

function audit_log_stage_change(PDO $pdo, int $form_id, int $changed_by_user_id,
                               ?string $from_stage, ?string $to_stage,
                               $from_date, $to_date,
                               ?string $note = null): void
{
    // normalize empty
    $from_stage = ($from_stage === '' ? null : $from_stage);
    $to_stage   = ($to_stage === '' ? null : $to_stage);
    $note       = ($note === '' ? null : $note);

    // Dates can be string or null; let PDO handle
    $stmt = $pdo->prepare("
        INSERT INTO interview_stage_logs
        (form_id, changed_by_user_id, from_stage, to_stage, from_interview_date, to_interview_date, note)
        VALUES
        (:form_id, :by, :from_stage, :to_stage, :from_date, :to_date, :note)
    ");
    $stmt->execute([
        ':form_id' => $form_id,
        ':by' => $changed_by_user_id,
        ':from_stage' => $from_stage,
        ':to_stage' => $to_stage,
        ':from_date' => $from_date,
        ':to_date' => $to_date,
        ':note' => $note,
    ]);
}
