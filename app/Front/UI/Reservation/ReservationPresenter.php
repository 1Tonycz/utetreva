<?php

namespace App\Front\UI\Reservation;

use App\Core\Repository\AccommodationRepository;
use App\Core\Repository\OpeningExpectionsRepository;
use App\Core\Repository\OpeningHoursRepository;
use App\Front\UI\BasePresenter;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Nette\Application\UI\Form;
use Contributte\Translation\Translator;
use Contributte\Translation\LocalesResolvers\Session as TranslatorSessionResolver;
use Nette\Utils\ArrayHash;
use App\Core\Mail\MailService;
use App\Core\Repository\TableRepository;

final class ReservationPresenter extends BasePresenter
{
    /** @persistent */
    public string $formType = 'reservation';

    public function __construct(
        OpeningHoursRepository $openingHoursRepository,
        OpeningExpectionsRepository $openingExpectionsRepository,
        protected Translator $translator,
        protected TranslatorSessionResolver $translatorSessionResolver,
        private AccommodationRepository $accommodationRepository,
        private TableRepository $tableRepository,
        private MailService $mailService

    ) {
        parent::__construct($openingHoursRepository, $openingExpectionsRepository, $translator, $translatorSessionResolver);
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
            ->addRule(Form::MIN, 'ui.reservation.room.personsRull', 1)
            ->setRequired('ui.reservation.room.personsRequired')
            ->setDefaultValue(1)
            ->setHtmlType('number');
        $form->addInteger('Child', 'ui.reservation.room.child')
            ->addRule(Form::MIN, 'ui.reservation.room.childRull', 0)
            ->setDefaultValue(0)
            ->setHtmlType('number');
        $form->addInteger('Baby', 'ui.reservation.room.baby')
            ->setDefaultValue(0)
            ->addRule(Form::MIN, 'ui.reservation.room.babyRull', 0)
            ->setHtmlType('number');
        $form->addCheckbox('Dog', 'ui.reservation.room.dog');
        $form->addInteger('Dog_count', 'ui.reservation.room.dogCount')
            ->addRule(Form::MIN, 'ui.reservation.room.dogCountRull', 0)
            ->setDefaultValue(0)
            ->setHtmlType('number');
        $form->addTextArea('Note', 'ui.reservation.room.message')
            ->setHtmlAttribute('placeholder', 'ui.reservation.room.messagePlaceholder');

        $form->addCheckbox('Gdpr', 'ui.reservation.room.gdpr')
            ->setRequired('ui.reservation.room.gdprRequired');
        $form->addCheckbox('Newsletter', 'ui.reservation.room.newsletter');
        $form->addSubmit('Submit', 'ui.reservation.room.button');
        $form->onSuccess[] = function (Form $form, ArrayHash $v): void {
            $locale = $this->translator->getLocale();

            if($v->Date_from > $v->Date_to) {
                $this->flashMessage($this->translator->translate('ui.reservation.flashmessage.type1'), 'error');
                return;
            }
            if($v->Dog == true && $v->Dog_count == 0) {
                $this->flashMessage($this->translator->translate('ui.reservation.flashmessage.type2'), 'error');
                return;
            }

            $test = $this->accommodationRepository->getAll()->insert([
                'First' => $v->First,
                'Second' => $v->Second,
                'Mail' => $v->Mail,
                'Tel' => $v->Tel,
                'Person' => $v->Person,
                'Child' => $v->Child,
                'Baby' => $v->Baby,
                'Date_from' => $v->Date_from->format('Y-m-d'),
                'Date_to' => $v->Date_to->format('Y-m-d'),
                'Dog' => $v->Dog,
                'Dog_count' => $v->Dog_count,
                'Note' => $v->Note,
                'Solved' => 0,
                'Old' => 0,
                'Gdpr' => new \DateTimeImmutable(),
                'Newsletter' => $v->Newsletter,
                'Locale' => $locale
                ]);

            if(!$test) {
                $this->flashMessage($this->translator->translate('ui.flashmessage.type4'), 'error');
                $this->redirect('Home:default');
            } else {

            $this->flashMessage($this->translator->translate('ui.flashmessage.type5'), 'success');
            $this->mailService->sendReminderMessage();
            $this->redirect('Home:default');}
        };
        return $form;
    }
    protected function createComponentReservationTableForm(): Form
    {
        $form = new Form;
        $form->setTranslator($this->translator);
        $form->addText('First', 'ui.reservation.room.first')
            ->setRequired('ui.reservation.room.firstRequired');
        $form->addText('Second', 'ui.reservation.room.second')
            ->setRequired('ui.reservation.room.secondRequired');
        $form->addText('Mail', 'ui.reservation.room.email')
            ->setRequired('ui.reservation.room.emailRequired');
        $form->addText('Tel', 'ui.reservation.room.phone')
            ->addRule(Form::PATTERN, 'ui.reservation.room.phoneRull', '\+?[0-9 ]{9,15}')
            ->setRequired('ui.reservation.room.phoneRequired');
        $form->addDate('Date', 'ui.reservation.table.date')
            ->setRequired('ui.reservation.table.dateRequired');
        $form->addTime('Time', 'ui.reservation.table.time')
            ->setRequired('ui.reservation.table.timeRequired');
        $form->addInteger('Person', 'ui.reservation.room.persons')
            ->setRequired('ui.reservation.room.personsRequired')
            ->setDefaultValue(1)
            ->setHtmlType('number');
        $form->addTextArea('Note', 'ui.reservation.room.message');
        $form->addCheckbox('Gdpr', 'ui.reservation.room.gdpr')
            ->setRequired('ui.reservation.room.gdprRequired');
        $form->addCheckbox('Newsletter', 'ui.reservation.room.newsletter');
        $form->addSubmit('Submit', 'ui.reservation.room.button');
        $form->onSuccess[] = function (Form $form, ArrayHash $v): void {
            $locale = $this->translator->getLocale();
            if($this->isToday($v->Date)){
                $this->flashMessage($this->translator->translate('ui.reservation.flashmessage.type3'), 'error');
                return;
            }
            $today = new \DateTimeImmutable('now');
            $today->format('Y-m-d');
            if($v->Date <= $today){
                $this->flashMessage($this->translator->translate('ui.reservation.flashmessage.type6'), 'error');
                return;
            }
            if($this->isOpen($v->Date,$v->Time)){
                $this->tableRepository->insert([
                    'First' => $v->First,
                    'Second' => $v->Second,
                    'Mail' => $v->Mail,
                    'Tel' => $v->Tel,
                    'Person' => $v->Person,
                    'Date' => $v->Date->format('Y-m-d'),
                    'Time' => $v->Time->format('H:i'),
                    'Note' => $v->Note,
                    'Solved' => 0,
                    'Gdpr' => new \DateTimeImmutable(),
                    'Newsletter' => $v->Newsletter,
                    'Locale' => $locale
                ]);
                $this->flashMessage($this->translator->translate('ui.reservation.flashmessage.type4'), 'success');
                $this->redirect('Home:default');
            } else {
                return;
            }
        };
        return $form;
    }

