<?php

declare(strict_types=1);

namespace App\Front\UI;


use App\Core\Repository\OpeningExpectionsRepository;
use App\Core\Repository\OpeningHoursRepository;
use App\Front\Components\navbar\NavbarControl;
use App\Front\Components\footer\FooterControl;
use Nette\Application\UI\Presenter;

/**
 * @property-read $template
 */


class BasePresenter extends Presenter
{
    public function __construct(
        protected OpeningHoursRepository $openingHoursRepository,
        protected OpeningExpectionsRepository $openingExpectionsRepository,

    ){
        parent::__construct();
    }

    public function beforeRender(): void
    {
        $this->template->currVer = date("dmHis");
    }

    public $locale;

    protected function startup() {
        $this->template->locale = $this->locale;
        parent::startup();
    }


    protected function getBasePath(): string
    {
        return __DIR__ . "/../../../www";
    }
    protected function createComponentNavbar(): NavbarControl
    {
        return new NavbarControl(
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