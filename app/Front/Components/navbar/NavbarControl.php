<?php

declare(strict_types=1);

namespace App\Front\Components\navbar;

use Nette\Application\UI\Control;
use Nette\Bridges\ApplicationLatte\Template;


/**
 * @property-read Template $template
 */

class NavbarControl extends Control
{
    public function render(): void
    {
        $this->template->render(__DIR__ . "/nav.latte");
    }
}