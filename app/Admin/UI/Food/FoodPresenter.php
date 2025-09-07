<?php

namespace App\Admin\UI\Food;

use App\Admin\UI\BasePresenter;
use Nette\Application\UI\Form;
use App\Core\Repository\FoodRepository;
use App\Admin\Forms\FoodForm\FoodFormFactory;


class FoodPresenter extends BasePresenter
{

    public function __construct(
        private readonly FoodRepository $foodRepository,
        private readonly FoodFormFactory $foodFormFactory
    )
    {
    }

    public const Category = [
        1 => 'Předkrmy',
        2 => 'Polévky',
        3 => 'Ryby',
        4 => 'Zvěřinové speciality',
        5 => 'Hlavní jídla',
        6 => 'Saláty',
        7 => 'Dezerty',
        8 => 'Přílohy',
        9 => 'Omáčky',
        10 => 'Nealkoholické nápoje',
        11 => 'Alkoholické nápoje',
        12 => 'Vinný list'
    ];

    public function renderDefault(int $cat = 1): void
    {

        $cat = array_key_exists($cat, self::Category) ? $cat : 1;
        $this->template->foods = $this->foodRepository->getAll()->where('Category', $cat)->where('archived', 0);
        $this->template->archived = $this->foodRepository->getAll()->where('Category', $cat)->where('archived', 1);

        $this->template->cat = $cat;
        $this->template->cats = self::Category;

    }

    public function handleSelectCat(int $cat): void
    {
        $this->renderDefault($cat);
        $this->redrawControl('foods');
        $this->redrawControl('tabs');
    }

    public function renderCreate(): void
    {

    }

    protected function createComponentFoodForm(): Form
    {
        $form = $this->foodFormFactory->create();
        $form->onSuccess[] = function ($form, $data) {
            $this->foodRepository->insert([
                'Name_cs' => $data->Name_cs,
                'Name_de' => $data->Name_de,
                'Name_en' => $data->Name_en,
                'Name_ru' => $data->Name_ru,
                'Description_cs' => $data->Description_cs,
                'Description_de' => $data->Description_de,
                'Description_en' => $data->Description_en,
                'Description_ru' => $data->Description_ru,
                'Price' => $data->Price,
                'Category' => $data->Category
            ]);
            $this->flashMessage('Úspěšně přidáno.', 'success');
            $this->redirect('this');
        };
        return $form;
    }

    public function handleArchive(int $id): void
    {
        $food = $this->foodRepository->getById($id);
        if ($food) {
            $this->foodRepository->update($id, ['archived' => 1]);
            $this->flashMessage('Jídlo bylo úspěšně archivováno.', 'success');
        } else {
            $this->flashMessage('Jídlo nebylo nalezeno.', 'error');
        }
        $this->redirect('Food:default', ['cat' => $food->Category]);
    }

    public function handleUnarchive(int $id): void
    {
        $food = $this->foodRepository->getById($id);
        if ($food) {
            $this->foodRepository->update($id, ['archived' => 0]);
            $this->flashMessage('Jídlo bylo úspěšně obnoveno.', 'success');
        } else {
            $this->flashMessage('Jídlo nebylo nalezeno.', 'error');
        }
        $this->redirect('Food:default', ['cat' => $food->Category]);
    }

    public function handleDelete(int $id): void
    {
        $food = $this->foodRepository->getById($id);
        if ($food) {
            $this->foodRepository->delete($id);
            $this->flashMessage('Jídlo bylo úspěšně smazáno.', 'success');
        } else {
            $this->flashMessage('Jídlo nebylo nalezeno.', 'error');
        }
        $this->redirect('Food:default', ['cat' => $food->Category]);
    }

    public function handleEditFood(): void
    {
        // 1) jen POST
        $req = $this->getHttpRequest();
        if (!$req->isMethod('POST')) {
            $this->error('Method not allowed');
        }

        $post = $req->getPost();

        // 2) načtení a základní validace vstupů
        $id       = (int)($post['id'] ?? 0);
        $name     = trim((string)($post['name'] ?? ''));
        $priceRaw = (string)($post['price'] ?? '0');
        $category = (int)($post['category'] ?? 0);
        $archived = isset($post['archived']) ? 1 : 0;

        if ($id <= 0) {
            $this->flashMessage('Chybí ID položky.', 'error');
            $this->redirect('this');
            return;
        }
        if ($name === '') {
            $this->flashMessage('Zadejte název jídla.', 'error');
            $this->redirect('this');
            return;
        }

        // cena – povolíme celá čísla; pokud chceš i desetinné, změň na floatval
        if (!is_numeric($priceRaw)) {
            $this->flashMessage('Cena musí být číslo.', 'error');
            $this->redirect('this');
            return;
        }
        $price = (int)round((float)$priceRaw);
        if ($price < 0) {
            $this->flashMessage('Cena nesmí být záporná.', 'error');
            $this->redirect('this');
            return;
        }

        if ($category <= 0) {
            $this->flashMessage('Vyberte platnou kategorii.', 'error');
            $this->redirect('this');
            return;
        }

        // 3) existence položky
        $row = $this->foodRepository->getById($id);
        if (!$row) {
            $this->flashMessage('Položka nenalezena.', 'error');
            $this->redirect('this');
            return;
        }

        // 4) update v DB (pozor na názvy sloupců podle schématu)
        $this->foodRepository->update($id, [
            'Name'     => $name,
            'Price'    => $price,
            'Category' => $category,
            'Archived' => $archived,
        ]);

        $this->flashMessage('Položka byla upravena.', 'success');

        // 5) AJAX vs non-AJAX odpověď
        if ($this->isAjax()) {
            // Aktualizuj data pro aktuální kategorii a překresli snippety
            $catParam = (int)($this->getParameter('cat') ?? $category);
            // Pokud máš renderList($cat), zavolej ho pro znovunaplnění $foods/$archived:
            if (method_exists($this, 'renderList')) {
                $this->renderList($catParam);
            }
            $this->redrawControl('foods');
            $this->redrawControl('tabs');
            // Pokud máš snippet na flash zprávy, překresli i ten:
            if (method_exists($this, 'redrawControl')) {
                $this->redrawControl('flash');
            }
            return;
        }

        // Full reload
        $this->redirect('this');
    }


}