# sam-hybrid-promo-lab

PHP OOP + local MySQL + Faker + Amazon SQS + Mailtrap Email Sandbox.
The database stays on your computer. Only queue messages go to AWS SQS.

## Install
```
composer install
composer dump-autoload
```
Create the `marketing_lab` database (utf8mb4), complete `schema.sql`, then `php init-db.php`.

## Configure (PowerShell, every terminal)
Copy `set-env.ps1`, fill in placeholders, then run `. .\set-env.ps1` (set-env.ps1 is git-ignored). Or set variables manually:
```
$env:DB_HOST="127.0.0.1"; $env:DB_PORT="3306"; $env:DB_NAME="marketing_lab"
$env:DB_USER="YOUR_LOCAL_MYSQL_USER"; $env:DB_PASSWORD="YOUR_LOCAL_MYSQL_PASSWORD"
$env:AWS_PROFILE="marketing-lab"; $env:AWS_REGION="us-east-1"
$env:SQS_QUEUE_URL="YOUR_MAIN_QUEUE_URL"
$env:MAILTRAP_HOST="sandbox.smtp.mailtrap.io"; $env:MAILTRAP_PORT="2525"
$env:MAILTRAP_USERNAME="YOUR_SANDBOX_USERNAME"; $env:MAILTRAP_PASSWORD="YOUR_SANDBOX_PASSWORD"
$env:MAIL_FROM_ADDRESS="marketing@example.com"; $env:MAILTRAP_DELAY_SECONDS="11"
```

## Run
```
php seed-customers.php
php prepare-campaign.php CAMP-001 PROMO15
php enqueue.php
php worker.php
php inspect.php
```
`iam-policy.json` is a template: replace REGION, ACCOUNT_ID and STUDENTID.
