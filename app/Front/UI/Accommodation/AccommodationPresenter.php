<?php

namespace App\Front\UI\Accommodation;

use App\Core\Repository\RoomRepository;
use App\Core\Repository\OpeningExpectionsRepository;
use App\Core\Repository\OpeningHoursRepository;
use App\Front\UI\BasePresenter;
use Contributte\Translation\Translator;
use Nette\Http\Session;
use Contributte\Translation\LocalesResolvers\Session as TranslatorSessionResolver;

final class AccommodationPresenter extends BasePresenter
{
    public function __construct(
        OpeningHoursRepository $openingHoursRepository,
        OpeningExpectionsRepository $openingExpectionsRepository,
        public RoomRepository $roomRepository,
        protected TranslatorSessionResolver $translatorSessionResolver,
        protected Translator $translator,
    ) {
        parent::__construct($openingHoursRepository, $openingExpectionsRepository, $translator, $translatorSessionResolver);
    }

    public function renderDefault(): void
    {
        $doubleRoom = $this->roomRepository->getAll()->where('Name', 'Pokoj 2')->fetch()?->Price;
        $familyRoom = $this->roomRepository->getAll()->where('Name', 'Pokoj 1')->fetch()?->Price;
        $apartment = $this->roomRepository->getAll()->where('Name', 'Apartmán')->fetch()?->Price;
        bdump($doubleRoom);
        bdump($familyRoom);
        bdump($apartment);
        $this->template->doubleRoom = $doubleRoom;
        $this->template->familyRoom = $familyRoom;
        $this->template->apartment = $apartment;
    }

}