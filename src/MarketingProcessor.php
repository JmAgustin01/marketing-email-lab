<?php
namespace Lab;

use InvalidArgumentException;
use PDO;
use Throwable;

final class MarketingProcessor
{
    public function __construct(
        private PDO $db,
        private EmailHistory $history,
        private EmailSender $sender,
        private ActivityLogger $logger,
        private int $delaySeconds
    ) {}

    /** Supplied: validation + FAILED-save/rethrow pattern. Preserve both. */
    public function process(array $job): void
    {
        $this->validate($job);
        try {
            $this->handle($job);
        } catch (Throwable $e) {
            try {
                $this->history->save([
                    'campaign_id'  => $job['campaignId'],
                    'customer_id'  => $job['customerId'],
                    'request_id'   => $job['requestId'],
                    'promo_code'   => $job['promoCode'],
                    'subject'      => $job['subject'],
                    'status'       => 'FAILED',
                    'reason'       => $e->getMessage(),
                    'processed_at' => gmdate('Y-m-d H:i:s'),
                ]);
            } catch (Throwable) {
                // Keep the original exception; the message will not be deleted.
            }
            throw $e; // the worker must NOT acknowledge
        }
    }

    private function handle(array $job): void
    {
        $ids = [
            'requestId'  => $job['requestId'],
            'campaignId' => $job['campaignId'],
            'customerId' => $job['customerId'],
        ];

        // M2: processing has started for a valid job
        $this->logger->record('processing_started', $ids);

        // P1: a terminal outcome already exists, so do not send again
        if ($this->history->isComplete($job['campaignId'], $job['customerId'])) {
            $this->logger->record('duplicate_skipped', $ids);
            return;
        }

        // P2: look up the local customer (prepared statement)
        $stmt = $this->db->prepare(
            'SELECT customer_id, full_name, email, marketing_opt_in FROM customers WHERE customer_id = ?'
        );
        $stmt->execute([$job['customerId']]);
        $customer = $stmt->fetch();

        // P3: missing customer or no current consent -> SKIPPED
        if (!$customer || (int)$customer['marketing_opt_in'] !== 1) {
            $reason = !$customer ? 'Customer not found' : 'Customer has not opted in';
            $this->history->save([
                'campaign_id'  => $job['campaignId'],
                'customer_id'  => $job['customerId'],
                'request_id'   => $job['requestId'],
                'promo_code'   => $job['promoCode'],
                'subject'      => $job['subject'],
                'status'       => 'SKIPPED',
                'reason'       => $reason,
                'processed_at' => gmdate('Y-m-d H:i:s'),
            ]);
            $this->logger->record('skipped', $ids + ['reason' => $reason]);
            return;
        }

        // P4: send through Mailtrap and keep the MIME Message-ID
        $messageId = $this->sender->send($customer, $job);

        // P5: save the SENT outcome (UTC), then log
        $now = gmdate('Y-m-d H:i:s');
        $this->history->save([
            'campaign_id'     => $job['campaignId'],
            'customer_id'     => $job['customerId'],
            'request_id'      => $job['requestId'],
            'promo_code'      => $job['promoCode'],
            'recipient_email' => $customer['email'],
            'subject'         => $job['subject'],
            'status'          => 'SENT',
            'provider'        => 'mailtrap',
            'message_id'      => $messageId,
            'processed_at'    => $now,
            'sent_at'         => $now,
        ]);
        $this->logger->record('sent', $ids);

        // Respect the Mailtrap rate limit (after the outcome is safely stored)
        sleep($this->delaySeconds);
    }

    private function validate(array $job): void
    {
        foreach (['requestId', 'campaignId', 'customerId', 'promoCode', 'subject', 'body'] as $key) {
            if (!isset($job[$key]) || !is_string($job[$key]) || trim($job[$key]) === '') {
                throw new InvalidArgumentException("Missing or invalid field: $key");
            }
        }
    }
}
