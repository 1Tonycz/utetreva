<?php

namespace App\Core\Mail;

use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Latte\Engine;
use Nette\Bridges\ApplicationLatte\LatteFactory;

final class MailService
{
    private Engine $latte;
    public function __construct(
        private Mailer $mailer,
        LatteFactory $latteFactory
    ){
        $this->latte = $latteFactory->create();
    }

    public function sendReservationMessage(string $to, string $subject, array $params): void
    {
        $html = $this->latte->renderToString(__DIR__ . '/template/ConfirmReservation.latte', $params);
        $attachmentCZ = __DIR__ . '/template/test.pdf';
        $attachmentDE = __DIR__ . '/template/testDE.pdf';

        $mail = (new Message())
            ->setFrom('restauraceutetreva@seznam.cz')
            ->addTo($to)
            ->setSubject($subject)
            ->setHtmlBody($html);
        $mail->addAttachment($attachmentCZ);
        $mail->addAttachment($attachmentDE);
        $this->mailer->send($mail);
    }
    public function sendCalculationMessage(string $to, string $subject, array $params): void
    {
        $html = $this->latte->renderToString(__DIR__ . '/template/ConfirmCalculation.latte', $params);
        $attachmentCZ = __DIR__ . '/template/test.pdf';
        $attachmentDE = __DIR__ . '/template/testDE.pdf';

        $mail = (new Message())
            ->setFrom('restauraceutetreva@seznam.cz')
            ->addTo($to)
            ->setSubject($subject)
            ->setHtmlBody($html);
        $mail->addAttachment($attachmentCZ);
        $mail->addAttachment($attachmentDE);
        $this->mailer->send($mail);
    }

    public function sendGenericMessage(string $to, string $subject, string $html): void
    {
        $mail = (new Message())
            ->setFrom('restauraceutetreva@seznam.cz')
            ->addTo($to)
            ->setSubject($subject)
            ->setHtmlBody($html);
        $this->mailer->send($mail);
    }
}