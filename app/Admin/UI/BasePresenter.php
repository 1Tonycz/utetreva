<?php

declare(strict_types=1);

namespace App\Admin\UI;


use App\Admin\Components\Navbar\NavbarControl;
use Nette\Application\UI\Presenter;

/**
 * @property-read $template
 */

class BasePresenter extends Presenter
{
    public function beforeRender(): void
    {
        $this->template->currVer = date("dmHis");

    }


    protected function getBasePath(): string
    {
        return __DIR__ . "/../../../www";
    }
    protected function startup(): void
    {
        parent::startup();

        if (!$this->getUser()->isLoggedIn()) {
            $this->flashMessage('Pro přístup do administrace se musíte přihlásit.', 'warning');
            $this->redirect(':Front:Sign:default');
        }
    }

    protected function createComponentNavbar(): NavbarControl
    {
        return new NavbarControl();
    }
}