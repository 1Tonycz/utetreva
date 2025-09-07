<?php

declare(strict_types=1);

namespace App\Admin\UI\Home;

use App\Admin\UI\BasePresenter;
use App\Core\Repository\CleanRepository;
use App\Core\Repository\ReservationCommentsRepository;
use Nette;
use App\Core\Repository\ReservationroomRepository;
use App\Core\Repository\AccommodationRepository;
use App\Core\Repository\RoomRepository;
use App\Admin\Forms\AccommodationForm\AccommodationFormFactory;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;
use Spatie\Browsershot\Browsershot;
use Nette\Application\Responses\FileResponse;
use Nette\Application\Responses\CallbackResponse;
use Nette\Bridges\ApplicationLatte\TemplateFactory;
use Nette\Utils\Strings;
use NumberToWords\NumberToWords;


final class HomePresenter extends BasePresenter
{
    public function __construct(
        private ReservationroomRepository $reservationRoomRepository,
        private AccommodationRepository $accommodationRepository,
        private RoomRepository $roomRepository,
        private ReservationCommentsRepository $reservationCommentsRepository,
        private CleanRepository $cleanRepository,
        private AccommodationFormFactory $accommodationFormFactory,
        private TemplateFactory $templateFactory
    ) {}

    // přijmeme parametry y=rok, m=měsíc (1–12)
    public function renderDefault(?int $y = null, ?int $m = null): void
    {
        // --- Základ: vychozí časová zóna a datum ---
        $tz = new \DateTimeZone('Europe/Prague');
        $base = new \DateTimeImmutable('today', $tz);
        if ($y !== null && $m !== null && $m >= 1 && $m <= 12) {
            $base = new \DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m), $tz);
        }

        $monthStart = $base->modify('first day of this month')->setTime(0, 0);
        $monthEndExclusive = $monthStart->modify('first day of next month');

        // --- Formattery pro CZ lokalizaci (Intl/ICU) ---
        $locale = 'cs_CZ';
        $mkFmt = function (string $pattern) use ($locale, $tz): \IntlDateFormatter {
            return new \IntlDateFormatter(
                $locale,
                \IntlDateFormatter::NONE,
                \IntlDateFormatter::NONE,
                $tz,
                \IntlDateFormatter::GREGORIAN,
                $pattern
            );
        };

        // Nadpis měsíce (samostatný název měsíce → LLLL), např. "září 2025"
        $this->template->monthLabel = $mkFmt('LLLL yyyy')->format($monthStart);

        // Krátké názvy dní v týdnu pro hlavičku kalendáře (Po, Út, …)
        $monday = $monthStart->modify('monday this week');
        $weekdays = [];
        for ($i = 0; $i < 7; $i++) {
            $weekdays[] = $mkFmt('EEE')->format($monday->modify("+$i day"));
        }
        $this->template->weekdays = $weekdays;

        // Helper do šablony: {$formatCz($day, 'd. MMMM y')} → "1. září 2025"
        $this->template->formatCz = function (\DateTimeInterface $d, string $pattern) use ($mkFmt): string {
            return $mkFmt($pattern)->format($d);
        };

        // --- Dny v aktuálním měsíci ---
        $daysInMonth = (int) $monthStart->format('t');
        $days = [];
        for ($i = 0; $i < $daysInMonth; $i++) {
            $days[] = $monthStart->modify("+$i day");
        }

        // --- Navigace předchozí/další měsíc ---
        $prev = $monthStart->modify('-1 month');
        $next = $monthStart->modify('+1 month');

        // --- Pokoje ---
        $rooms = $this->roomRepository->getAll()->order('ID')->fetchAll();

        // --- Obsazenost ---
        $occupiedMap = [];
        foreach ($rooms as $room) {
            $roomId = (int) $room->ID;
            $occupiedMap[$roomId] = [];

            $reservations = $this->reservationRoomRepository
                ->getReservationsForRoomInRange($roomId, $monthStart, $monthEndExclusive);

            foreach ($reservations as $res) {
                // ořež na aktuální měsíc (konvence [from, to))
                $start = $res->Date_from < $monthStart ? $monthStart : $res->Date_from;
                $end   = $res->Date_to->modify('+1 day') > $monthEndExclusive ? $monthEndExclusive : $res->Date_to;

                // připrav datové pole s detaily
                $detail = [
                    'name'       => trim($res->First . ' ' . $res->Second),
                    'mail'       => (string) $res->Mail,
                    'tel'        => (string) $res->Tel,
                    'id'         => (int) $res->ID,
                    'deposit'    => (float) $res->Deposit,
                    'totalPrice' => (float) $res->totalPrice,
                ];

                for ($d = $start; $d < $end; $d = $d->modify('+1 day')) {
                    $key = $d->format('Y-m-d');
                    $occupiedMap[$roomId][$key] ??= [];
                    $occupiedMap[$roomId][$key][] = $detail; // umožní i výjimečný overlap
                }
            }
        }

        // --- Úklidy ---
        $cleanRows = $this->cleanRepository->getAll()
            ->where('day >= ?', $monthStart->format('Y-m-d'))
            ->where('day <  ?', $monthEndExclusive->format('Y-m-d'))
            ->fetchAll();

        $cleanMap = [];
        foreach ($cleanRows as $r) {
            $cleanMap[(int) $r->room_id][$r->day->format('Y-m-d')] = true;
        }

        // --- Předání do šablony ---
        $this->template->monthStart = $monthStart;
        $this->template->days       = $days;
        $this->template->rooms      = $rooms;
        $this->template->occupied   = $occupiedMap;

        // Navigační parametry
        $this->template->prevY = (int) $prev->format('Y');
        $this->template->prevM = (int) $prev->format('n'); // 1–12
        $this->template->nextY = (int) $next->format('Y');
        $this->template->nextM = (int) $next->format('n');

        // (volitelné) popisky pro "‹ srpen 2025" / "říjen 2025 ›"
        $this->template->prevLabel = $mkFmt('LLLL yyyy')->format($prev);
        $this->template->nextLabel = $mkFmt('LLLL yyyy')->format($next);

        // Výchozí den pro úklidový formulář apod.
        $cleanDefaultDay = new \DateTimeImmutable('today', $tz);
        $this->template->cleanDefaultDay = $cleanDefaultDay;
        $this->template->cleanMap = $cleanMap;
    }

    public function renderDetail(int $id): void
    {

        // 1) načti rezervaci
        $reservation = $this->accommodationRepository->getById($id);
        if (!$reservation) {
            $this->error('Rezervace nenalezena.');
        }

        // 2) načti přiřazené pokoje (přes M:N)
        // :reservation_room je Nette zkratka pro JOIN na M:N tabulku
        $rooms = $this->roomRepository->getAll()
            ->where(':reservation_room.reservation_id', $id)
            ->order('ID')
            ->fetchAll();

        // 3) pomocné výpočty
        $nights = $this->accommodationRepository->getNumberOfNights(
            $reservation->Date_from,
            $reservation->Date_to
        );

        $this->template->reservation = $reservation;
        $this->template->rooms = $rooms;
        $this->template->nights = $nights;

        $comments = $this->reservationCommentsRepository->getAll()
            ->where('reservation_id', $id)
            ->order('created_at DESC')
            ->fetchAll();

        $this->template->comments = $comments;
        $this->template->allRooms = $this->roomRepository->getAll()
            ->order('Name')
            ->fetchAll();

        // ID aktuálně přiřazených pokojů
        $selectedRoomIds = array_map(fn($r) => (int)$r->ID, $rooms);
        $this->template->selectedRoomIds = $selectedRoomIds;

        // dostupnost pokoje pro daný termín s vyloučením téhle rezervace
        $this->template->isRoomAvailable = function (int $roomId, $from, $to) use ($id): bool {
            return $this->reservationRoomRepository->isRoomAvailableExclusive(
                $roomId, $from, $to, $excludeReservationId = $id
            );
        };

    }

    public function renderReservation(){
        $this->template->title = 'Vytvořit rezervaci';
    }

    protected function createComponentAccommodationForm(): Form
    {
        // defaulty: dnes / zítra (uživatel může změnit)
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');

        // vytvoření formuláře z tvé továrny (bez blokování nedostupných; kontrola bude server-side)
        $form = $this->accommodationFormFactory->create();

        // předvyplníme datumy
        $form['Date_from']->setDefaultValue($today->format('Y-m-d'));
        $form['Date_to']->setDefaultValue($tomorrow->format('Y-m-d'));

        $form->onSuccess[] = [$this, 'accommodationFormSucceeded'];
        return $form;
    }

    /** Zpracování odeslaného formuláře */
    public function accommodationFormSucceeded(Form $form, ArrayHash $values): void
    {
        // 1) validace
        $roomIds = array_values(array_unique(array_map('intval', (array)($values->room_ids ?? []))));
        if ($roomIds === []) {
            $form['room_ids']->addError('Vyberte alespoň jeden pokoj.');
            return;
        }

        try {
            $from = new \DateTimeImmutable((string)$values->Date_from);
            $to   = new \DateTimeImmutable((string)$values->Date_to);
        } catch (\Throwable) {
            $form['Date_from']->addError('Neplatné datum.');
            return;
        }
        if ($from > $to) {
            $form['Date_to']->addError('Datum odjezdu musí být stejné nebo po datu příjezdu.');
            return;
        }

        // 2) ověř, že pokoje existují
        $rooms = $this->roomRepository->getAll()->where('ID', $roomIds)->fetchAll();
        if (count($rooms) !== count($roomIds)) {
            $form['room_ids']->addError('Některý z vybraných pokojů neexistuje.');
            return;
        }

        // 3) dostupnost (inkluzivně – blokuje i den odjezdu)
        foreach ($rooms as $r) {
            if (!$this->reservationRoomRepository->isRoomAvailableExclusive(
                (int)$r->ID, $from, $to, null
            )) {
                $form['room_ids']->addError(sprintf('Pokoj „%s“ není v požadovaném termínu volný.', $r->Name));
                return;
            }
        }

        // 4) výpočet ceny
        $nights = $this->accommodationRepository->getNumberOfNights($from, $to);
        $basePerNight = 0.0;
        foreach ($rooms as $r) {
            $basePerNight += (float)$r->Price;
        }
        $persons = (int)$values->Person;
        $hasDog  = (int)$values->Dog === 1;
        $totalPrice = ($basePerNight * $nights)
            + (($nights * $persons) * 50)
            + ($hasDog ? ($nights * 150) : 0);

        // 5) uložení rezervace
        $row = $this->accommodationRepository->getAll()->insert([
            'First'      => (string)$values->First,
            'Second'     => (string)$values->Second,
            'Mail'       => (string)$values->Mail,
            'Tel'        => (string)$values->Tel,
            'Person'     => $persons,
            'Date_from'  => $from->format('Y-m-d'),
            'Date_to'    => $to->format('Y-m-d'),
            'Dog'        => (int)$values->Dog,
            'Note'       => (string)($values->Note ?? ''),
            'Solved'     => 1, // uprav dle procesu
            'Old'        => 0,
            'totalPrice' => (int)round($totalPrice),
            'Deposit'    => 0,
            'Gdpr'       => new \DateTimeImmutable(), // pokud má být NOT NULL
        ]);
        if (!$row) {
            $this->flashMessage('Nepodařilo se uložit rezervaci.', 'error');
            $this->redirect('this');
        }

        // 6) vazby pokojů
        foreach ($roomIds as $rid) {
            $this->reservationRoomRepository->insert([
                'Reservation_id' => (int)$row->ID,
                'Room_id'        => (int)$rid,
            ]);
        }

        $this->flashMessage('Rezervace vytvořena.');
        // přesměruj na detail (uprav route dle projektu)
        $this->redirect(':Admin:Home:detail', ['id' => (int)$row->ID]);
    }

    public function handleAddComment(int $id): void
    {
        $note = trim((string)($this->getHttpRequest()->getPost('note') ?? ''));
        $this->reservationCommentsRepository->insert(['reservation_id' => $id, 'note' => $note, 'created_at' => date('Y-m-d H:i:s')]);

        $this->flashMessage('Poznámka uložena.');
        $this->redirect('this', ['id' => $id]);
    }

    public function handleAddDeposit(int $id): void
    {
        $amount = (float) ($this->getHttpRequest()->getPost('amount') ?? 0);
        $this->accommodationRepository->update($id, [
            'Deposit' => $amount,
        ]);

        $this->flashMessage('Záloha uložena.');
        $this->redirect('this', ['id' => $id]); // refresh detailu
    }

    public function handleMarkPaid(int $id): void
    {
        $reservation = $this->accommodationRepository->getById($id);
        $this->accommodationRepository->update($id, [
            'Deposit' => $reservation->totalPrice,
        ]);

    }

    public function handleChangeDates(int $id): void
    {
        // načti rezervaci
        $reservation = $this->accommodationRepository->getById($id);

        // načti vstupy
        $fromStr = (string) ($this->getHttpRequest()->getPost('date_from') ?? '');
        $toStr   = (string) ($this->getHttpRequest()->getPost('date_to') ?? '');

        try {
            $from = new \DateTimeImmutable($fromStr);
            $to   = new \DateTimeImmutable($toStr);
        } catch (\Throwable $e) {
            $this->flashMessage('Neplatné datum.', 'error');
            $this->redirect('this', ['id' => $id]);
            return;
        }

        // validace: od <= do
        if ($from > $to) {
            $this->flashMessage('Příjezd musí být před (nebo shodně s) odjezdem.', 'error');
            $this->redirect('this', ['id' => $id]);
            return;
        }

        // přiřazené pokoje k rezervaci
        $rooms = $this->roomRepository->getAll()
            ->where(':reservation_room.reservation_id', $id)
            ->fetchAll();

        // kontrola dostupnosti pro všechny pokoje (včetně dne odjezdu)
        foreach ($rooms as $r) {
            if (!$this->reservationRoomRepository->isRoomAvailableExclusive(
                (int) $r->ID,
                $from,
                $to,
                $excludeReservationId = $id // aby si rezervace nepřekážela sama sobě
            )) {
                $this->flashMessage(sprintf(
                    ' Jeden z pokojů není v požadovaném termínu dostupný.'
                ), 'error');
                $this->redirect('this', ['id' => $id]);
                return;
            }
        }

        // OK – ulož změnu termínu
        $this->accommodationRepository->update($id, [
            'Date_from' => $from->format('Y-m-d'),
            'Date_to'   => $to->format('Y-m-d'),
        ]);

        $basePrice = 0;
        foreach($rooms as $r) {
            $basePrice += $r->Price;
        }
        $nights = $this->accommodationRepository->getNumberOfNights(
            $from,
            $to
        );
        if($reservation->Dog == 1){
            $totalPrice = ($basePrice * $nights) + (($nights * $reservation->Person) * 50 ) + ($nights * 150);
        } else {
            $totalPrice = ($basePrice * $nights) + (($nights * $reservation->Person) * 50 );
        }
        $this->accommodationRepository->update($id, [
            'totalPrice' => $totalPrice,
        ]);
        $this->flashMessage('Termín upraven.');
        $this->redirect('this', ['id' => $id]);
    }

    public function handleCancelReservation(int $id): void
    {
        $reservation = $this->accommodationRepository->getById($id);
        if (!$reservation) {
            $this->error('Rezervace nenalezena.');
        }

        $this->reservationRoomRepository->deleteByReservationId($id);
        $this->accommodationRepository->delete($id);
        $this->redirect('Home:default');

    }

    public function handleChangeRooms(int $id): void
    {
        $reservation = $this->accommodationRepository->getById($id);
        if (!$reservation) {
            $this->error('Rezervace nenalezena.');
        }

        $postRoomIds = (array) ($this->getHttpRequest()->getPost('room_ids') ?? []);
        $roomIds = array_values(array_unique(array_map('intval', $postRoomIds)));

        if ($roomIds === []) {
            $this->flashMessage('Vyber alespoň jeden pokoj.', 'error');
            $this->redirect('this', ['id' => $id]);
            return;
        }

        // existují všechny?
        $rooms = $this->roomRepository->getAll()->where('ID', $roomIds)->fetchAll();
        if (count($rooms) !== count($roomIds)) {
            $this->flashMessage('Některý z vybraných pokojů neexistuje.', 'error');
            $this->redirect('this', ['id' => $id]);
            return;
        }

        // dostupnost (inkluzivně) – vyloučím tuhle rezervaci
        foreach ($rooms as $r) {
            if (!$this->reservationRoomRepository->isRoomAvailableExclusive(
                (int)$r->ID, $reservation->Date_from, $reservation->Date_to, $excludeReservationId = $id
            )) {
                $this->flashMessage(sprintf('Pokoj "%s" není v požadovaném termínu volný.', $r->Name), 'error');
                $this->redirect('this', ['id' => $id]);
                return;
            }
        }

        // přepiš vazby (jednoduše smaž a vlož)
        $this->reservationRoomRepository->getAll()
            ->where('reservation_id', $id)
            ->delete();

        foreach ($roomIds as $rid) {
            $this->reservationRoomRepository->insert([
                'Reservation_id' => $id,
                'Room_id'        => $rid,
            ]);
        }

        // přepočítej cenu
        $nights = $this->accommodationRepository->getNumberOfNights(
            $reservation->Date_from, $reservation->Date_to
        );

        $basePerNight = 0.0;
        foreach ($rooms as $room) {
            $basePerNight += (float)$room->Price;
        }

        $totalPrice = ($basePerNight * $nights)
            + (($nights * (int)$reservation->Person) * 50)
            + ($reservation->Dog ? ($nights * 150) : 0);

        $this->accommodationRepository->update($id, [
            'totalPrice' => $totalPrice,
        ]);

        $this->flashMessage('Pokoje byly změněny.');
        $this->redirect('this', ['id' => $id]);
    }

    public function handleSaveCleaning(): void
    {
        $dayStr = (string) ($this->getHttpRequest()->getPost('day') ?? '');
        $roomIdsPost = (array) ($this->getHttpRequest()->getPost('room_ids') ?? []);

        try {
            $day = new \DateTimeImmutable($dayStr);
        } catch (\Throwable) {
            $this->flashMessage('Neplatné datum.', 'error');
            $this->redirect('this');
            return;
        }

        $roomIds = array_values(array_unique(array_map('intval', $roomIdsPost)));

        foreach ($roomIds as $rid) {
            $this->cleanRepository->insert([
                'room_id' => $rid,
                'day'     => $day->format('Y-m-d'),
            ]);
        }

        $this->flashMessage('Uloženo.');
        $this->redirect('this');


    }

    public function handleCreateReservation(): void
    {
        $post = $this->getHttpRequest()->getPost();

        $first  = trim((string)($post['first']  ?? ''));
        $second = trim((string)($post['second'] ?? ''));
        $mail   = trim((string)($post['mail']   ?? ''));
        $tel    = trim((string)($post['tel']    ?? ''));
        $note   = trim((string)($post['note']   ?? ''));
        $person = (int)($post['person'] ?? 1);
        $dog    = (int)($post['dog']    ?? 0);
        $roomIds = array_values(array_unique(array_map('intval', (array)($post['room_ids'] ?? []))));
        $fromStr = (string)($post['date_from'] ?? '');
        $toStr   = (string)($post['date_to']   ?? '');

        // základní validace
        if (!$first || !$second || !$mail || !$tel) {
            $this->flashMessage('Vyplňte jméno, příjmení, email a telefon.', 'error');
            $this->redirect('this'); return;
        }
        if (!$roomIds) {
            $this->flashMessage('Vyberte alespoň jeden pokoj.', 'error');
            $this->redirect('this'); return;
        }
        try {
            $from = new \DateTimeImmutable($fromStr);
            $to   = new \DateTimeImmutable($toStr);
        } catch (\Throwable) {
            $this->flashMessage('Neplatná data pobytu.', 'error');
            $this->redirect('this'); return;
        }
        if ($from > $to) {
            $this->flashMessage('Příjezd nesmí být po odjezdu.', 'error');
            $this->redirect('this'); return;
        }

        // ověř, že pokoje existují
        $rooms = $this->roomRepository->getAll()->where('ID', $roomIds)->fetchAll();
        if (count($rooms) !== count($roomIds)) {
            $this->flashMessage('Některý z vybraných pokojů neexistuje.', 'error');
            $this->redirect('this'); return;
        }

        // dostupnost (inkluzivně – blokuje i den odjezdu)
        foreach ($rooms as $r) {
            if (!$this->reservationroomRepository->isRoomAvailableExclusive(
                (int)$r->ID, $from, $to, null
            )) {
                $this->flashMessage(sprintf('Pokoj "%s" není v požadovaném termínu volný.', $r->Name), 'error');
                $this->redirect('this'); return;
            }
        }

        // výpočet nocí a ceny
        $nights = $this->accommodationRepository->getNumberOfNights($from, $to);

        $basePerNight = 0.0;
        foreach ($rooms as $r) { $basePerNight += (float)$r->Price; }

        $totalPrice = ($basePerNight * $nights)
            + (($nights * $person) * 50)
            + ($dog ? ($nights * 150) : 0);

        // INSERT rezervace (použij přímo Explorer přes getAll()->insert kvůli získání ID)
        $row = $this->accommodationRepository->getAll()->insert([
            'First'      => $first,
            'Second'     => $second,
            'Mail'       => $mail,
            'Tel'        => $tel,
            'Person'     => $person,
            'Date_from'  => $from->format('Y-m-d'),
            'Date_to'    => $to->format('Y-m-d'),
            'Dog'        => $dog,
            'Note'       => $note,
            'Solved'     => 1,           // rovnou potvrzená, uprav dle procesu
            'Old'        => 0,
            'totalPrice' => $totalPrice,
            'Deposit'    => 0,
            'Gdpr'       => new \DateTimeImmutable(), // nebo null podle schématu
        ]);

        if (!$row) {
            $this->flashMessage('Nepodařilo se uložit rezervaci.', 'error');
            $this->redirect('this'); return;
        }

        // zápis vazeb pokojů
        foreach ($roomIds as $rid) {
            $this->reservationroomRepository->insert([
                'Reservation_id' => (int)$row->ID,
                'Room_id'        => (int)$rid,
            ]);
        }

        $this->flashMessage('Rezervace vytvořena.');
        $this->redirect(':Admin:Home:detail', ['id' => (int)$row->ID]);
    }

    public function renderAccommodationlist(int $id): void
    {
        $r = $this->accommodationRepository->getById($id);

        $nights = $this->accommodationRepository->getNumberOfNights($r->Date_from, $r->Date_to);

        $totalPrice = $r->totalPrice - ($nights * 50 * $r->Person);

        $this['receiptForm']->setDefaults([
            'received_from'     => trim($r->First . ' ' . $r->Second),
            'paid_at'           => $r->Date_from->format('Y-m-d'),
            'total_amount'      => $totalPrice,
            'total_amount_without_vat' => $totalPrice - ($totalPrice * 0.12),
            'vat'               => 12,
            'text'              => $r->Person . 'os/ ' . $nights . ($nights == 1 ? 'noc' : 'noci'),
        ]);
    }

    public function renderFeelist(int $id): void
    {
        $r = $this->accommodationRepository->getById($id);

        $nights = $this->accommodationRepository->getNumberOfNights($r->Date_from, $r->Date_to);

        $totalPrice = $nights * 50 * $r->Person;

        $this['feeForm']->setDefaults([
            'received_from'     => trim($r->First . ' ' . $r->Second),
            'paid_at'           => $r->Date_from->format('Y-m-d'),
            'total_amount'      => $totalPrice,
            'text'              => $r->Person . ' os/ ' . $nights . ($nights == 1 ? ' noc' : ' noci'),
        ]);
    }

    protected function createComponentReceiptForm(): Form
    {
        $form = new Form;

        $form->addText('received_from', 'Přijato od:');
        $form->addDate('paid_at', 'Dne:');
        $form->addInteger('total_amount', 'Přijato KČ:');
        $form->addFloat('total_amount_without_vat', 'Cena bez DPH');
        $form->addInteger('vat', 'DPH');
        $form->addText('text', 'Text');

        // Odeslání
        $form->addSubmit('download', 'Vygenerovat PDF');

        // PDF generování
        $form->onSuccess[] = function (Form $form, ArrayHash $v): void {

            $totalPrice = $v->total_amount;
            $numberToWords = new NumberToWords();
            $transformer = $numberToWords->getNumberTransformer('cs');

            $data = [
                'paid_at'       => $v->paid_at->format('d.m.Y'),
                'received_from' => $v->received_from,
                'total_amount'  => $totalPrice,
                'total_amount_words' => $transformer->toWords($totalPrice),
                'total_amount_without_vat_words' => $transformer->toWords($v->total_amount_without_vat),
                'total_amount_without_vat' => $v->total_amount_without_vat,
                'vat'           => $v->vat,
                'vat_amount'    => $totalPrice - $v->total_amount_without_vat,
                'text'          => $v->text,
            ];

            // --- Render šablony Latte
            $template = $this->templateFactory->createTemplate();
            $template->setFile(__DIR__ . '/template/receipt.pdf.latte');
            $template->data = $data;
            $html = (string) $template;

            // --- Browsershot -> PDF
            $pdfPath = tempnam(sys_get_temp_dir(), 'receipt') . '.pdf';

            Browsershot::html($html)
                ->setNodeBinary('C:\Program Files\nodejs\node.exe')
                ->setNpmBinary('C:\Program Files\nodejs\npm.cmd')
                ->setCustomTempPath(__DIR__ . '/../../temp/browsershot')
                ->paperSize(148, 105, 'mm')
                ->landscape(true)
                ->save($pdfPath);

            // --- Název souboru
            $suffix = $v->received_from ?: date('Ymd-His');
            $suffix = Strings::replace($suffix, '~\s+~', '-');
            $filename = sprintf('prijmovy-pokladni-doklad-ubytovani-%s.pdf', $suffix);

            // --- Download response
            $this->sendResponse(new FileResponse($pdfPath, $filename, 'application/pdf'));
        };

        return $form;
    }

    protected function createComponentFeeForm(): Form
    {
        $form = new Form;

        $form->addText('received_from', 'Přijato od:');
        $form->addDate('paid_at', 'Dne:');
        $form->addInteger('total_amount', 'Přijato KČ:');
        $form->addText('text', 'Text');

        // Odeslání
        $form->addSubmit('download', 'Vygenerovat PDF');

        // PDF generování
        $form->onSuccess[] = function (Form $form, ArrayHash $v): void {

            $totalPrice = $v->total_amount;
            $numberToWords = new NumberToWords();
            $transformer = $numberToWords->getNumberTransformer('cs');

            $data = [
                'paid_at'       => $v->paid_at->format('d.m.Y'),
                'received_from' => $v->received_from,
                'total_amount'  => $totalPrice,
                'total_amount_words' => $transformer->toWords($totalPrice),
                'text'          => $v->text,
            ];

            // --- Render šablony Latte
            $template = $this->templateFactory->createTemplate();
            $template->setFile(__DIR__ . '/template/fee.pdf.latte');
            $template->data = $data;
            $html = (string) $template;

            // --- Browsershot -> PDF
            $pdfPath = tempnam(sys_get_temp_dir(), 'receipt') . '.pdf';

            Browsershot::html($html)
                ->setNodeBinary('C:\Program Files\nodejs\node.exe')
                ->setNpmBinary('C:\Program Files\nodejs\npm.cmd')
                ->setCustomTempPath(__DIR__ . '/../../temp/browsershot')
                ->paperSize(148, 105, 'mm')
                ->landscape(true)
                ->save($pdfPath);

            // --- Název souboru
            $suffix = $v->received_from ?: date('Ymd-His');
            $suffix = Strings::replace($suffix, '~\s+~', '-');
            $filename = sprintf('prijmovy-pokladni-doklad-lazensky-poplatek-%s.pdf', $suffix);

            // --- Download response
            $this->sendResponse(new FileResponse($pdfPath, $filename, 'application/pdf'));
        };

        return $form;
    }
}