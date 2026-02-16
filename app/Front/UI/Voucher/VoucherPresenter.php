<?php

namespace App\Front\UI\Voucher;

use App\Front\UI\BasePresenter;
use App\Core\Repository\OpeningExpectionsRepository;
use App\Core\Repository\OpeningHoursRepository;
use Contributte\Translation\Translator;
use Nette\Application\UI\Form;
use Contributte\Translation\LocalesResolvers\Session as TranslatorSessionResolver;
use App\Front\Forms\Voucher\VoucherFormFactory;
use App\Core\Repository\VoucherRepository;
use App\Core\Mail\MailService;

final class VoucherPresenter extends BasePresenter
{
    public function __construct(
        OpeningHoursRepository $openingHoursRepository,
        OpeningExpectionsRepository $openingExpectionsRepository,
        protected Translator $translator,
        protected TranslatorSessionResolver $translatorSessionResolver,
        protected VoucherFormFactory $voucherFormFactory,
        protected VoucherRepository $voucherRepository,
        protected MailService $mailService,
    )
    {
        parent::__construct($openingHoursRepository, $openingExpectionsRepository, $translator, $translatorSessionResolver);
    }

    public function renderDefault():void{

    }

    public function createComponentVoucherForm() : Form
    {
        $form = $this->voucherFormFactory->create();
        $form->setTranslator($this->translator);
        $form->onSuccess[] = function (Form $form, array $v): void {
            $Vs = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $this->voucherRepository->insert([
                'First' => $v['First'],
                'Second' => $v['Second'],
                'Mail' => $v['Mail'],
                'Amount' => $v['Amount'],
                'Solved' => 0,
                'Paid' => 0,
                'Vs' => $Vs,
                'Gdpr' => new \DateTimeImmutable(),
                'Newsletter' => $v['Newsletter'],
                'Locale' => $this->translator->getLocale(),
            ]);
            $to = $v['Mail'];
            $lng = $this->translator->getLocale();
            $params = [
                'First' => $v['First'],
                'Second' => $v['Second'],
                'Amount' => $v['Amount'],
                'Vs' => $Vs
            ];
            $this->mailService->sendVoucherRequest($to, $params, $lng);
            $this->flashMessage($this->translator->translate('ui.voucher.flashmessage.type1'), 'success');
            $this->redirect('Home:default');
        };
        return $form;

    }
}