<?php

namespace App\Front\UI\Accommodation;

use App\Core\Repository\RoomRepository;
use App\Core\Repository\OpeningExpectionsRepository;
use App\Core\Repository\OpeningHoursRepository;
use App\Front\UI\BasePresenter;

final class AccommodationPresenter extends BasePresenter
{
    public function __construct(
        OpeningHoursRepository $openingHoursRepository,
        OpeningExpectionsRepository $openingExpectionsRepository,
        public RoomRepository $roomRepository
    ) {
        parent::__construct($openingHoursRepository, $openingExpectionsRepository, $roomRepository);
    }

    public function renderDefault(): void
    {
        $this->template->doubleRoom = $this->roomRepository->getAll()->where('Name', 'Pokoj 2');
        $this->template->familyRoom = $this->roomRepository->getAll()->where('Name', 'Pokoj 1');
        $this->template->apartment = $this->roomRepository->getAll()->where('Name', 'Apartmán');
    }

}