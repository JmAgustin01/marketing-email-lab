<?php
namespace Lab;

use PDO;

/** Reads and saves the latest outcome per campaign/customer pair. */
final class EmailHistory
{
    public function __construct(private PDO $db) {}

    /** True when the pair already has a terminal outcome (SENT or SKIPPED). */
    public function isComplete(string $campaignId, string $customerId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM sent_marketing_emails
             WHERE campaign_id = ? AND customer_id = ? AND status IN ('SENT','SKIPPED')"
        );
        $stmt->execute([$campaignId, $customerId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /** Upserts the latest outcome. Expected keys are listed below. */
    public function save(array $r): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO sent_marketing_emails
               (campaign_id, customer_id, request_id, promo_code, recipient_email, subject,
                status, provider, message_id, reason, processed_at, sent_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               request_id = VALUES(request_id), promo_code = VALUES(promo_code),
               recipient_email = VALUES(recipient_email), subject = VALUES(subject),
               status = VALUES(status), provider = VALUES(provider),
               message_id = VALUES(message_id), reason = VALUES(reason),
               processed_at = VALUES(processed_at), sent_at = VALUES(sent_at)"
        );
        $stmt->execute([
            $r['campaign_id'], $r['customer_id'], $r['request_id'], $r['promo_code'],
            $r['recipient_email'] ?? null, $r['subject'], $r['status'],
            $r['provider'] ?? null, $r['message_id'] ?? null, $r['reason'] ?? null,
            $r['processed_at'] ?? gmdate('Y-m-d H:i:s'), $r['sent_at'] ?? null,
        ]);
    }
}
