<?php

declare(strict_types=1);

namespace App\Admin\Forms\AccommodationForm;

use App\Core\Repository\ReservationroomRepository;
use App\Core\Repository\RoomRepository;
use Nette\Application\UI\Form;

final class AccommodationFormFactory
{
    public function __construct(
        private RoomRepository $roomRepository,
        private ReservationroomRepository $reservationroomRepository,
    ) {}

    /**
     * Vytvoří formulář pro rezervaci.
     *
     * @param \DateTimeInterface|null $from  volitelné – když zadáš, zakážeme přetížené pokoje
     * @param \DateTimeInterface|null $to    volitelné – když zadáš, zakážeme přetížené pokoje
     * @param int|null $excludeReservationId volitelné – při editaci, aby si rezervace nepřekážela sama sobě
     */
    public function create(
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null,
        ?int $excludeReservationId = null,
    ): Form {
        $form = new Form;

        // --- ZÁKLADNÍ ÚDAJE ---
        $form->addText('First', 'Jméno:')
            ->setRequired('Zadejte své jméno.');

        $form->addText('Second', 'Příjmení:')
            ->setRequired('Zadejte své příjmení.');

        $form->addEmail('Mail', 'E-mail:')
            ->setRequired('Zadejte e-mail.');

        $form->addText('Tel', 'Telefon:')
            ->addRule(
                $form::PATTERN,
                'Zadejte platné telefonní číslo.',
                '^\+?[0-9 ]{9,15}$'
            )
            ->setRequired('Zadejte telefon.');

        $form->addInteger('Person', 'Počet osob:')
            ->setRequired('Zadejte počet ubytovaných osob.')
            ->setDefaultValue(2)
            ->addRule($form::MIN, 'Počet osob musí být alespoň 1.', 1);

        $form->addText('Date_from', 'Datum příjezdu:')
            ->setHtmlType('date')
            ->setRequired('Zadejte datum příjezdu.')
            ->addRule($form::PATTERN, 'Zadejte správné datum (YYYY-MM-DD).', '^\d{4}-\d{2}-\d{2}$');

        $form->addText('Date_to', 'Datum odjezdu:')
            ->setHtmlType('date')
            ->setRequired('Zadejte datum odjezdu.')
            ->addRule($form::PATTERN, 'Zadejte správné datum (YYYY-MM-DD).', '^\d{4}-\d{2}-\d{2}$');

        $form->addCheckbox('Dog', 'Mazlíček');

        $form->addTextArea('Note', 'Poznámka:')
            ->setHtmlAttribute('rows', 5);

        // --- POKOJE (checkboxy) ---
        // Připravíme options: [id => "Název (cena / noc)"]
        $rooms = $this->roomRepository->getAll()->order('Name')->fetchAll();
        $options = [];
        foreach ($rooms as $r) {
            $options[(int) $r->ID] = sprintf('%s (%s Kč / noc)', $r->Name, number_format((float)$r->Price, 0, ',', ' '));
        }

        $roomField = $form->addCheckboxList('room_ids', 'Pokoje:', $options)
            ->setRequired('Vyberte alespoň jeden pokoj.');

        // Pokud máme interval, zakážeme nedostupné pokoje
        if ($from && $to) {
            $disabled = [];
            foreach ($rooms as $r) {
                $available = $this->reservationroomRepository->isRoomAvailableExclusive(
                    (int) $r->ID,
                    $from,
                    $to,
                    $excludeReservationId
                );
                if (!$available) {
                    $disabled[] = (int) $r->ID;
                }
            }
            if ($disabled) {
                $roomField->setDisabled($disabled);
            }
        }

        // --- SUBMIT ---
        $form->addSubmit('send', 'Vytvořit rezervaci');

        // (volitelné) mezivalidační pravidlo: Date_from <= Date_to
        $form->onValidate[] = function (Form $form): void {
            $v = $form->getValues('array');
            if (!empty($v['Date_from']) && !empty($v['Date_to'])) {
                try {
                    $from = new \DateTimeImmutable((string)$v['Date_from']);
                    $to   = new \DateTimeImmutable((string)$v['Date_to']);
                    if ($from > $to) {
                        $form['Date_to']->addError('Datum odjezdu musí být stejné nebo po datu příjezdu.');
                    }
                } catch (\Throwable) {
                    // form už má vlastní pattern validace; tady jen ochrana
                }
            }
        };

        return $form;
    }
}
