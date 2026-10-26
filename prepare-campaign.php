<?php
require __DIR__ . '/config.php';
if ($argc < 3) {
    fwrite(STDERR, "Usage: php prepare-campaign.php CAMPAIGN_ID PROMO_CODE\n");
    exit(1);
}
$preparer = new Lab\CampaignPreparer((new Lab\Database())->connect());
$preparer->prepare($argv[1], $argv[2]);
