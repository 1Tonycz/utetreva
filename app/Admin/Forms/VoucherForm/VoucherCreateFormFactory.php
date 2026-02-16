<?php

namespace App\Admin\Forms\VoucherForm;

use Nette\Application\UI\Form;

final class VoucherCreateFormFactory
{
    public function create(): Form
    {
        $form = new Form;

            $form->addInteger('Amount', 'Hodnota voucheru:')
                ->setRequired('Zadejte hodnotu voucheru.')
                ->setHtmlAttribute('min', '500')
                ->setHtmlAttribute('max', '50000')
                ->setHtmlAttribute('step', '100');

            $form->addInteger('Code', 'Kód voucheru:')
                ->setRequired('Zadejte kód voucheru.')
                ->setHtmlAttribute('min', '0000')
                ->setHtmlAttribute('max', '9999')
                ->setHtmlAttribute('step', '1');

            $form->addDate('Date', 'Platný od')
                ->setRequired('Zadejte datum.');

            $form->addTextArea('Note', 'Poznámka k voucheru:')
                ->setHtmlAttribute('rows', '5')
                ->setHtmlAttribute('placeholder', 'Zadejte poznámku k voucheru (volitelné).')
                ->setRequired(false);

            $form->addSubmit('Send', 'Vytvořit voucher');

        return $form;
    }
}