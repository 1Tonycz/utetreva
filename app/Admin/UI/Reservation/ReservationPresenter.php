<?php

namespace App\Admin\UI\Reservation;

use App\Admin\UI\BasePresenter;
use App\Core\Mail\MailService;
use App\Core\Repository\AccommodationRepository;
use App\Core\Repository\ReservationroomRepository;
use App\Core\Repository\RoomRepository;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;
use Nette\Utils\Strings;

final class ReservationPresenter extends BasePresenter
{
    public function __construct(
        private AccommodationRepository $accommodationRepository,
        private RoomRepository $roomRepository,
        private ReservationroomRepository $reservationroomRepository,
        private MailService $mailService
    ){}

    public function renderDefault(): void
    {
        // Data pro šablonu (výpis nových rezervací + všechny pokoje)
        $this->template->accommodations = $this->accommodationRepository->getAll()
            ->where('Solved = ?', 0)
            ->order('Gdpr DESC');

        $this->template->rooms = $this->roomRepository->getAll();

        // Callback do Latte: kontrola dostupnosti jednoho pokoje v intervalu
        $this->template->isRoomAvailable = function (int $roomId, $from, $to): bool {
            return $this->reservationroomRepository->isRoomAvailable($roomId, $from, $to);
        };

        // Zpracování POST akcí z formuláře
        if ($this->getHttpRequest()->isMethod('POST')) {
            $post = $this->getHttpRequest()->getPost();

            $reservationId = isset($post['reservation_id']) ? (int) $post['reservation_id'] : null;
            $roomIds       = array_map('intval', $post['room_ids'] ?? []);   // ⬅️ checkboxy name="room_ids[]"
            $action        = $post['action'] ?? null;

            if ($reservationId === null) {
                $this->flashMessage('Někde se stala chyba (chybí ID rezervace).');
                $this->redirect('this');
            }

            switch ($action) {
                case 'accept':
                    // 1) Načti rezervaci
                    $reservation = $this->accommodationRepository->getById($reservationId);
                    if (!$reservation) {
                        $this->flashMessage('Rezervace nenalezena.');
                        $this->redirect('this');
                    }

                    if (!$roomIds) {
                        $this->flashMessage('Vyber alespoň jeden pokoj.');
                        $this->redirect('this');
                    }

                    // 2) Počet nocí
                    $nights = $this->accommodationRepository->getNumberOfNights(
                        $reservation->Date_from,
                        $reservation->Date_to
                    );

                    // 3) Načti vybrané pokoje a ověř existenci
                    $roomsSelection = $this->roomRepository->getAll()->where('ID', $roomIds);
                    $rooms = $roomsSelection->fetchAll();
                    if (count($rooms) !== count(array_unique($roomIds))) {
                        $this->flashMessage('Některý z vybraných pokojů neexistuje.');
                        $this->redirect('this');
                    }

                    // 4) Server-side re-check dostupnosti (ochrana proti race conditions)
                    $unavailable = [];
                    foreach ($rooms as $r) {
                        if (!$this->reservationroomRepository->isRoomAvailable(
                            (int) $r->ID, $reservation->Date_from, $reservation->Date_to
                        )) {
                            $unavailable[] = $r->Name;
                        }
                    }
                    if ($unavailable) {
                        $this->flashMessage('Tyto pokoje už nejsou volné: ' . implode(', ', $unavailable));
                        $this->redirect('this');
                    }

                    // 5) Označ rezervaci jako vyřešenou
                    $this->accommodationRepository->update($reservationId, ['Solved' => 1]);

                    // 6) Variabilní symbol (ddmmyyddmmyy)
                    $VS = $reservation->Date_from->format('dmy') . $reservation->Date_to->format('dmy');

                    // 7) Seznam pokojů pro e-mail (jméno + cena / noc)
                    $roomList = [];
                    foreach ($rooms as $room) {
                        $roomList[] = [
                            'name'  => $room->Name,
                            'price' => (float) $room->Price,
                        ];
                    }

                    // 8) Celková cena (cena za všechny pokoje/noc)
                    $totalPrice = array_sum(array_column($roomList, 'price'));
                    // cena celého pobytu všetně poplatků
                    if($reservation->Dog == 1){
                        $Price = ($totalPrice * $nights) + (($nights * $reservation->Person) * 50 ) + ($nights * 150);
                    } else {
                        $Price = ($totalPrice * $nights) + (($nights * $reservation->Person) * 50 );
                    }

                    $this->accommodationRepository->update($reservationId, [
                        'totalPrice' => $Price,
                    ]);



                    // 9) Odeslání e-mailu
                    $subject = 'Pension Kladská - Rezervace přijata';
                    $this->mailService->sendReservationMessage($reservation->Mail, $subject, [
                        'from'       => $reservation->Date_from,
                        'to'         => $reservation->Date_to,
                        'rooms'      => $roomList,
                        'totalPrice' => $totalPrice,
                        'nights'     => $nights,
                        'person'     => $reservation->Person,
                        'VS'         => $VS,
                        'name'       => $reservation->First . ' ' . $reservation->Second,
                        'Dog'        => $reservation->Dog,
                    ]);

                    // 10) Zápis do M:N tabulky (reservation_room)
                    foreach (array_unique($roomIds) as $rid) {
                        $this->reservationroomRepository->insert([
                            'Reservation_id' => (int) $reservation->ID,
                            'Room_id'        => (int) $rid,
                        ]);
                    }

                    $this->flashMessage('Rezervace přijata a pokoje přiřazeny.');
                    $this->redirect('this');

                case 'email':
                    $this->redirect('mail', ['id' => $reservationId]);

                default:
                    $this->flashMessage('Neznámá akce.');
                    $this->redirect('this');
            }
        }
    }