    function isToday(DateTimeInterface $d, ?DateTimeZone $tz = null): bool
    {
        $tz ??= new DateTimeZone('Europe/Prague');
        $today = new DateTimeImmutable('today', $tz);

        return $d->format('Y-m-d') === $today->format('Y-m-d');
    }

    function isOpen(DateTimeInterface $date, DateTimeInterface $time): bool
    {
        $tz = new DateTimeZone('Europe/Prague');

        $d = DateTimeImmutable::createFromInterface($date)->setTimezone($tz);

        $row = $this->openingHoursRepository->getAll()
            ->where('day_of_week', (int) $d->format('N'))
            ->fetch();

        if (!$row || $row->opens === null || $row->closes === null) {
            $this->flashMessage('Někde se stala chyba.', 'error');
            return false;
        }

        // základní „dnešek“ (bez času)
        $base = new DateTimeImmutable($d->format('Y-m-d'), $tz);

        // dnešní otevírací a zavírací čas
        $open  = $this->timeOnDate($base, $row->opens ?? $row->Open,  $tz);
        $close = $this->timeOnDate($base, $row->closes ?? $row->Close, $tz);

        // rezervace nejpozději hodinu před zavírací dobou
        $closeMinus1h = $close->modify('-1 hour');

        // čas z formuláře přenes na dnešní datum
        $t   = DateTimeImmutable::createFromInterface($time)->setTimezone($tz);
        $now = $base->setTime((int) $t->format('H'), (int) $t->format('i'), (int) $t->format('s'));

        if ($now >= $open && $now <= $closeMinus1h) {
            return true;
        }

        $this->flashMessage(
            $this->translator->translate('ui.reservation.flashmessage.type5') . ' '
            . $open->format('H:i') . $this->translator->translate('ui.reservation.flashmessage.type5plus') . $close->format('H:i') . '.',
            'error'
        );
        return false;
    }

    private function timeOnDate(DateTimeImmutable $base, mixed $val, DateTimeZone $tz): DateTimeImmutable
    {
        // 1) DB vrací TIME jako DateInterval (MySQL přes Nette často dělá)
        if ($val instanceof DateInterval) {
            return $base->setTime(0, 0, 0)->add($val); // přičteme HH:MM:SS k půlnoci
        }

        // 2) Už je to DateTimeInterface (např. hydrace na DateTime)
        if ($val instanceof DateTimeInterface) {
            return $base->setTime(
                (int) $val->format('H'),
                (int) $val->format('i'),
                (int) $val->format('s')
            );
        }

        // 3) Je to string "HH:MM" / "HH:MM:SS"
        if (is_string($val)) {
            return new DateTimeImmutable($base->format('Y-m-d') . ' ' . $val, $tz);
        }

        throw new \InvalidArgumentException('Nepodporovaný typ času: ' . (is_object($val) ? get_class($val) : gettype($val)));
    }

}