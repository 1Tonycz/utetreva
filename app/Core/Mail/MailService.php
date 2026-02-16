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

    public function sendReservationMessage(string $to, string $subject, array $params, string $lng): void
    {
        $html = $this->latte->renderToString(__DIR__ . '/template/'.$lng.'/ConfirmReservation.latte', $params);
        $attachmentCZ = __DIR__ . '/template/Informace_CZ.pdf';
        $attachmentDE = __DIR__ . '/template/Informace_DE.pdf';
        $attachmentEN = __DIR__ . '/template/Informace_EN.pdf';

        $mail = (new Message())
            ->setFrom('info@utetreva.cz')
            ->addTo($to)
            ->setSubject($subject)
            ->setHtmlBody($html);
        $mail->addAttachment($attachmentCZ);
        $mail->addAttachment($attachmentDE);
        $mail->addAttachment($attachmentEN);
        $this->mailer->send($mail);
    }
    public function sendCalculationMessage(string $to, string $subject, array $params, string $lng): void
    {
        $html = $this->latte->renderToString(__DIR__ . '/template/'.$lng.'/ConfirmCalculation.latte', $params);
        $attachmentCZ = __DIR__ . '/template/Informace_CZ.pdf';
        $attachmentDE = __DIR__ . '/template/Informace_DE.pdf';
        $attachmentEN = __DIR__ . '/template/Informace_EN.pdf';

        $mail = (new Message())
            ->setFrom('info@utetreva.cz')
            ->addTo($to)
            ->setSubject($subject)
            ->setHtmlBody($html);
        $mail->addAttachment($attachmentCZ);
        $mail->addAttachment($attachmentDE);
        $mail->addAttachment($attachmentEN);
        $this->mailer->send($mail);
    }

    public function sendGenericMessage(string $to, string $subject, string $html): void
    {
        $mail = (new Message())
            ->setFrom('info@utetreva.cz')
            ->addTo($to)
            ->setSubject($subject)
            ->setHtmlBody($html);
        $this->mailer->send($mail);
    }

    public function sendReminderMessage(): void
    {
        $mail = (new Message())
            ->setFrom('info@utetreva.cz')
            ->addTo('restauraceutetreva@seznam.cz')
            ->setSubject('utetreva.cz - Nová rezervace')
            ->setHtmlBody('Žádost o novou rezervaci byla vytvořena. https://utetreva.cz/system/');
        $this->mailer->send($mail);
    }

    public function sendNewsletterMail2(string $to, string $subject, string $body): void
    {
        $mail = (new Message())
            ->setFrom('info@utetreva.cz')
            ->addTo($to)
            ->setSubject($subject)
            ->setHtmlBody($body);
        $this->mailer->send($mail);
    }

    public function sendNewsletterMail(string $to, string $subject, string $body, string $imagePath): void
    {
        $mail = (new Message())
            ->setFrom('info@utetreva.cz')
            ->addTo($to)
            ->setSubject($subject)
            ->setHtmlBody($body);
        $mail->addAttachment($imagePath);
        $this->mailer->send($mail);
    }

    public function sendVoucherRequest(string $to, array $params, string $lng): void
    {
        $html = $this->latte->renderToString(__DIR__ . '/template/'.$lng.'/VoucherRequest.latte', $params);

        $mail = (new Message())
            ->setFrom('info@utetreva.cz')
            ->addTo($to)
            ->setSubject('Restaurace u Tetřeva - Platba voucheru')
            ->setHtmlBody($html);
        $this->mailer->send($mail);
    }

    public function sendVoucherPaid($to, $params, $file, $lng): void
    {
        $html = $this->latte->renderToString(__DIR__ . '/template/'.$lng.'/VoucherPaid.latte', $params);

        $mail = (new Message())
            ->setFrom('info@utetreva.cz')
            ->addTo($to)
            ->setSubject('Restaurace u Tetřeva - Voucher')
            ->setHtmlBody($html);
        $mail->addAttachment(__DIR__ . '/Voucher/' . $file);
        $this->mailer->send($mail);
    }

    public function sendDepositPaid($to, $params, $lng): void
    {
        $html = $this->latte->renderToString(__DIR__ . '/template/'.$lng.'/DepositPaid.latte', $params);

        $mail = (new Message())
            ->setFrom('info@utetreva.cz')
            ->addTo($to)
            ->setSubject('Restaurace u Tetřeva - Potvrzení o zaplacení zálohy')
            ->setHtmlBody($html);
        $this->mailer->send($mail);
    }

    public function sendPaidInvoice($to, $params, $lng): void
    {
        $html = $this->latte->renderToString(__DIR__ . '/template/'.$lng.'/PaidInvoice.latte', $params);

        $mail = (new Message())
            ->setFrom('info@utetreva.cz')
            ->addTo($to)
            ->setSubject('Restaurace u Tetřeva - Potvrzení o zaplacení ubytování')
            ->setHtmlBody($html);
        $this->mailer->send($mail);
    }

    public function sendTableReservation($to, $params, $lng): void
    {
        $html = $this->latte->renderToString(__DIR__ . '/template/'.$lng.'/TableReservation.latte', $params);

        $mail = (new Message())
            ->setFrom('info@utetreva.cz')
            ->addTo($to)
            ->setSubject('Restaurace u Tetřeva - Potvrzení rezervace stolu')
            ->setHtmlBody($html);
        $this->mailer->send($mail);
    }
}