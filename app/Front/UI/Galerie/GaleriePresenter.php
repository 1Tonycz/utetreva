<?php

namespace App\Front\UI\Galerie;

use App\Core\Repository\FoodRepository;
use App\Core\Repository\OpeningExpectionsRepository;
use App\Core\Repository\OpeningHoursRepository;
use App\Front\UI\BasePresenter;
use App\Core\Repository\GalerieRepository;
use Contributte\Translation\Translator;
use Nette\Http\Session;

final class GaleriePresenter extends BasePresenter
{
    public function __construct(
        OpeningHoursRepository $openingHoursRepository,
        OpeningExpectionsRepository $openingExpectionsRepository,
        public GalerieRepository $galerieRepository,
        protected Translator $translator,
        protected Session $session
    ) {
        parent::__construct($openingHoursRepository, $openingExpectionsRepository, $translator, $session);
    }
    public function renderDefault(): void
    {
        $restaurant = $this->galerieRepository->getAll()->where('category', 'restaurace');
        $pension = $this->galerieRepository->getAll()->where('category', 'pension');
        $kladska = $this->galerieRepository->getAll()->where('category', 'kladska');
        $this->template->restaurant = $restaurant;
        $this->template->pension = $pension;
        $this->template->kladska = $kladska;
    }

}