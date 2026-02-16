<?php

namespace App\Admin\Forms\VoucherForm;

use Nette\Application\UI\Form;

final class VariablSymbolFormFactory
{
    public function create(): Form
    {
        $form = new Form;

            $form->addInteger('Vs', 'Variabilní symbol:')
            ->setRequired('Zadejte variabilní symbol.')
            ->addRule($form::RANGE, 'Variabilní symbol musí být mezi %d a %d.', [000000, 999999])
            ->setHtmlAttribute('min', 000000)
            ->setHtmlAttribute('max', 999999)
            ->setHtmlAttribute('step', 1);

            $form->addSubmit('Send', 'Označit jako zaplacený');

         return $form;
    }

}