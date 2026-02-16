<?php

declare(strict_types=1);

namespace App\Front\UI;


use App\Core\Repository\OpeningExpectionsRepository;
use App\Core\Repository\OpeningHoursRepository;
use App\Front\Components\navbar\NavbarControl;
use App\Front\Components\footer\FooterControl;
use Contributte\Translation\Translator;
use Nette\Http\Session;
use Nette\Application\UI\Presenter;
use Contributte\Translation\LocalesResolvers\Session as TranslatorSessionResolver;

/**
 * @property-read $template
 */


class BasePresenter extends Presenter
{


    public function __construct(
        protected OpeningHoursRepository $openingHoursRepository,
        protected OpeningExpectionsRepository $openingExpectionsRepository,
        protected Translator $translator,
        protected TranslatorSessionResolver $translatorSessionResolver,

    ){
        parent::__construct();
    }

    public function beforeRender(): void
    {
        $this->template->currVer = date("dmHis");
    }

    protected function startup(): void
    {
        parent::startup();

    }



    protected function getBasePath(): string
    {
        return __DIR__ . "/../../../www";
    }
    protected function createComponentNavbar(): NavbarControl
    {
        return new NavbarControl(
            $this->translator,
            $this->translatorSessionResolver,
        );
    }

    protected function createComponentFooter(): FooterControl
    {

        return new FooterControl(
            $this->openingHoursRepository,
            $this->openingExpectionsRepository
        );
    }
}