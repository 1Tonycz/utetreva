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
        if (!$this->getUser()->isAllowed('food', 'default')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

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
        if (!$this->getUser()->isAllowed('food', 'create')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

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
        if (!$this->getUser()->isAllowed('food', 'archive')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

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
        if (!$this->getUser()->isAllowed('food', 'unarchive')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

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
        if (!$this->getUser()->isAllowed('food', 'delete')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

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
        if (!$this->getUser()->isAllowed('food', 'edit')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $req = $this->getHttpRequest();
        if (!$req->isMethod('POST')) {
            $this->error('Method not allowed');
        }

        $post = $req->getPost();

        // 2) načtení a základní validace vstupů
        $id       = (int)($post['id'] ?? 0);

        // názvy ve všech jazycích
        $nameCs   = trim((string)($post['name_cs'] ?? ''));
        $nameDe   = trim((string)($post['name_de'] ?? ''));
        $nameEn   = trim((string)($post['name_en'] ?? ''));
        $nameRu   = trim((string)($post['name_ru'] ?? ''));

        // popisy ve všech jazycích
        $descCs   = trim((string)($post['description_cs'] ?? ''));
        $descDe   = trim((string)($post['description_de'] ?? ''));
        $descEn   = trim((string)($post['description_en'] ?? ''));
        $descRu   = trim((string)($post['description_ru'] ?? ''));

        // ostatní pole
        $priceRaw = (string)($post['price'] ?? '0');
        $category = (int)($post['category'] ?? 0);
        $archived = isset($post['archived']) ? 1 : 0;

        if ($id <= 0) {
            $this->flashMessage('Chybí ID položky.', 'error');
            $this->redirect('this');
            return;
        }

        // alespoň jeden název musí být vyplněn (doporučeně cs)
        if ($nameCs === '' && $nameDe === '' && $nameEn === '' && $nameRu === '') {
            $this->flashMessage('Vyplňte alespoň jeden název (doporučeně česky).', 'error');
            $this->redirect('this');
            return;
        }

        // cena
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

        // kategorie
        if ($category <= 0 || !array_key_exists($category, self::Category)) {
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

        // 4) příprava dat k update — aktualizujeme názvy i popisy
        $updateData = [
            'Name_cs'        => $nameCs,
            'Name_de'        => $nameDe,
            'Name_en'        => $nameEn,
            'Name_ru'        => $nameRu,
            'Description_cs' => $descCs,
            'Description_de' => $descDe,
            'Description_en' => $descEn,
            'Description_ru' => $descRu,
            'Price'          => $price,
            'Category'       => $category,
            'Archived'       => $archived,
        ];

        // 5) update v DB
        $this->foodRepository->update($id, $updateData);

        $this->flashMessage('Položka byla upravena.', 'success');

        // 6) AJAX vs non-AJAX odpověď
        if ($this->isAjax()) {
            $catParam = (int)($this->getParameter('cat') ?? $category);
            if (method_exists($this, 'renderList')) {
                $this->renderList($catParam);
            } else {
                // fallback – znovunačti data jako v renderDefault
                $this->template->foods    = $this->foodRepository->getAll()->where('Category', $catParam)->where('archived', 0);
                $this->template->archived = $this->foodRepository->getAll()->where('Category', $catParam)->where('archived', 1);
                $this->template->cat      = $catParam;
                $this->template->cats     = self::Category;
            }
            $this->redrawControl('foods');
            $this->redrawControl('tabs');
            if (method_exists($this, 'redrawControl')) {
                $this->redrawControl('flash');
            }
            return;
        }

        // Full reload
        $this->redirect('this');
    }




}