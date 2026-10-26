<?php
namespace Lab;

/** Appends one JSON event per line to logs/events.jsonl (no names, emails, or content). */
final class ActivityLogger
{
    private const ALLOWED = [
        'requestId', 'campaignId', 'customerId', 'messageId', 'sqsMessageId',
        'phase', 'error', 'detail', 'reason', 'count',
    ];

    public function __construct(private string $file = __DIR__ . '/../logs/events.jsonl') {}

    public function record(string $event, array $context = []): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $entry = ['timestamp' => gmdate('Y-m-d\TH:i:s\Z'), 'event' => $event]
               + array_intersect_key($context, array_flip(self::ALLOWED));
        file_put_contents(
            $this->file,
            json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
