<?php

declare(strict_types=1);

namespace App\Front\Components\navbar;

use Nette\Application\UI\Control;
use Nette\Http\Session;
use Nette;
use Contributte\Translation\Translator;
use Nette\Bridges\ApplicationLatte\Template;


/**
 * @property-read Template $template
 */

final class NavbarControl extends Control
{
    use Nette\SmartObject;

    private const SESSION_LOCALE = 'locale';
    /** @var string[] povolené jazyky */
    private array $allowed = ['cs','en','de','ru'];

    public function __construct(
        private Translator $translator,
        private Session $session,
    ) {}


    public function render(): void
    {
        $this->template->currentLocale = $this->translator->getLocale();
        $this->template->render(__DIR__ . "/nav.latte");
    }

    public function handleSetLocale(string $locale): void
    {
        if (!in_array($locale, $this->allowed, true)) {
            $this->getPresenter()->error('Unsupported locale');
        }

        // ulož do session
        $sec = $this->session->getSection(self::SESSION_LOCALE);
        $sec->code = $locale;

        // projeví se hned i v tomto requestu
        $this->translator->setLocale($locale);

        // AJAX vs. full reload
        $p = $this->getPresenter();
        if ($p->isAjax()) {

            if (method_exists($p, 'redrawControl')) {
                $p->redrawControl('navbar');
                $p->redrawControl('content');// uprav podle svých snippetů
                $p->redrawControl('footer');
            }
        } else {
            $p->redirect('this');
        }
    }
}