<?php

namespace App\Front\UI\Menu;

use App\Core\Repository\FoodRepository;
use App\Core\Repository\OpeningExpectionsRepository;
use App\Core\Repository\OpeningHoursRepository;
use App\Front\UI\BasePresenter;
use Contributte\Translation\Translator;
use Nette\Http\Session;

final class MenuPresenter extends BasePresenter
{
    /** @persistent */
    public string $category = 'predkrmy';

    public function __construct(
        OpeningHoursRepository $openingHoursRepository,
        OpeningExpectionsRepository $openingExpectionsRepository,
        public FoodRepository $foodRepository,
        protected Translator $translator,
        protected Session $session

    ) {
        parent::__construct($openingHoursRepository, $openingExpectionsRepository, $translator, $session);
    }

    public function renderDefault(): void
    {
        $locale = $this->translator->getLocale();
        $this->template->locale = $locale;
        $this->template->selectedCategory = $this->category;
        $this->template->menuItems = $this->getMenuItems($this->mapCategory($this->category));
    }

    public function handleChangeCategory(string $category): void
    {
        // uloží se do URL (persistent) + do šablony
        $this->category = $category;
        $this->template->selectedCategory = $category;

        $menuItems = $this->getMenuItems($this->mapCategory($category));
        $this->template->menuItems = $menuItems;

        // bdump($menuItems); // necháš si když chceš

        if ($this->isAjax()) {
            $this->redrawControl('menuContent');
            $this->redrawControl('menuTabs');
        } else {
            $this->redirect('this'); // persistent param zůstane v URL
        }
    }

    private function getMenuItems(array $categories): \Nette\Database\Table\Selection
    {
        return $this->foodRepository->getAll()->where('Category', $categories);
    }

    private function mapCategory(string $c): array
    {
        return match ($c) {
            'predkrmy' => [1, 2],
            'hlavni'   => [3, 4, 5, 6, 8, 9],
            'dezerty'  => [7],
            default    => [10, 11, 12], // napoje apod.
        };
    }
}
