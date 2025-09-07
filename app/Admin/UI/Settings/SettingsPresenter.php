<?php

declare(strict_types=1);

namespace App\Admin\UI\Settings;

use App\Admin\UI\BasePresenter;
use App\Core\Repository\RoomRepository;
use App\Core\Repository\OpeningHoursRepository;
use App\Core\Repository\OpeningExpectionsRepository;
use Nette\Utils\DateTime;

final class SettingsPresenter extends BasePresenter
{
    public function __construct(
        private RoomRepository $roomRepository,
        private OpeningHoursRepository $openingHoursRepository,
        private OpeningExpectionsRepository $openingExceptionsRepository,
    ) {
        parent::__construct();
    }

    public function renderDefault(): void
    {
        $rooms = $this->roomRepository->getAll();
        $this->template->rooms = $rooms;

        $openingHours = $this->openingHoursRepository->getAll()
            ->select('*, TIME_FORMAT(opens, "%H:%i") AS opens_fmt, TIME_FORMAT(closes, "%H:%i") AS closes_fmt')
            ->order('day_of_week ASC');

        $this->template->openingHours = $openingHours;

        $this->template->exceptions = $this->openingExceptionsRepository->getAll()
            ->select('*, TIME_FORMAT(opens, "%H:%i") AS opens_fmt, TIME_FORMAT(closes, "%H:%i") AS closes_fmt')
            ->order('day ASC, is_closed DESC, opens ASC');
    }

    /** Signal: změna ceny (POST s fields: id, price) */
    public function handleChangePrice(int $id): void
    {
        $http = $this->getHttpRequest();
        $priceNorm = (string) $http->getPost('price', '');

        if ($priceNorm === '' || !is_numeric($priceNorm)) {
            $this->flashMessage('Neplatná cena.', 'error');
            $this->redirect('this');
        }

        $price = round((float) $priceNorm, 2);
        if ($price < 0) {
            $this->flashMessage('Cena nemůže být záporná.', 'error');
            $this->redirect('this');
        }

        try {
            $this->roomRepository->update($id, [
                'Price' => $price,
            ]);
            $this->flashMessage('Cena byla uložena.', 'success');
        } catch (\Throwable $e) {
            $this->flashMessage('Nepodařilo se uložit cenu.', 'error');
        }

        $this->redirect('this');
    }

    /** Signal: přidání pokoje (POST s fields: name, price)
    public function handleAddRoom(): void
    {
        $http  = $this->getHttpRequest();
        $name  = trim((string) $http->getPost('name', ''));
        $priceRaw = (string) $http->getPost('price', '');
        $priceNorm = str_replace([' ', ','], ['', '.'], trim($priceRaw));

        if ($name === '') {
            $this->flashMessage('Zadej název pokoje.', 'error');
            $this->redirect('this');
        }
        if ($priceNorm === '' || !is_numeric($priceNorm)) {
            $this->flashMessage('Neplatná cena.', 'error');
            $this->redirect('this');
        }

        $price = round((float) $priceNorm, 2);
        if ($price < 0) {
            $this->flashMessage('Cena nemůže být záporná.', 'error');
            $this->redirect('this');
        }

        try {
            $this->roomRepository->insert([
                'Name'  => $name,
                'Price' => $price,
            ]);

            $this->flashMessage('Pokoj byl přidán.', 'success');
        } catch (\Throwable $e) {
            $this->flashMessage('Nepodařilo se přidat pokoj.', 'error');
        }
        $this->redirect('this');
    }*/

    public function handleChangeTime(int $id)
    {
        $opens  = $this->getHttpRequest()->getPost('opens');   // '11:00'
        $closes = $this->getHttpRequest()->getPost('closes');  // '22:00'
        $overnight = (int) ($closes <= $opens);                // přes půlnoc
        $this->openingHoursRepository->update($id, [
            'opens' => strlen($opens)===5 ? $opens.':00' : $opens,
            'closes'=> strlen($closes)===5 ? $closes.':00' : $closes,
            'overnight' => $overnight,
        ]);
    }

    public function handleAddException(): void
    {
        $r = $this->getHttpRequest();
        $day = (string) $r->getPost('day');             // YYYY-MM-DD
        $isClosed = (bool) $r->getPost('is_closed');
        $opens = $r->getPost('opens') ?: null;          // HH:MM
        $closes = $r->getPost('closes') ?: null;
        $overnight = (bool) $r->getPost('overnight');
        $note = (string) ($r->getPost('note') ?? '');

        if (!$isClosed) {
            if ($opens && strlen($opens) === 5)  $opens  .= ':00';
            if ($closes && strlen($closes) === 5) $closes .= ':00';
            $overnight = $opens && $closes ? (strcmp($closes, $opens) <= 0) : false;
        } else {
            $opens = $closes = null;
            $overnight = false;
        }

        $this->openingExceptionsRepository->insert([
            'day' => $day,
            'is_closed' => $isClosed ? 1 : 0,
            'opens' => $opens,
            'closes' => $closes,
            'overnight' => $overnight ? 1 : 0,
            'note' => $note !== '' ? $note : null,
        ]);

        $this->flashMessage('Výjimka přidána.', 'success');
        $this->redirect('this');
    }

    public function handleUpdateException(int $id): void
    {
        $r = $this->getHttpRequest();
        $day = (string) $r->getPost('day');
        $isClosed = (bool) $r->getPost('is_closed');
        $opens = $r->getPost('opens') ?: null;
        $closes = $r->getPost('closes') ?: null;
        $overnight = (bool) $r->getPost('overnight');
        $note = (string) ($r->getPost('note') ?? '');

        if (!$isClosed) {
            if ($opens && strlen($opens) === 5)  $opens  .= ':00';
            if ($closes && strlen($closes) === 5) $closes .= ':00';
            $overnight = $opens && $closes ? (strcmp($closes, $opens) <= 0) : false;
        } else {
            $opens = $closes = null;
            $overnight = false;
        }

        $this->openingExceptionsRepository->update($id, [
            'day' => $day,
            'is_closed' => $isClosed ? 1 : 0,
            'opens' => $opens,
            'closes' => $closes,
            'overnight' => $overnight ? 1 : 0,
            'note' => $note !== '' ? $note : null,
        ]);

        $this->flashMessage('Výjimka upravena.', 'success');
        $this->redirect('this');
    }

    public function handleDeleteException(int $id): void
    {
        $this->openingExceptionsRepository->delete($id);
        $this->flashMessage('Výjimka smazána.', 'success');
        $this->redirect('this');
    }

}
