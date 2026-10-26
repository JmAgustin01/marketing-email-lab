<?php
namespace Lab;

use PHPMailer\PHPMailer\PHPMailer;

final class EmailSender
{
    /** @param array{host:string,port:int,username:string,password:string,from:string} $smtp */
    public function __construct(private array $smtp) {}

    /**
     * @param array $customer row from customers (full_name, email, ...)
     * @param array $job      decoded SQS payload (promoCode, subject, body, ...)
     * @return string MIME Message-ID
     */
    public function send(array $customer, array $job): string
    {
        $mail = new PHPMailer(true); // true = throw exceptions; do NOT suppress them
        $mail->isSMTP();
        $mail->Host       = $this->smtp['host'];
        $mail->Port       = $this->smtp['port'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->smtp['username'];
        $mail->Password   = $this->smtp['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($this->smtp['from'], 'Marketing Lab');

        // E1: recipient
        $mail->addAddress($customer['email'], $customer['full_name']);

        // E2: subject and personalized plain-text body showing the persisted promo code
        $mail->Subject = $job['subject'];
        $mail->Body    = "Hello {$customer['full_name']},\n\n"
                       . $job['body'] . "\n\n"
                       . "Your promo code: {$job['promoCode']}\n";

        if ($mail->Subject === '' || $mail->Body === '' || $mail->getToAddresses() === []) {
            throw new \LogicException('Email is missing a recipient, subject or body.');
        }

        $mail->send();
        return $mail->getLastMessageID();
    }
}
