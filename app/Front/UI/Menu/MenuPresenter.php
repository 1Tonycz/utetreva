<?php

namespace App\Front\UI\Menu;

use App\Core\Repository\FoodRepository;
use App\Core\Repository\OpeningExpectionsRepository;
use App\Core\Repository\OpeningHoursRepository;
use App\Front\UI\BasePresenter;
use Contributte\Translation\Translator;
use Contributte\Translation\LocalesResolvers\Session as TranslatorSessionResolver;

final class MenuPresenter extends BasePresenter
{
    /** @persistent */
    public string $category = 'predkrmy';

    public function __construct(
        OpeningHoursRepository $openingHoursRepository,
        OpeningExpectionsRepository $openingExpectionsRepository,
        public FoodRepository $foodRepository,
        protected Translator $translator,
        protected TranslatorSessionResolver $translatorSessionResolver,

    ) {
        parent::__construct($openingHoursRepository, $openingExpectionsRepository, $translator, $translatorSessionResolver);
    }

    public function renderDefault(): void
    {
        $locale = $this->translator->getLocale();
        $this->template->locale = $locale;
        $this->template->selectedCategory = $this->category;

        // Získání všech položek menu rozdělených podle kategorií
        $menuItems = $this->getMenuItems($this->mapCategory($this->category));

        // Rozdělení položek do kategorií podle jejich ID
        $categories = [];
        foreach ($menuItems as $item) {
            $categoryName = $this->getCategoryName($item->Category);
            $categories[$categoryName][] = $item;
        }

        // Předání rozdělených kategorií do šablony
        $this->template->categories = $categories;
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

    private function getCategoryName(int $categoryId): string
    {
        switch ($categoryId) {
            case 1:
                return 'Polévky';
            case 2:
                return 'Předkrmy';
            case 3:
                return 'Ryby';
            case 4:
                return 'Zvěřinové speciality';
            case 5:
                return 'Hlavní jídla';
            case 6:
                return 'Saláty';
            case 7:
                return 'Dezerty';
            case 8:
                return 'Přílohy';
            case 9:
                return 'Omáčky';
            case 10:
                return 'Nealkoholické nápoje';
            case 11:
                return 'Alkoholické nápoje';
            case 12:
                return 'Vinný list';
            default:
                return 'Ostatní';
        }
    }
}
