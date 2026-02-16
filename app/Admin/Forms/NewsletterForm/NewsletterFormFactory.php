<?php

namespace App\Admin\Forms\NewsletterForm;

use Nette\Application\UI\Form;

final class NewsletterFormFactory
{
    public function create(): Form
    {
        $form = new Form;

        $form->addText('Subject', 'Předmět:')
            ->setRequired('Zadejte předmět.')
            ->addRule($form::MAX_LENGTH, 'Max. 255 znaků.', 255);

        $form->addTextArea('Body', 'Obsah:')
            ->setHtmlAttribute('rows', '5');
        $form->addUpload('Attachment', 'Přílohy:')
            ->addRule($form::MAX_FILE_SIZE, 'Max. velikost jednoho souboru je 10 MB.', 10 * 1024 * 1024)
            ->addRule($form::Image, 'Příloha musí být obrázek (jpg, png, gif...).');

        $form->addSubmit('Submit', 'Odeslat')
            ->setHtmlAttribute('class', 'newsletter__button');

        return $form;
    }

}