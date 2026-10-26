<?php
namespace Lab;

use Aws\Sqs\SqsClient;
use Throwable;
use UnexpectedValueException;

final class QueueWorker
{
    private string $phase = 'receive';

    public function __construct(
        private SqsClient $sqs,
        private string $queueUrl,
        private MarketingProcessor $processor,
        private ActivityLogger $logger
    ) {}

    /** Supplied receive loop. Runs until you press Ctrl+C. */
    public function run(): void
    {
        echo "Worker started. Press Ctrl+C to stop.\n";
        $this->logger->record('monitoring_ready', ['phase' => 'worker']);
        while (true) {
            $result = $this->sqs->receiveMessage([
                'QueueUrl'            => $this->queueUrl,
                'MaxNumberOfMessages' => 1,
                'WaitTimeSeconds'     => 20,
            ]);
            foreach ($result->get('Messages') ?? [] as $message) {
                $this->handle($message);
            }
        }
    }

    private function handle(array $message): void
    {
        $sqsId = $message['MessageId'] ?? null;
        $this->phase = 'receive';
        $this->logger->record('received', ['messageId' => $sqsId]);

        try {
            $this->phase = 'decode';
            $job = json_decode($message['Body'] ?? '', true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($job)) {
                throw new UnexpectedValueException('Payload must be a JSON object.');
            }
            $this->phase = 'process';

            // W1: process the job (throws on failure, so nothing is deleted)
            $this->processor->process($job);

            // M3: switch phase so a failure here is reported as ack_failed
            $this->phase = 'delete';

            // W2: acknowledge using the current ReceiptHandle (not the MessageId)
            $this->sqs->deleteMessage([
                'QueueUrl'      => $this->queueUrl,
                'ReceiptHandle' => $message['ReceiptHandle'],
            ]);

            // M3: log 'deleted' only after DeleteMessage succeeded
            $this->logger->record('deleted', [
                'messageId'  => $sqsId,
                'requestId'  => $job['requestId'],
                'campaignId' => $job['campaignId'],
                'customerId' => $job['customerId'],
            ]);
        } catch (Throwable $e) {
            $event = $this->phase === 'delete' ? 'ack_failed' : 'processing_failed';
            $this->logger->record($event, [
                'messageId'  => $sqsId,
                'requestId'  => $job['requestId']  ?? null,
                'campaignId' => $job['campaignId'] ?? null,
                'customerId' => $job['customerId'] ?? null,
                'phase'      => $this->phase,
                'error'      => $e::class,
                'detail'     => $e->getMessage(),
            ]);
            echo "[$event] phase={$this->phase}: {$e->getMessage()}\n";
            // Do NOT delete the message; SQS will redeliver it after the visibility timeout.
        }
    }
}
