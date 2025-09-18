<?php

declare(strict_types=1);

namespace App\Front\UI\Home;

use App\Front\UI\BasePresenter;
use App\Core\Repository\FoodRepository;
use App\Core\Repository\OpeningExpectionsRepository;
use App\Core\Repository\OpeningHoursRepository;
use Contributte\Translation\Translator;
use Nette;
use Nette\Http\Session;


final class HomePresenter extends BasePresenter
{
    public function __construct(
        public FoodRepository $foodRepository,
        OpeningHoursRepository $openingHoursRepository,
        OpeningExpectionsRepository $openingExpectionsRepository,
        protected Translator $translator,
        protected Session $session
    ) {
        parent::__construct($openingHoursRepository, $openingExpectionsRepository, $translator, $session, $foodRepository);
    }

    public function renderDefault(): void
    {
        $this->template->openingHours = $this->openingHoursRepository->getAll()->order('day_of_week ASC');
        $locale = $this->translator->getLocale();
        $this->template->foods = $this->foodRepository
            ->getAll()
            ->where('Category', 4)
            ->where('Archived', 0)
            ->order('Price DESC')
            ->limit(6);
        $this->template->locale = $locale;
    }
}
