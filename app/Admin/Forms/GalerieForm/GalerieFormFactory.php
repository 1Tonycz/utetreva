<?php

namespace App\Admin\Forms\GalerieForm;

use Nette\Application\UI\Form;

final class GalerieFormFactory
{
    public function create(): Form
    {
        $form = new Form;

        $form->addText('Title', 'Název obrázku:')
            ->setRequired('Zadejte název obrázku.')
            ->addRule($form::MAX_LENGTH, 'Max. 255 znaků.', 255);

        $form->addSelect('Category', 'Kategorie:', [
            'pension'     => 'Pension',
            'restaurace'  => 'Restaurace',
            'kladska'     => 'Kladská',
        ])
            ->setPrompt('Vyberte kategorii')
            ->setRequired('Vyberte kategorii.');


        $form->addUpload('Images', 'Obrázky:')
            ->setRequired('Nahrajte alespoň jeden obrázek.')
            ->addRule($form::MAX_FILE_SIZE, 'Max. velikost jednoho souboru je 5 MB.', 5 * 1024 * 1024)
            ->addRule($form::MIME_TYPE, 'Povoleny jsou pouze obrázky.', ['image/jpeg', 'image/png', 'image/gif']);

        $form->addProtection();
        $form->addSubmit('Submit', 'Uložit');

        return $form;
    }
}
