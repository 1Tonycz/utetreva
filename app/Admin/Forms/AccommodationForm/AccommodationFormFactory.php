<?php

declare(strict_types=1);

namespace App\Admin\Forms\AccommodationForm;

use App\Core\Repository\ReservationroomRepository;
use App\Core\Repository\RoomRepository;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;

final class AccommodationFormFactory
{
    public function __construct(
        private RoomRepository $roomRepository,
        private ReservationroomRepository $reservationroomRepository,
    ) {}

    public function create(
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null,
        ?int $excludeReservationId = null,
    ): Form {
        $form = new Form;

        $form->addText('First', 'Jméno:')
            ->setRequired('Zadejte své jméno.');

        $form->addText('Second', 'Příjmení:')
            ->setRequired('Zadejte své příjmení.');

        $form->addEmail('Mail', 'E-mail:')
            ->addRule($form::EMAIL, 'Zadejte platnou e-mailovou adresu.');

        $form->addText('Tel', 'Telefon:')
            ->addRule(
                $form::PATTERN,
                'Zadejte platné telefonní číslo.',
                '^\+?[0-9 ]{9,15}$'
            );

        $form->addInteger('Person', 'Počet osob:')
            ->setRequired('Zadejte počet ubytovaných osob.')
            ->setDefaultValue(2)
            ->addRule($form::MIN, 'Počet osob musí být alespoň 1.', 1);

        $form->addInteger('Child', 'Počet dětí:')
            ->setDefaultValue(0)
            ->addRule($form::MIN, 'Počet dětí nemůže být záporný.', 0);

        $form->addInteger('Baby', 'Počet dětí do 3 let:')
            ->setDefaultValue(0)
            ->addRule($form::MIN, 'Počet miminek nemůže být záporný.', 0);

        $form->addText('Date_from', 'Datum příjezdu:')
            ->setHtmlType('date')
            ->setRequired('Zadejte datum příjezdu.')
            ->addRule($form::PATTERN, 'Zadejte správné datum (YYYY-MM-DD).', '^\d{4}-\d{2}-\d{2}$');

        $form->addText('Date_to', 'Datum odjezdu:')
            ->setHtmlType('date')
            ->setRequired('Zadejte datum odjezdu.')
            ->addRule($form::PATTERN, 'Zadejte správné datum (YYYY-MM-DD).', '^\d{4}-\d{2}-\d{2}$');

        $form->addCheckbox('Dog', 'Mazlíček');

        $form->addInteger('Dog_count', 'Počet mazlíčků:')
            ->setDefaultValue(0)
            ->addRule($form::MIN, 'Počet mazlíčků nemůže být záporný.', 0);

        $form->addTextArea('Note', 'Poznámka:')
            ->setHtmlAttribute('rows', 5);

        // --- POKOJE ---
        $rooms = $this->roomRepository->getAll()->order('Name')->fetchAll();

        // Checkboxy (pokoje)
        $options = [];
        foreach ($rooms as $r) {
            $options[(int) $r->ID] = sprintf('%s (%s Kč / noc)', $r->Name, number_format((float)$r->Price, 0, ',', ' '));
        }
        $roomField = $form->addCheckboxList('room_ids', 'Pokoje:', $options)
            ->setRequired('Vyberte alespoň jeden pokoj.');

        // Kontejner na speciální ceny vedle každé položky
        $prices = $form->addContainer('custom_prices');
        foreach ($rooms as $r) {
            $prices->addText((string) $r->ID, 'Speciální cena')
                ->setNullable() // prázdné = ignorovat
                ->setHtmlType('number')
                ->setHtmlAttribute('step', '0.01')
                ->setHtmlAttribute('min', '0')
                ->addCondition($form::FILLED)
                ->addRule($form::FLOAT, 'Cena musí být číslo.');
        }

        // Pokud máme interval, zakážeme nedostupné pokoje (+ jejich price input)
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
                foreach ($disabled as $rid) {
                    if (isset($prices[(string)$rid])) {
                        $prices[(string)$rid]->setDisabled();
                    }
                }
            }
        }

        // --- SUBMIT ---
        $form->addSubmit('send', 'Vytvořit rezervaci');

        $form->onValidate[] = function (Form $form, ArrayHash $v): void {
            if (!empty($v['Date_from']) && !empty($v['Date_to'])) {
                try {
                    $from = new \DateTimeImmutable((string)$v['Date_from']);
                    $to   = new \DateTimeImmutable((string)$v['Date_to']);
                    if ($from > $to) {
                        $form['Date_to']->addError('Datum odjezdu musí být stejné nebo po datu příjezdu.');
                    }
                } catch (\Throwable) {
                    // pattern validace to zachytí; zde jen ochrana
                }
            }
        };

        return $form;
    }
}
