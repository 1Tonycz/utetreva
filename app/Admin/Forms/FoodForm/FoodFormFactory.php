<?php

namespace App\Admin\Forms\FoodForm;

use Nette\Application\UI\Form;
final class FoodFormFactory
{
    public function create(): Form
    {
        $form = new Form;

        $form->addText('Name_cs', 'Název jídla:')
            ->setRequired('Zadejte název jídla.');

        $form->addText('Name_de', 'Německy:')
            ->setRequired('Zadejte název jídla v němčině.');
        $form->addText('Name_en', 'Anglicky:')
            ->setRequired('Zadejte název jídla anglicky.');
        $form->addText('Name_ru', 'Rusky:')
            ->setRequired('Zadejte název jídla v ruštině.');

        $form->addTextArea('Description_cs', 'Popis jídla:');
        $form->addTextArea('Description_de', 'Německy:');
        $form->addTextArea('Description_en', 'Anglicky:');
        $form->addTextArea('Description_ru', 'Rusky:');

        $form->addInteger('Price', 'Cena (v Kč):')
            ->setRequired('Zadejte cenu jídla.')
            ->addRule($form::MIN, 'Cena musí být alespoň 1 Kč.', 1);

        $form->addSelect('Category', 'Kategorie:', [
            1 => 'Předkrmy',
            2 => 'Polévky',
            3 => 'Ryby',
            4 => 'Zvěřinové speciality',
            5 => 'Hlavní jídla',
            6 => 'Saláty',
            7 => 'Dezerty',
            8 => 'Přílohy',
            9 => 'Omáčky',
            10 => 'Nealkoholické nápoje',
            11 => 'Alkoholické nápoje',
            12 => 'Vinný list'
        ])->setPrompt('Zvolte kategorii')
          ->setRequired('Zvolte kategorii.');

        $form->addSubmit('Submit', 'Uložit');

        return $form;
    }

}