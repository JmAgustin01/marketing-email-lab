<?php
namespace Lab;

use Aws\Sqs\SqsClient;
use RuntimeException;

final class Settings
{
    public static function required(string $name): string
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            throw new RuntimeException("Environment variable $name is not set.");
        }
        return $value;
    }

    public static function queue(): SqsClient
    {
        $config = [
            'version' => 'latest',
            'region'  => getenv('AWS_REGION') ?: (getenv('AWS_DEFAULT_REGION') ?: 'us-east-1'),
        ];
        if (getenv('AWS_PROFILE')) {
            $config['profile'] = getenv('AWS_PROFILE');
        }
        return new SqsClient($config);
    }

    /** SMTP settings passed to EmailSender (constructor injection). */
    public static function smtp(): array
    {
        return [
            'host'     => getenv('MAILTRAP_HOST') ?: 'sandbox.smtp.mailtrap.io',
            'port'     => (int)(getenv('MAILTRAP_PORT') ?: 2525),
            'username' => self::required('MAILTRAP_USERNAME'),
            'password' => self::required('MAILTRAP_PASSWORD'),
            'from'     => getenv('MAIL_FROM_ADDRESS') ?: 'marketing@example.com',
        ];
    }

    public static function delaySeconds(): int
    {
        return (int)(getenv('MAILTRAP_DELAY_SECONDS') ?: 11);
    }
}
