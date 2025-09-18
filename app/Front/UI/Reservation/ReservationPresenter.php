<?php

namespace App\Front\UI\Reservation;

use App\Core\Repository\AccommodationRepository;
use App\Core\Repository\OpeningExpectionsRepository;
use App\Core\Repository\OpeningHoursRepository;
use App\Front\UI\BasePresenter;
use Nette\Application\UI\Form;
use Contributte\Translation\Translator;
use Nette\Http\Session;
use Nette\Utils\ArrayHash;

final class ReservationPresenter extends BasePresenter
{
    /** @persistent */
    public string $formType = 'reservation';

    public function __construct(
        OpeningHoursRepository $openingHoursRepository,
        OpeningExpectionsRepository $openingExpectionsRepository,
        protected Translator $translator,
        protected Session $session,
        private AccommodationRepository $accommodationRepository

    ) {
        parent::__construct($openingHoursRepository, $openingExpectionsRepository, $translator, $session);
    }

    public function renderDefault(): void
    {
        $this->template->formType = $this->formType;
        $this->template->formTemplate = 'forms/' . $this->formType . '.latte';
    }

    public function handleChangeForm(string $type): void
    {
        $this->formType = $type;
        $this->template->formTemplate = 'forms/' . $type . '.latte';

        if ($this->isAjax()) {
            $this->redrawControl('formBox');
            $this->redrawControl('formTabs');
        } else {
            $this->redirect('this');
        }
    }

    protected function createComponentReservationForm(): Form
    {
        $form = new Form;
        $form->setTranslator($this->translator);
        $form->addText('First', 'ui.reservation.room.first')
            ->setRequired('ui.reservation.room.firstRequired');
        $form->addText('Second', 'ui.reservation.room.second')
            ->setRequired('ui.reservation.room.secondRequired');
        $form->addText('Mail', 'ui.reservation.room.email')
            ->setRequired('ui.reservation.room.emailRequired')
            ->addRule(Form::EMAIL, 'ui.reservation.room.emailRule');
        $form->addText('Tel', 'ui.reservation.room.phone')
            ->addRule(Form::PATTERN, 'ui.reservation.room.phoneRull', '\+?[0-9 ]{9,15}')
            ->setRequired('ui.reservation.room.phoneRequired');
        $form->addDate('Date_from', 'ui.reservation.room.from')
            ->setRequired('ui.reservation.fromRequired');
        $form->addDate('Date_to', 'ui.reservation.room.to')
            ->setRequired('ui.reservation.toRequired');
        $form->addInteger('Person', 'ui.reservation.room.persons')
            ->setRequired('ui.reservation.room.personsRequired')
            ->setDefaultValue(1)
            ->setHtmlType('number');
        $form->addCheckbox('Dog', 'ui.reservation.room.dog');
        $form->addTextArea('Note', 'ui.reservation.room.message');
        $form->addCheckbox('Gdpr', 'ui.reservation.room.gdpr')
            ->setRequired('ui.reservation.room.gdprRequired');
        $form->addCheckbox('Newsletter', 'ui.reservation.room.newsletter');
        $form->addSubmit('Submit', 'ui.reservation.room.button');
        $form->onSuccess[] = function (Form $form, ArrayHash $v): void {
            $locale = $this->translator->getLocale();

            $test = $this->accommodationRepository->getAll()->insert([
                'First' => $v->First,
                'Second' => $v->Second,
                'Mail' => $v->Mail,
                'Tel' => $v->Tel,
                'Person' => $v->Person,
                'Date_from' => $v->Date_from->format('Y-m-d'),
                'Date_to' => $v->Date_to->format('Y-m-d'),
                'Dog' => $v->Dog,
                'Note' => $v->Note,
                'Solved' => 0,
                'Old' => 0,
                'Gdpr' => new \DateTimeImmutable(),
                'Newsletter' => $v->Newsletter,
                'Locale' => $locale
                ]);

            if(!$test) {
                $this->flashMessage('Nepodařilo se odeslat rezervaci, kontaktujte nás.', 'error');
                $this->redirect('this');
            } else {

            $this->flashMessage('Děkujeme za odeslání rezervace. Brzy Vás budeme kontaktovat prostřednictvím emailu.', 'success');
            $this->redirect('this');}
        };
        return $form;
    }
    protected function createComponentReservationTableForm(): Form
    {
        $form = new Form;
        $form->addText('First', 'Jméno:')
            ->setRequired('Zadejte své jméno.');
        $form->addText('Second', 'Příjmení:')
            ->setRequired('Zadejte své příjmení.');
        $form->addText('Mail', 'E-mail:')
            ->setRequired('Zadejte svůj e-mail.');
        $form->addText('Tel', 'Telefon:')
            ->addRule(Form::PATTERN, 'Zadejte platné telefonní číslo.', '\+?[0-9 ]{9,15}')
            ->setRequired('Zadejte své telefonní číslo.');
        $form->addDate('Date', 'Datum rezervace:')
            ->setRequired('Zadejte datum rezervace.');
        $form->addTime('Time', 'Čas rezervace:')
            ->setRequired('Zadejte čas rezervace.');
        $form->addInteger('Person', 'Počet osob:')
            ->setRequired('Zadejte počet osob.')
            ->setDefaultValue(1)
            ->setHtmlType('number');
        $form->addTextArea('Note', 'Poznámka:');
        $form->addCheckbox('Gdpr', 'Souhlasím se zpracováním osobních údajů.')
            ->setRequired('Musíte souhlasit se zpracováním osobních údajů.');
        $form->addCheckbox('Newsletter', 'Chci odebírat novinky e-mailem.');
        $form->addSubmit('Submit', 'Odeslat rezervaci');
        $form->onSuccess[] = function (Form $form): void {
            $this->flashMessage('Děkujeme za odeslání rezervace. Brzy Vás budeme kontaktovat prostřednictvím emailu.', 'success');
            $this->redirect('this');
        };
        return $form;
    }

}