<?php
namespace Lab;

use Aws\Sqs\SqsClient;
use PDO;
use Throwable;

final class QueuePublisher
{
    public function __construct(
        private PDO $db,
        private SqsClient $sqs,
        private string $queueUrl,
        private ActivityLogger $logger
    ) {}

    public function publishPending(): void
    {
        $rows = $this->db->query(
            "SELECT request_id, campaign_id, customer_id, promo_code, subject, body
             FROM marketing_outbox WHERE status = 'PENDING' ORDER BY created_at, request_id"
        )->fetchAll();

        $update = $this->db->prepare(
            "UPDATE marketing_outbox
             SET status = 'QUEUED', queued_at = ?, sqs_message_id = ?
             WHERE request_id = ?"
        );

        $published = 0;
        foreach ($rows as $row) {
            $payload = [
                'requestId'  => $row['request_id'],
                'campaignId' => $row['campaign_id'],
                'customerId' => $row['customer_id'],
                'promoCode'  => $row['promo_code'],
                'subject'    => $row['subject'],
                'body'       => $row['body'],
            ];
            try {
                // Q1: publish to SQS first.
                $result = $this->sqs->sendMessage([
                    'QueueUrl'    => $this->queueUrl,
                    'MessageBody' => json_encode($payload, JSON_THROW_ON_ERROR),
                ]);
                $sqsMessageId = $result['MessageId'];

                // Q2: mark QUEUED only after SQS acknowledged the message.
                $update->execute([gmdate('Y-m-d H:i:s'), $sqsMessageId, $row['request_id']]);

                // M1: log after both the send and the local update succeeded.
                $this->logger->record('queued', [
                    'requestId'    => $row['request_id'],
                    'campaignId'   => $row['campaign_id'],
                    'customerId'   => $row['customer_id'],
                    'sqsMessageId' => $sqsMessageId,
                ]);
                $published++;
            } catch (Throwable $e) {
                // Row stays PENDING. Check whether SQS may already have accepted the message.
                $this->logger->record('enqueue_failed', [
                    'requestId'  => $row['request_id'],
                    'campaignId' => $row['campaign_id'],
                    'customerId' => $row['customer_id'],
                    'error'      => $e::class,
                    'detail'     => $e->getMessage(),
                ]);
                echo "Failed to publish {$row['request_id']}: {$e->getMessage()}\n";
            }
        }
        echo "Published $published of " . count($rows) . " pending requests.\n";
    }
}
