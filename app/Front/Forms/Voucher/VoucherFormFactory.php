<?php

namespace App\Front\Forms\Voucher;

use Nette\Application\UI\Form;

final class VoucherFormFactory
{

    public function create(): Form
    {
        $form = new Form;
        $form->addText('First', 'ui.reservation.room.first')
            ->setRequired('ui.reservation.room.firstRequired');

        $form->addText('Second', 'ui.reservation.room.second')
            ->setRequired('ui.reservation.room.secondRequired');

        $form->addText('Mail', 'ui.reservation.room.email')
            ->setRequired('ui.reservation.room.emailRequired')
            ->addRule($form::EMAIL, 'ui.reservation.room.emailRule')
            ->setHtmlType('email');

        $form->addInteger('Amount', 'ui.voucher.form.amount')
            ->setRequired('ui.voucher.form.amountRequired')
            ->setHtmlAttribute('min', 500)
            ->setHtmlAttribute('max', 50000)
            ->setHtmlAttribute('step', 100);

        $form->addCheckbox('Gdpr', 'ui.reservation.room.gdpr')
            ->setRequired('ui.reservation.room.gdprRequired');

        $form->addCheckbox('Newsletter', 'ui.reservation.room.newsletter');

        $form->addSubmit('Send', 'ui.voucher.form.button');

        return $form;
    }

}