    public function renderCalculation(int $id): void
    {
        // 1) Rezervace
        $reservation = $this->accommodationRepository->getById($id);
        if (!$reservation) {
            $this->flashMessage('Rezervace nenalezena.');
            $this->redirect('Reservation:default');
        }

        // 2) Základní parametry
        $nights       = $this->accommodationRepository->getNumberOfNights($reservation->Date_from, $reservation->Date_to);
        $perPersonFee = 50.0;   // Kč / osoba / noc
        $dogFeePerN   = 150.0;  // Kč / noc
        $rooms        = $this->roomRepository->getAll()->order('Name');

        // dostupnost do šablony
        $this->template->isRoomAvailable = function (int $roomId) use ($reservation): bool {
            return $this->reservationroomRepository->isRoomAvailable(
                $roomId, $reservation->Date_from, $reservation->Date_to
            );
        };

        // 3) Výchozí hodnoty (při prvním načtení už počítej poplatky)
        $selectedRoomIds = [];
        $customPrices    = [];   // [roomId => pricePerNight]
        $countFees       = true; // osoby
        $countDogFee     = (bool) $reservation->Dog; // pes jen pokud v rezervaci je
        $extraItems      = [];   // [['amount'=>float, 'label'=>string], ...]
        $extraFees       = [];   // jen částky pro výpočet
        $extraFeesSum    = 0.0;

        // souhrn
        $summary = [
            'perNightRooms' => 0.0, // součet cen pokojů / noc
            'accomFees'     => 0.0, // osoby + pes dle checkboxů
            'extraFees'     => 0.0, // součet přídavných položek
            'nights'        => $nights,
            'total'         => 0.0, // celkový pobyt
            'items'         => [],  // pokoje s použitou cenou
            'flags'         => [
                'countFees'   => $countFees,
                'countDogFee' => $countDogFee,
            ],
            'extraItems'    => $extraItems,
        ];

        // 4) POST: přepočet / potvrzení
        if ($this->getHttpRequest()->isMethod('POST')) {
            $post            = $this->getHttpRequest()->getPost();
            $action          = $post['calc_action'] ?? 'recalc';

            // výběr pokojů
            $selectedRoomIds = array_map('intval', $post['room_ids'] ?? []);

            // speciální ceny
            $customRaw = $post['custom_prices'] ?? [];
            foreach ($customRaw as $rid => $val) {
                $rid = (int) $rid;
                $v   = (float) str_replace(',', '.', (string) $val);
                if ($v > 0) {
                    $customPrices[$rid] = $v;
                }
            }

            // checkboxy poplatků
            $countFees   = isset($post['count_fees']) && (int)$post['count_fees'] === 1;
            $countDogFee = isset($post['count_dog_fee']) && (int)$post['count_dog_fee'] === 1;

            // přídavné položky (částka + popis)
            $extraFeesAmounts = $post['extra_fees_amount'] ?? [];
            $extraFeesLabels  = $post['extra_fees_label'] ?? [];
            $extraItems = [];
            $extraFees  = [];
            foreach ($extraFeesAmounts as $i => $val) {
                $amount = (float) str_replace(',', '.', (string) $val);
                $label  = trim((string) ($extraFeesLabels[$i] ?? ''));
                if ($amount !== 0.0 || $label !== '') {
                    $extraItems[] = ['amount' => $amount, 'label' => $label];
                    $extraFees[]  = $amount;
                    $extraFeesSum += $amount;// může být i záporné (sleva)

                }
            }

            // validace výběru pokojů
            $sel = $this->roomRepository->getAll()->where('ID', $selectedRoomIds)->fetchAll();
            if (!$sel) {
                $this->flashMessage('Vyber alespoň jeden pokoj.');
            }

            // výpočet cen pokojů / noc
            $perNight = 0.0;
            $items    = [];
            foreach ($sel as $r) {
                $rid  = (int) $r->ID;
                $unit = $customPrices[$rid] ?? (float) $r->Price;
                $perNight += $unit;
                $items[] = [
                    'roomId' => $rid,
                    'name'   => $r->Name,
                    'base'   => (float) $r->Price,
                    'custom' => $customPrices[$rid] ?? null,
                    'used'   => $unit,
                ];
            }

            // poplatky
            $accomFees = 0.0;
            if ($countFees) {
                $accomFees += ($nights * (int) $reservation->Person) * $perPersonFee;
            }
            if ($reservation->Dog && $countDogFee) {
                $accomFees += $nights * $dogFeePerN;
            }

            // výsledný souhrn
            $summary = [
                'perNightRooms' => $perNight,
                'accomFees'     => $accomFees,
                'extraFees'     => $extraFeesSum,
                'nights'        => $nights,
                'total'         => ($perNight * $nights) + $accomFees + ($extraFeesSum*$nights),
                'items'         => $items,
                'flags'         => [
                    'countFees'   => $countFees,
                    'countDogFee' => $countDogFee,
                ],
                'extraItems'    => $extraItems,
            ];
            bdump($summary['extraItems']);

            // 4a) Potvrzení kalkulace (nezávislé na accept)
            if ($action === 'confirm' && $sel) {
                // server-side re-check dostupnosti
                $unavailable = [];
                foreach ($sel as $r) {
                    if (!$this->reservationroomRepository->isRoomAvailable(
                        (int) $r->ID, $reservation->Date_from, $reservation->Date_to
                    )) {
                        $unavailable[] = $r->Name;
                    }
                }
                if ($unavailable) {
                    $this->flashMessage('Tyto pokoje už nejsou volné: ' . implode(', ', $unavailable));
                    $this->redirect('this', ['id' => $id]);
                }

                // ulož vyřešení a celkovou cenu pobytu
                $this->accommodationRepository->update($reservation->ID, [
                    'Solved'     => 1,
                    'totalPrice' => $summary['total'], // ukládáme finální cenu pobytu
                ]);

                // VS + e-mail
                $VS = $reservation->Date_from->format('dmy') . $reservation->Date_to->format('dmy');

                // seznam pokojů do e-mailu s použitou cenou / noc
                $roomList = [];
                foreach ($summary['items'] as $i) {
                    $roomList[] = [
                        'name'  => $i['name'],
                        'price' => $i['used'],
                    ];
                }

                // POZOR: zachovávám původní očekávání šablony mailu – posílám per-night součet do 'totalPrice'
                // Pokud chceš posílat celkový pobyt, vyměň níže $summary['perNightRooms'] za $summary['total'] a uprav mailovou šablonu.
                $subject = 'Pension Kladská - Rezervace přijata';
                $this->mailService->sendCalculationMessage($reservation->Mail, $subject, [
                    'from'       => $reservation->Date_from,
                    'to'         => $reservation->Date_to,
                    'rooms'      => $roomList,
                    'totalPrice' => $summary['perNightRooms'],
                    'allPrice'   => $summary['total'],
                    'nights'     => $nights,
                    'person'     => $reservation->Person,
                    'VS'         => $VS,
                    'name'       => $reservation->First . ' ' . $reservation->Second,
                    'Dog'        => $summary['flags']['countDogFee'],
                    'countFees' => $summary['flags']['countFees'],
                    'extraItems' => $summary['extraItems'],
                ]);

                foreach (array_unique($selectedRoomIds) as $rid) {
                    $this->reservationroomRepository->insert([
                        'Reservation_id' => (int) $reservation->ID,
                        'Room_id'        => (int) $rid,
                    ]);
                }

                $this->flashMessage('Kalkulace potvrzena a pokoje přiřazeny.');
                $this->redirect('Reservation:default');
            }
        }

        // 5) Data do šablony
        $this->template->reservation    = $reservation;
        $this->template->rooms          = $rooms;
        $this->template->selectedRooms  = $selectedRoomIds;
        $this->template->customPrices   = $customPrices;
        $this->template->summary        = $summary;
        $this->template->perPersonFee   = $perPersonFee;
        $this->template->dogFeePerNight = $dogFeePerN;
        $this->template->countFees      = $countFees;
        $this->template->countDogFee    = $countDogFee;
        $this->template->extraItems     = $extraItems;
    }

