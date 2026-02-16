<?php

namespace App\Admin\UI\Newsletter;

use App\Admin\UI\BasePresenter;
use App\Core\Repository\AccommodationRepository;
use Nette\Application\UI\Form;
use App\Admin\Forms\NewsletterForm\NewsletterFormFactory;
use App\Core\Mail\MailService;
use Nette\Http\FileUpload;
use App\Core\Repository\VoucherRepository;

final class NewsletterPresenter extends BasePresenter
{
    public function __construct(
        public NewsletterFormFactory $newsletterFormFactory,
        public AccommodationRepository $accommodationRepository,
        public MailService $mailService,
        public VoucherRepository $voucherRepository,
    ){

    }

    public function renderDefault(): void
    {
        if (!$this->getUser()->isAllowed('newsletter', 'default')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

    }

    public function createComponentNewsletterForm(): Form
    {
        $form = $this->newsletterFormFactory->create();

        $form->onSuccess[] = function (Form $form, array $v): void {
            $subject = (string) ($v['Subject'] ?? '');
            $body    = nl2br($v['Body']);
            /** @var Nette\Http\FileUpload|null $upload */
            $attachment = $v['Attachment'] ?? null;
            $upload = null;
            $uploadDir = __DIR__ . '/../../../../www/temp/';
            $files = glob($uploadDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            $emails2 = $this->voucherRepository->getAll()->where('Newsletter', 1);
            $emails = $this->accommodationRepository->getAll()->where('Newsletter', 1);
            if($attachment instanceof FileUpload){
                if ($attachment->isOk() && $attachment->isImage()) {
                    $upload = $attachment;
                    $imagePath = $upload->move($uploadDir.$upload->getSanitizedName());
                    $imageUrl = '1tonytesterweb.online/temp/'.$upload->getSanitizedName();
                    $body .= '<br><img src="'.$imageUrl.'" alt="Příloha emailu">';
                    foreach ($emails as $email) {
                        if(!$email->unsubscribe_token){
                            $token = bin2hex(random_bytes(16));
                            $this->accommodationRepository->update($email->ID, ['unsubscribe_token' => $token]);
                        }
                        $to = $email->Mail;
                        $unsubscribeLink = $this->link('//:Front:Home:unsubscribe', ['token' => $email->unsubscribe_token]);
                        $body .= '<br><br>Pro odhlášení z newsletteru klikněte <a href="'.$unsubscribeLink.'">zde</a>.';
                        $this->mailService->sendNewsletterMail($to, $subject, $body, $imagePath);
                    }
                    foreach ($emails2 as $email) {
                        if(!$email->unsubscribe_token){
                            $token = bin2hex(random_bytes(16));
                            $this->voucherRepository->update($email->ID, ['unsubscribe_token' => $token]);
                        }
                        $to = $email->Mail;
                        $unsubscribeLink = $this->link('//:Front:Home:unsubscribe', ['token' => $email->unsubscribe_token]);
                        $body .= '<br><br>Pro odhlášení z newsletteru klikněte <a href="'.$unsubscribeLink.'">zde</a>.';
                        $this->mailService->sendNewsletterMail($to, $subject, $body, $imagePath);
                    }
                } else {
                    foreach ($emails as $email) {
                        if(!$email->unsubscribe_token){
                            $token = bin2hex(random_bytes(16));
                            $this->accommodationRepository->update($email->ID, ['unsubscribe_token' => $token]);
                        }
                        $to = $email->Mail;
                        $this->mailService->sendNewsletterMail2($to, $subject, $body);
                        $unsubscribeLink = $this->link('//:Front:Home:unsubscribe', ['token' => $email->unsubscribe_token]);
                        $body .= '<br><br>Pro odhlášení z newsletteru klikněte <a href="'.$unsubscribeLink.'">zde</a>.';
                        $this->mailService->sendNewsletterMail2($to, $subject, $body);
                    }
                    foreach ($emails2 as $email) {
                        if(!$email->unsubscribe_token){
                            $token = bin2hex(random_bytes(16));
                            $this->voucherRepository->update($email->ID, ['unsubscribe_token' => $token]);
                        }
                        $to = $email->Mail;
                        $this->mailService->sendNewsletterMail2($to, $subject, $body);
                        $unsubscribeLink = $this->link('//:Front:Home:unsubscribe', ['token' => $email->unsubscribe_token]);
                        $body .= '<br><br>Pro odhlášení z newsletteru klikněte <a href="'.$unsubscribeLink.'">zde</a>.';
                        $this->mailService->sendNewsletterMail2($to, $subject, $body);
                    }
                }
            }
            $this->flashMessage('Newsletter byl odeslán.', 'success');
            $this->redirect('this');
        };

        return $form;
    }



}