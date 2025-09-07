<?php

declare(strict_types=1);

namespace App\Front\UI\Home;

use App\Front\UI\BasePresenter;
use App\Core\Repository\FoodRepository;
use App\Core\Repository\OpeningExpectionsRepository;
use App\Core\Repository\OpeningHoursRepository;
use Contributte\Translation\Translator;
use Nette;


final class HomePresenter extends BasePresenter
{
    public function __construct(
        private FoodRepository $foodRepository,
        OpeningHoursRepository $openingHoursRepository,
        OpeningExpectionsRepository $openingExpectionsRepository,
        private Translator $translator
    ) {
        parent::__construct($openingHoursRepository, $openingExpectionsRepository);
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
