<?php

declare(strict_types=1);

namespace App\Front\Forms\Accommodation;

use Nette\Application\UI\Form;

final class AccommodationFormFactory
{
    public function create(): Form
    {
        $form = new Form;

        $form->addText('First', 'Jméno:')
            ->setRequired('Zadejte své jméno.');

        $form->addText('Second', 'Příjmení:')
            ->setRequired('Zadejte své příjmení.');

        $form->addEmail('Mail', 'Email:')
            ->setRequired('Zadejte svojí emailovou adressu');

        $form->addText('Tel', 'Telefon:')
            ->setRequired('Zadejte telefonní číslo.')
            ->addRule($form::PATTERN, 'Zadejte platné telefonní číslo.', '^\+?[0-9 ]{9,15}$');

        $form->addInteger('Person', 'Počet osob')
            ->setRequired('Zadejte počet ubytováných osob.');

        $form->addText('Date_from', 'Datum příjezdu:')
            ->setHtmlType('date')
            ->setRequired('Zadejte datum.')
            ->addRule($form::PATTERN, 'Zadejte správné datum (YYYY-MM-DD).', '^\d{4}-\d{2}-\d{2}$');

        $form->addText('Date_to', 'Datum odjezdu:')
            ->setHtmlType('date')
            ->setRequired('Zadejte datum.')
            ->addRule($form::PATTERN, 'Zadejte správné datum (YYYY-MM-DD).', '^\d{4}-\d{2}-\d{2}$');

        $form->addCheckbox('Dog', 'Mazliček');

        $form->addTextArea('Note', 'Popis:')
            ->setHtmlAttribute('rows', 5);

        $form->addSubmit('send', 'Odeslat žádost na rezervaci');

        $form->addCheckbox('Gdpr', 'Souhlasím se zpracováním svých osobních údajů uvedených v tomto formuláři za účelem vyřízení rezervace.
Více informací o zpracování osobních údajů najdete v Ochrana osobních údajů.')
            ->setRequired('Musíte souhlasit s GDPR.');

        return $form;
    }
}
