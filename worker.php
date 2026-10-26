<?php
require __DIR__ . '/config.php';
$db     = (new Lab\Database())->connect();
$logger = new Lab\ActivityLogger();

$processor = new Lab\MarketingProcessor(
    $db,
    new Lab\EmailHistory($db),
    new Lab\EmailSender(Lab\Settings::smtp()),
    $logger,
    Lab\Settings::delaySeconds()
);
$worker = new Lab\QueueWorker(
    Lab\Settings::queue(),
    Lab\Settings::required('SQS_QUEUE_URL'),
    $processor,
    $logger
);
$worker->run();