    public function createComponentEmailForm(): Form
    {
        $form = new Form;

        $form->addEmail('email', 'E-mailová adresa:')
            ->setRequired('Zadejte e-mailovou adresu.')
            ->addRule($form::EMAIL, 'Zadejte platnou e-mailovou adresu.');

        $form->addText('subject', 'Předmět:')
            ->setRequired('Zadejte předmět e-mailu.');

        $form->addTextArea('message', 'Zpráva:');

        $form->addSubmit('send', 'Odeslat e-mail');

        $form->onSuccess[] = function (Form $form, ArrayHash $v): void{

            try {
                // sjednoť konce řádků na "\n"
                $message = Strings::normalizeNewLines((string) $v->message);
                $html = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                // pokud tvoje služba očekává HTML, použij HTML variantu/flag:
                $this->mailService->sendGenericMessage($v->email, $v->subject, $html);

                $this->accommodationRepository->update(
                    (int) $this->getParameter('id'),
                    ['Solved' => 1]
                );

                $this->flashMessage('E-mail byl odeslán.', 'success');
                $this->redirect('Reservation:default');
            } catch (\Throwable $e) {
                $form->addError('E-mail se nepodařilo odeslat. Zkuste to prosím později.');
                // případně log:
                // $this->logger?->error($e->getMessage(), ['exception' => $e]);
            }
        };

        return $form;
    }

    public function renderMail(int $id): void
    {
        $reservation = $this->accommodationRepository->getById($id);
        if (!$reservation) {
            $this->flashMessage('Rezervace nenalezena.');
            $this->redirect('Reservation:default');
        }

        $this['emailForm']->setDefaults([
            'email'     => $reservation->Mail,
            'message'   => 'Vážený pane/paní ' . $reservation->First . ' ' . $reservation->Second,
        ]);

        $this->template->reservation = $reservation;
    }
}