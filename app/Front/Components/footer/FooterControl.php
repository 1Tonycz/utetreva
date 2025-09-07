<?php

declare(strict_types=1);

namespace App\Front\Components\footer;

use Nette\Application\UI\Control;
use App\Core\Repository\OpeningHoursRepository;
use App\Core\Repository\OpeningExpectionsRepository;
use Nette\Bridges\ApplicationLatte\Template;

/**
 * @property-read Template $template
 */

class FooterControl extends Control
{
    public function __construct(
        private OpeningHoursRepository $openingHoursRepository,
        private OpeningExpectionsRepository $openingExpectionsRepository
    ) {}

    public function render(): void
    {
        $openingHours = $this->openingHoursRepository->getAll()
            ->select('*, TIME_FORMAT(opens, "%H:%i") AS opens_fmt, TIME_FORMAT(closes, "%H:%i") AS closes_fmt')
            ->order('day_of_week ASC');
        $openingExceptions = $this->openingExpectionsRepository->getAll()
            ->select('*, TIME_FORMAT(opens, "%H:%i") AS opens_fmt, TIME_FORMAT(closes, "%H:%i") AS closes_fmt')
            ->order('day ASC, is_closed DESC, opens ASC');

        $this->template->setParameters([
            'openingHours'      => $openingHours,
            'openingExceptions' => $openingExceptions,
        ]);

        $this->template->render(__DIR__ . '/footer.latte');
    }
}