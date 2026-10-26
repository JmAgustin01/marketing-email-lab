<?php
namespace Lab;

use PDO;
use RuntimeException;
use Throwable;

final class CampaignPreparer
{
    private const RECIPIENTS = 50;

    public function __construct(private PDO $db) {}

    public function prepare(string $campaignId, string $promoCode): void
    {
        // C3: check existing rows for this campaign before selecting anyone.
        $count = $this->db->prepare('SELECT COUNT(*) FROM marketing_outbox WHERE campaign_id = ?');
        $count->execute([$campaignId]);
        $existing = (int)$count->fetchColumn();

        if ($existing === self::RECIPIENTS) {
            echo "Campaign $campaignId already has " . self::RECIPIENTS . " requests. Selection unchanged.\n";
            return;
        }
        if ($existing !== 0) {
            throw new RuntimeException(
                "Inconsistent state: campaign $campaignId has $existing outbox rows (expected 0 or " . self::RECIPIENTS . ')'
            );
        }

        // C2: 50 distinct opted-in customers. customer_id is unique, so ORDER BY RAND() LIMIT
        //     samples rows without replacement.
        $select = $this->db->query(
            'SELECT customer_id FROM customers WHERE marketing_opt_in = 1
             ORDER BY RAND() LIMIT ' . self::RECIPIENTS
        );
        $customerIds = $select->fetchAll(PDO::FETCH_COLUMN);

        if (count($customerIds) !== self::RECIPIENTS) {
            throw new RuntimeException('Fewer than ' . self::RECIPIENTS . ' eligible customers found.');
        }

        $insert = $this->db->prepare(
            'INSERT INTO marketing_outbox
               (request_id, campaign_id, customer_id, promo_code, subject, body, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $subject = 'Laboratory promotion';
        $body    = 'You have been selected to receive our promotional offer.';
        $now     = gmdate('Y-m-d H:i:s');

        $this->db->beginTransaction();
        try {
            foreach ($customerIds as $customerId) {
                $requestId = bin2hex(random_bytes(16)); // 32 hex characters
                // C1: seven values in the order of the placeholders.
                $insert->execute([$requestId, $campaignId, $customerId, $promoCode, $subject, $body, $now]);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        echo "Prepared " . count($customerIds) . " requests for $campaignId.\n";
    }
}
