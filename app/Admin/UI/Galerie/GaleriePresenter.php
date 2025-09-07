<?php

namespace App\Admin\UI\Galerie;

use App\Admin\UI\BasePresenter;
use App\Admin\Forms\GalerieForm\GalerieFormFactory;
use App\Core\Repository\GalerieRepository;
use Nette\Application\UI\Form;
use Nette\Utils\Random;
use Nette\Http\FileUpload;

final class GaleriePresenter extends BasePresenter
{
    public function __construct(
        private GalerieRepository   $galerieRepository,
        private GalerieFormFactory  $galerieFormFactory,
    ){}

    /** Výpis všech nahraných fotografií */
    public function renderDefault(): void
    {
        $this->template->pension = $this->galerieRepository->getAll()->where('Category', 'pension')->order('created_at DESC');
        $this->template->restaurace = $this->galerieRepository->getAll()->where('Category', 'restaurace')->order('created_at DESC');
        $this->template->kladska = $this->galerieRepository->getAll()->where('Category', 'kladska')->order('created_at DESC');
    }

    /** nahrání nové fotografie */
    public function renderCreate(): void
    {

    }

    /** Komponenta formuláře */
    protected function createComponentCreateForm(): Form
    {
        $form = $this->galerieFormFactory->create();

        $form->onSuccess[] = function (Form $form, array $values): void {

            $fileName = null; // název, který uložíme do DB

            /** @var FileUpload $images */
            $images = $values['Images'];

            if ($images instanceof FileUpload && $images->isOk() && $images->isImage()) {
                $uploadDir = __DIR__ . '/../../../../www/gallery/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // vždy ukládáme jako .webp
                $fileName = Random::generate(8) . '.webp';
                $filePath = $uploadDir . $fileName;

                // Konverze do WebP pomocí GD
                $tmp = $images->getTemporaryFile();

                // Urči typ obrázku
                $imgType = @exif_imagetype($tmp); // může vrátit false, proto @

                // Vytvoř image resource podle typu
                $src = null;
                if ($imgType === IMAGETYPE_JPEG) {
                    $src = imagecreatefromjpeg($tmp);
                } elseif ($imgType === IMAGETYPE_PNG) {
                    $src = imagecreatefrompng($tmp);
                    // zachovat průhlednost
                    imagepalettetotruecolor($src);
                    imagealphablending($src, true);
                    imagesavealpha($src, true);
                } elseif ($imgType === IMAGETYPE_GIF) {
                    $src = imagecreatefromgif($tmp);
                    // převod palety na truecolor a alfa, ať jde dobře do WebP
                    imagepalettetotruecolor($src);
                    imagealphablending($src, true);
                    imagesavealpha($src, true);
                } else {
                    // nepodporovaný typ – můžeš zvolit jinou reakci
                    $src = null;
                }

                if ($src) {
                    // Ulož jako WebP (kvalita 80 je fajn kompromis)
                    if (!imagewebp($src, $filePath, 80)) {
                        // Fallback: kdyby se uložení nepovedlo, aspoň přesunout originál
                        $fallbackName = Random::generate(8) . '-' . $images->getSanitizedName();
                        $images->move($uploadDir . $fallbackName);
                        $fileName = $fallbackName; // uložíme název originálu
                    }
                    imagedestroy($src);
                } else {
                    // Fallback: typ neznáme – uložíme originál
                    $fallbackName = Random::generate(8) . '-' . $images->getSanitizedName();
                    $images->move($uploadDir . $fallbackName);
                    $fileName = $fallbackName;
                }
            }

            // Uložení do DB – $fileName může být null, když nic nepřišlo
            $this->galerieRepository->insert([
                'title'      => $values['Title'],
                'category'   => $values['Category'],
                'images'     => $fileName,
                'created_at' => new \DateTime(),
            ]);

            $this->flashMessage('Galerie byla úspěšně vytvořena.', 'success');
            $this->redirect('Galerie:default');
        };

        return $form;
    }

    public function handleDelete(int $id): void
    {
        // případně kontrola oprávnění uživatele
        $item = $this->galerieRepository->getById($id);

        // smaž soubor z filesystemu (pokud existuje)
        $path = __DIR__ . '/../../../../www/gallery/' . $item['images'];
        if (is_file($path)) {
            @unlink($path);
        }

        // smaž DB záznam
        $this->galerieRepository->delete($id);

        $this->redirect('default');
    }

}
