<?php

namespace App\Admin\Forms\GroupForm;

use Nette\Application\UI\Form;
final class GroupFormFactory
{
    public function create(): Form
    {
        $form = new Form;

        $form->addText('First', 'Název skupiny/jméno')
            ->setRequired('Zadejte název skupiny nebo jméno');

        $form->addText('Second', 'Příjmení (nepovinné)');

        $form->addText('Mail', 'E-mail (nepovinné)')
            ->addRule($form::EMAIL, 'Zadejte platný e-mail.');

        $form->addInteger('Tel', 'Telefonní číslo (nepovinné)')
            ->addRule(Form::PATTERN, 'Telefonní číslo neodpovída paternu', '\+?[0-9 ]{9,15}');

        $form->addDate('Date', 'Datum rezervace')
            ->setRequired('Zadejte datum rezervace');

        $form->addTime('Time', 'Čas rezervace')
            ->setRequired('Zadejte čas rezervace');

        $form->addInteger('Person', 'Počet osob')
            ->setRequired('Zadejte počet osob ve skupině');

        $form->addTextArea('Note', 'Poznámka(nepovinné)');

        $form->addSubmit('Submit', 'Uložit');

        return $form;
    }

}