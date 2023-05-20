<?php declare(strict_types=1);

namespace App;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class EmailSender
{
    private $subject;
    private $bodyHtml;

    public function __construct(string $subject, string $bodyHtml)
    {
        $this->subject = $subject;
        $this->bodyHtml = $bodyHtml;
    }

    public function sendEmails(string $dsn, array $recipients, string $fromEmail, string $fromName, ?string $glockappsId = null, ?string $subjectPrefix = null): void
    {
        $transport = Transport::fromDsn($dsn);
        $mailer = new Mailer($transport);
        $subjectPrefix = (null !== $subjectPrefix ? '['.$subjectPrefix.'] ' : '');
        $bodyPrefix = (null !== $glockappsId) ? "<br>id:{$glockappsId}" : '';
        foreach ($recipients as $recipient) {
            $email = (new Email())
                ->from(new Address($fromEmail, $fromName))
                ->to($recipient)
                ->subject($subjectPrefix . $this->subject)
                ->html($this->bodyHtml . $bodyPrefix);
            if (null !== $glockappsId) {
                $email->getHeaders()->addTextHeader('X-API-Campaign-id', $glockappsId);
            }
            $mailer->send($email);
        }
    }

    public function validateDsn(string $dsn): void
    {
        $transport = Transport::fromDsn($dsn);
        $method = new \ReflectionMethod($transport, 'start');
        $method->setAccessible(true);
        $method->invoke($transport);
    }
}
