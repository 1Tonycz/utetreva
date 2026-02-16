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
use Dompdf\Dompdf;
use Dompdf\Options;
use Nette\Application\Responses\FileResponse;
use Nette\Bridges\ApplicationLatte\TemplateFactory;
use Nette\Utils\Strings;
use App\Core\Mail\MailService;
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
        private TemplateFactory $templateFactory,
        private MailService $mailService,
    ) {}

    // přijmeme parametry y=rok, m=měsíc (1–12)
    public function renderDefault(?int $y = null, ?int $m = null): void
    {
        if (!$this->getUser()->isAllowed('home', 'default')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $tz = new \DateTimeZone('Europe/Prague');
        $base = new \DateTimeImmutable('today', $tz);
        if ($y !== null && $m !== null && $m >= 1 && $m <= 12) {
            $base = new \DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m), $tz);
        }

        $monthStart        = $base->modify('first day of this month')->setTime(0, 0);
        $monthEndExclusive = $monthStart->modify('first day of next month');

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

        $this->template->monthLabel = $mkFmt('LLLL yyyy')->format($monthStart);

        $monday = $monthStart->modify('monday this week');
        $weekdays = [];
        for ($i = 0; $i < 7; $i++) {
            $weekdays[] = $mkFmt('EEE')->format($monday->modify("+$i day"));
        }
        $this->template->weekdays = $weekdays;

        // Helper do šablony
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

        // --- Obsazenost: nocování + den odjezdu jako dlaždice ---
        $occupiedMap = [];

        foreach ($rooms as $room) {
            $roomId = (int) $room->ID;
            $occupiedMap[$roomId] = [];

            // 1) Rezervace, které v měsíci NOCUJÍ ([from, to) – end exkluzivní)
            $reservations = $this->reservationRoomRepository
                ->getReservationsForRoomInRange($roomId, $monthStart, $monthEndExclusive);

            foreach ($reservations as $res) {
                // ořez na zobrazovaný měsíc
                $start = $res->Date_from < $monthStart ? $monthStart : $res->Date_from;
                $end   = $res->Date_to   > $monthEndExclusive ? $monthEndExclusive : $res->Date_to;

                $hasNote = (bool) $this->reservationCommentsRepository
                    ->getAll()
                    ->where('reservation_id', $res->ID)
                    ->count('*');

                $detail = [
                    'name'       => trim($res->First . ' ' . $res->Second),
                    'mail'       => (string) $res->Mail,
                    'tel'        => (string) $res->Tel,
                    'id'         => (int) $res->ID,
                    'deposit'    => (float) $res->Deposit,
                    'totalPrice' => (float) $res->totalPrice,
                    'Person'     => (int) ($res->Person + $res->Child),
                    'hasNote'    => $hasNote,
                    'Book'       => $res->Card_id,
                    'date_from' => $res->Date_from->format('Y-m-d'),
                ];

                // nocování po dnech: [start, end)
                for ($d = $start; $d < $end; $d = $d->modify('+1 day')) {
                    $key = $d->format('Y-m-d');
                    $occupiedMap[$roomId][$key] ??= [];
                    $occupiedMap[$roomId][$key][] = $detail;
                }
            }

            // 2) ODJEZDY spadající do měsíce → přidej i den odjezdu jako obsazený (klikací)
            $edgeDepartures = $this->reservationRoomRepository
                ->getDeparturesForRoomInRange($roomId, $monthStart, $monthEndExclusive);

            foreach ($edgeDepartures as $res) {
                $hasNote = (bool) $this->reservationCommentsRepository
                    ->getAll()
                    ->where('reservation_id', $res->ID)
                    ->count('*');

                $detail = [
                    'name'       => trim($res->First . ' ' . $res->Second),
                    'mail'       => (string) $res->Mail,
                    'tel'        => (string) $res->Tel,
                    'id'         => (int) $res->ID,
                    'deposit'    => (float) $res->Deposit,
                    'totalPrice' => (float) $res->totalPrice,
                    'Person'     => (int) ($res->Person + $res->Child),
                    'hasNote'    => $hasNote,
                    'Book'       => $res->Card_id,
                    'date_from' => $res->Date_from->format('Y-m-d'),
                ];

                $depKey = $res->Date_to->format('Y-m-d'); // den odjezdu
                $occupiedMap[$roomId][$depKey] ??= [];
                $occupiedMap[$roomId][$depKey][] = $detail;
            }
        }

        foreach ($occupiedMap as $roomId => &$daysMap) {
            foreach ($daysMap as $key => &$cell) {
                usort($cell, function(array $a, array $b): int {
                    $cmp = strcmp($a['date_from'], $b['date_from']); // vzestupně
                    return $cmp !== 0 ? $cmp : ($a['id'] <=> $b['id']); // stabilizace
                });
            }
        }
        unset($daysMap, $cell);


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

        // Popisky šipek
        $this->template->prevLabel = $mkFmt('LLLL yyyy')->format($prev);
        $this->template->nextLabel = $mkFmt('LLLL yyyy')->format($next);

        // Výchozí den pro „Uklizeno“
        $cleanDefaultDay = new \DateTimeImmutable('today', $tz);
        $this->template->cleanDefaultDay = $cleanDefaultDay;
        $this->template->cleanMap        = $cleanMap;
    }



    public function renderDetail(int $id): void
    {
        if (!$this->getUser()->isAllowed('home', 'detail')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

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
        if (!$this->getUser()->isAllowed('home', 'reservation')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }
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

        // === NOVÉ: načtení speciálních cen z formuláře ==========================
        // container 'custom_prices' má klíče jako ID pokoje; prázdné = ignorovat
        $customMap = [];
        if (isset($values->custom_prices) && $values->custom_prices instanceof \Nette\Utils\ArrayHash) {
            foreach ($values->custom_prices as $rid => $raw) {
                if ($raw === null || $raw === '') {
                    continue; // prázdné = použije se defaultní cena pokoje
                }
                // pro jistotu nahraď čárku tečkou, ať projde locale typu "123,45"
                $num = (float) str_replace(',', '.', (string) $raw);
                if ($num >= 0) {
                    $customMap[(int) $rid] = $num;
                }
            }
        }
        // =======================================================================

        // 4) výpočet ceny
        $nights = $this->accommodationRepository->getNumberOfNights($from, $to);

        // sečti ceny za vybrané pokoje s respektem ke speciálním cenám
        $basePerNight = 0.0;
        foreach ($rooms as $r) {
            $rid = (int) $r->ID;
            // pokud je pro daný pokoj zadaná speciální cena, použij ji; jinak Room.Price
            $pricePerNight = $customMap[$rid] ?? (float) $r->Price;
            $basePerNight += $pricePerNight;
        }

        $persons  = (int) $values->Person;
        $children = (int) $values->Child;
        $dogs = (int) $values->Dog_count;

        // Checkbox -> spolehlivý převod na bool (někdy chodí TRUE/FALSE, jindy 'on', '1', atd.)
        $hasDog = !empty($values->Dog);

        $totalPrice = ($basePerNight * $nights)
            + (($nights * $persons) * 50)
            + ($hasDog ? ($nights * 150 * $dogs) : 0);

        // TODO: pokud máš pravidla pro slevy/příplatky za děti, aplikuj je sem s využitím $children

        $totalPrice = (int) round($totalPrice);

        // 5) uložení rezervace
        $row = $this->accommodationRepository->getAll()->insert([
            'First'      => (string)$values->First,
            'Second'     => (string)$values->Second,
            'Mail'       => (string)$values->Mail,
            'Tel'        => (string)$values->Tel,
            'Person'     => $persons,
            'Child'      => (int) $values->Child,
            'Baby'       => (int) $values->Baby,
            'Date_from'  => $from->format('Y-m-d'),
            'Date_to'    => $to->format('Y-m-d'),
            'Dog'        => $hasDog ? 1 : 0,
            'Note'       => (string)($values->Note ?? ''),
            'Solved'     => 1, // uprav dle procesu
            'Old'        => 0,
            'totalPrice' => $totalPrice,
            'Deposit'    => 0,
            'Gdpr'       => new \DateTimeImmutable(), // pokud je sloupec NOT NULL
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
        $this->redirect(':Admin:Home:detail', ['id' => (int)$row->ID]);
    }


    public function handleAddComment(int $id): void
    {
        if (!$this->getUser()->isAllowed('home', 'comments')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }
        $note = trim((string)($this->getHttpRequest()->getPost('note') ?? ''));
        $this->reservationCommentsRepository->insert(['reservation_id' => $id, 'note' => $note, 'created_at' => date('Y-m-d H:i:s')]);

        $this->flashMessage('Poznámka uložena.');
        $this->redirect('this', ['id' => $id]);
    }

    public function handleAddDeposit(int $id): void
    {
        if (!$this->getUser()->isAllowed('home', 'deposit')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $amount = (float) ($this->getHttpRequest()->getPost('amount') ?? 0);

        $data = $this->accommodationRepository->getById($id);
        $this->accommodationRepository->update($id, [
            'Deposit' => $amount,
        ]);

        $to = $data->Mail;
        $params = [
          'amount' => $amount,
        ];
        $lng = $data->Locale;
        $this->mailService->sendDepositPaid($to, $params, $lng);

        $this->flashMessage('Záloha uložena.');
        $this->redirect('this', ['id' => $id]); // refresh detailu
    }

    public function handleMarkPaid(int $id): void
    {
        if (!$this->getUser()->isAllowed('home', 'paid')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $reservation = $this->accommodationRepository->getById($id);
        $to = $reservation->Mail;
        $params = [
            'amount' => $reservation->totalPrice
        ];
        $lng = $reservation->Locale;
        if($to == ''){$this->accommodationRepository->update($id, [
            'Deposit' => $reservation->totalPrice,
        ]);} else{
            $this->mailService->sendPaidInvoice($to, $params, $lng);
            $this->accommodationRepository->update($id, [
                'Deposit' => $reservation->totalPrice,
            ]);
        }
    }

    public function handleChangeDates(int $id): void
    {
        if (!$this->getUser()->isAllowed('home', 'changeDate')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

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
        if (!$this->getUser()->isAllowed('home', 'cancel')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

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
        if (!$this->getUser()->isAllowed('home', 'changeRoom')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

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

        $this->flashMessage('Pokoje byly změněny.');
        $this->redirect('this', ['id' => $id]);
    }

    public function handleChangePrice(int $id): void
    {
        if (!$this->getUser()->isAllowed('home', 'changePrice')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $price = (float) ($this->getHttpRequest()->getPost('amount') ?? 0);

        $this->accommodationRepository->update($id, [
            'totalPrice' => $price,
        ]);

        $this->flashMessage('Cena byla upravena.');
        $this->redirect('this', ['id' => $id]);
    }

    public function handleSaveCleaning(): void
    {
        if (!$this->getUser()->isAllowed('home', 'clean')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

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
        if (!$this->getUser()->isAllowed('home', 'reservation')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

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

    private function mmToPt(float $mm): float
    {
        return $mm * 72.0 / 25.4; // 1 inch = 25.4 mm, 1 inch = 72 pt
    }

    public function renderAccommodationlist(int $id): void
    {
        if (!$this->getUser()->isAllowed('home', 'receipt')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $r = $this->accommodationRepository->getById($id);

        $nights = $this->accommodationRepository->getNumberOfNights($r->Date_from, $r->Date_to);

        $totalPrice = $r->totalPrice - ($nights * 50 * $r->Person);



        $this['receiptForm']->setDefaults([
            'received_from'     => trim($r->First . ' ' . $r->Second),
            'paid_at'           => $r->Date_from->format('Y-m-d'),
            'total_amount'      => $totalPrice,
            'vat'               => 12,
            'total_amount_without_vat' => $totalPrice/(1.12),
            'text'              => $r->Person . 'os/ ' . $nights . ($nights == 1 ? 'noc' : 'noci'),
        ]);
    }

    public function renderFeelist(int $id): void
    {
        if (!$this->getUser()->isAllowed('home', 'receipt')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

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
        $form->addFloat('total_amount', 'Přijato KČ:');
        $form->addFloat('total_amount_without_vat', 'Cena bez DPH');
        $form->addFloat('vat', 'DPH');
        $form->addText('text', 'Text');

        $form->addSubmit('download', 'Vygenerovat PDF');

        $form->onSuccess[] = function (Form $form, ArrayHash $v): void {

            $totalPrice = (float)$v->total_amount;
            $wordsPrice = (int)$v->total_amount;

            $numberToWords  = new NumberToWords();
            $transformer    = $numberToWords->getNumberTransformer('cs');

            $data = [
                'paid_at'       => $v->paid_at->format('d.m.Y'),
                'received_from' => (string)$v->received_from,
                'total_amount'  => $totalPrice,
                'total_amount_words' => $transformer->toWords($wordsPrice),
                'total_amount_without_vat_words' => $transformer->toWords((int)$v->total_amount_without_vat),
                'total_amount_without_vat' => (float)$v->total_amount_without_vat,
                'vat'           => (float)$v->vat,
                'vat_amount'    => $totalPrice - (float)$v->total_amount_without_vat,
                'text'          => (string)$v->text,
            ];

            // 1) Render Latte -> HTML (stejně jako dřív)
            $template = $this->templateFactory->createTemplate();
            $template->setFile(__DIR__ . '/template/receipt.pdf.latte');
            $template->data = $data;

            // doplníme <base> aby fungovaly relativní cesty na webhostingu
            $html = (string) $template;
            $baseHref = $this->getHttpRequest()->getUrl()->getBaseUrl();
            if (stripos($html, '<head>') !== false) {
                $html = preg_replace('~<head>~i', '<head><base href="' . htmlspecialchars($baseHref, ENT_QUOTES) . '">', $html, 1);
            }

            // 2) Dompdf (bez Node/NPM)
            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isRemoteEnabled', true);       // obrázky / fonty přes URL
            $options->set('isHtml5ParserEnabled', true);  // lepší parser

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');

            // přesný rozměr 148×105 mm landscape
            $w = $this->mmToPt(148);
            $h = $this->mmToPt(105);
            $dompdf->setPaper([$w, $h], 'landscape'); // pořadí: width, height (Dompdf rotuje podle orientation)

            $dompdf->render();

            $pdfPath = tempnam(sys_get_temp_dir(), 'receipt') . '.pdf';
            file_put_contents($pdfPath, $dompdf->output());

            $suffix   = $v->received_from ?: date('Ymd-His');
            $suffix   = Strings::replace($suffix, '~\s+~', '-');
            $filename = sprintf('prijmovy-pokladni-doklad-ubytovani-%s.pdf', $suffix);

            $this->sendResponse(new FileResponse($pdfPath, $filename, 'application/pdf'));
        };

        return $form;
    }


    protected function createComponentFeeForm(): Form
    {
        $form = new Form;

        $form->addText('received_from', 'Přijato od:');
        $form->addDate('paid_at', 'Dne:');
        $form->addFloat('total_amount', 'Přijato KČ:');
        $form->addText('text', 'Text');

        $form->addSubmit('download', 'Vygenerovat PDF');

        $form->onSuccess[] = function (Form $form, ArrayHash $v): void {

            $totalPrice      = (float)$v->total_amount;
            $wordsPrice = (int)$v->total_amount;
            $numberToWords   = new NumberToWords();
            $transformer     = $numberToWords->getNumberTransformer('cs');

            $data = [
                'paid_at'       => $v->paid_at->format('d.m.Y'),
                'received_from' => (string)$v->received_from,
                'total_amount'  => $totalPrice,
                'total_amount_words' => $transformer->toWords($wordsPrice),
                'text'          => (string)$v->text,
            ];

            $template = $this->templateFactory->createTemplate();
            $template->setFile(__DIR__ . '/template/fee.pdf.latte');
            $template->data = $data;

            $html = (string)$template;
            $baseHref = $this->getHttpRequest()->getUrl()->getBaseUrl();
            if (stripos($html, '<head>') !== false) {
                $html = preg_replace('~<head>~i', '<head><base href="' . htmlspecialchars($baseHref, ENT_QUOTES) . '">', $html, 1);
            }

            $options = new Options();
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');

            // A6 landscape (148×105 mm)
            $w = $this->mmToPt(148);
            $h = $this->mmToPt(105);
            $dompdf->setPaper([$w, $h], 'landscape');

            $dompdf->render();

            $pdfPath = tempnam(sys_get_temp_dir(), 'fee') . '.pdf';
            file_put_contents($pdfPath, $dompdf->output());

            $suffix   = $v->received_from ?: date('Ymd-His');
            $suffix   = Strings::replace($suffix, '~\s+~', '-');
            $filename = sprintf('prijmovy-pokladni-doklad-lazensky-poplatek-%s.pdf', $suffix);

            $this->sendResponse(new FileResponse($pdfPath, $filename, 'application/pdf'));
        };

        return $form;
    }

    protected function createComponentAccommodationBookForm(): Form
    {
        $form = new Form;

        $form->addText('First', 'Jméno:')->setRequired();
        $form->addText('Second', 'Příjmení:')->setRequired();
        $form->addInteger('Card_id', 'Číslo dokladu:')->setRequired();
        $form->addText('Nationality', 'Státní příslušnost:')->setRequired();
        $form->addText('Town', 'Město/Obec:')->setRequired();
        $form->addText('Town_part', 'Část obce:');
        $form->addText('Street', 'Ulice a číslo popisné:')->setRequired();
        $form->addDate('Birth', 'Datum narození:')->setRequired();
        $form->addDate('Date_from', 'Datum příjezdu:')->setRequired();
        $form->addDate('Date_to', 'Datum odjezdu:')->setRequired();

        $form->addSubmit('Submit', 'Uložit');

        $form->onSuccess[] = function (Form $form, array $v): void {
            $id = $this->getParameter('id');

            $this->accommodationRepository->update($id, [
                'First' => $v['First'],
                'Second' => $v['Second'],
                'Card_id' => $v['Card_id'],
                'Nationality' => $v['Nationality'],
                'Town' => $v['Town'],
                'Town_part' => $v['Town_part'],
                'Street' => $v['Street'],
                'Birth' => $v['Birth'],
                'Date_from' => $v['Date_from'],
                'Date_to' => $v['Date_to'],
            ]);
            $this->flashMessage('Ubytovací list byl vytvořen.');
            $this->redirect('detail', ['id' => $id]);
        };
        return $form;
    }

    public function renderAccommodationBook(int $id): void
    {
        if (!$this->getUser()->isAllowed('home', 'book')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }
        $r = $this->accommodationRepository->getById($id);
        $this['accommodationBookForm']->setDefaults([
            'First'      => $r->First,
            'Second'     => $r->Second,
            'Date_from'  => $r->Date_from->format('Y-m-d'),
            'Date_to'    => $r->Date_to->format('Y-m-d'),
        ]);
    }

    public function renderBook(): void
    {
        if (!$this->getUser()->isAllowed('home', 'book')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $all = $this->accommodationRepository->getAll()->where('Card_id != ?', 0)->order('Date_from DESC')->fetchAll();
        $this->template->all = $all;
    }

    public function handleDeleteInfo(int $id): void
    {
        if (!$this->getUser()->isAllowed('home', 'deleteInfo')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $this->accommodationRepository->update($id, [
            'Card_id'     => 0,
            'Nationality' => null,
            'Town'        => null,
            'Town_part'   => null,
            'Street'      => null,
            'Birth'       => null,
        ]);


        $this->redirect('this');
    }

    public function handleDeleteComment(int $commentId): void
    {
        if (!$this->getUser()->isAllowed('home', 'deleteComment')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $this->reservationCommentsRepository->delete($commentId);

        $reservationId = $this->getParameter('id');
        $this->flashMessage('Poznámka smazána.');
        $this->redirect('Home:detail', ['id' => $reservationId]);
    }

}