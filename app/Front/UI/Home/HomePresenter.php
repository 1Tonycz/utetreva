<?php

declare(strict_types=1);

namespace App\Front\UI\Home;

use App\Front\UI\BasePresenter;
use App\Core\Repository\OpeningExpectionsRepository;
use App\Core\Repository\OpeningHoursRepository;
use App\Core\Repository\AccommodationRepository;
use App\Core\Repository\EventRepository;
use Contributte\Translation\Translator;
use Contributte\Translation\LocalesResolvers\Session as TranslatorSessionResolver;
use Nette\Http\Session;


final class HomePresenter extends BasePresenter
{
    public function __construct(
        OpeningHoursRepository $openingHoursRepository,
        OpeningExpectionsRepository $openingExpectionsRepository,
        protected Translator $translator,
        protected TranslatorSessionResolver $translatorSessionResolver,
        public AccommodationRepository $accommodationRepository,
        public EventRepository $eventRepository,
    ) {
        parent::__construct($openingHoursRepository, $openingExpectionsRepository, $translator, $translatorSessionResolver);
    }

    public function renderDefault(): void
    {

        $this->template->event = $this->eventRepository->getAll();
        $this->template->openingHours = $this->openingHoursRepository->getAll()->order('day_of_week ASC');
        $locale = $this->translator->getLocale();
        $this->template->locale = $locale;
    }

    public function renderUnsubscribe(): void
    {
        $this->redirect('Home:default');
    }

    public function actionUnsubscribe(string $token): void
    {
        $this->autoCanonicalize = false;

        $row = $this->accommodationRepository->getAll()
            ->where('unsubscribe_token', $token)
            ->fetch();

        if (!$row) {
            $this->template->status = 'invalid';
            $this->flashMessage($this->translator->translate('ui.flashmessage.type1'), 'error');
            return;
        }

        if ((int) $row->Newsletter === 1) {
            $this->accommodationRepository->update($row->ID, [
                'Newsletter' => 0,
            ]);
            $this->template->justUnsubscribed = true;
            $this->flashMessage($this->translator->translate('ui.flashmessage.type2'), 'success');
        } else {
            $this->template->justUnsubscribed = false;
            $this->flashMessage($this->translator->translate('ui.flashmessage.type3'), 'error');
        }

        $this->template->status = 'success';
        $this->template->email = $row->Mail;
    }

}
