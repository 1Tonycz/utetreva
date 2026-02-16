<?php

namespace App\Admin\UI\Event;

use App\Admin\UI\BasePresenter;
use App\Core\Repository\EventRepository;
use Nette\Application\UI\Form;
use Nette\Utils\DateTime;

final class EventPresenter extends BasePresenter
{
    private const SINGLE_ID = 1; // jediný záznam

    public function __construct(
        public EventRepository $eventRepository,
    ) {
        parent::__construct();
    }

    public function renderDefault(): void
    {
        if (!$this->getUser()->isAllowed('event', 'default')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $row = $this->eventRepository->getById(self::SINGLE_ID);
        $this->template->event = $row ? $row->toArray() : null;

        if ($row) {
            /** @var Form $form */
            $form = $this['eventForm'];
            $form['title_cs']->setDefaultValue($row['Title_cs']);
            $form['title_de']->setDefaultValue($row['Title_de']);
            $form['title_en']->setDefaultValue($row['Title_en']);
            $form['title_ru']->setDefaultValue($row['Title_ru']);

            $form['description_cs']->setDefaultValue($row['Description_cs']);
            $form['description_de']->setDefaultValue($row['Description_de']);
            $form['description_en']->setDefaultValue($row['Description_en']);
            $form['description_ru']->setDefaultValue($row['Description_ru']);

            $form['date_from']->setDefaultValue(DateTime::from($row['Date_from'])->format('Y-m-d'));
            $form['date_to']->setDefaultValue(DateTime::from($row['Date_to'])->format('Y-m-d'));
        }
    }

    protected function createComponentEventForm(): Form
    {
        $form = new Form();
        $form->addProtection();

        // Titles
        $form->addText('title_cs', 'Nadpis (cs)')->setRequired()->setMaxLength(255);
        $form->addText('title_de', 'Nadpis (de)')->setRequired()->setMaxLength(255);
        $form->addText('title_en', 'Nadpis (en)')->setRequired()->setMaxLength(255);
        $form->addText('title_ru', 'Nadpis (ru)')->setRequired()->setMaxLength(255);

        // Descriptions
        $form->addTextArea('description_cs', 'Popis (cs)')->setRequired()->setMaxLength(255)->setHtmlAttribute('rows', 3);
        $form->addTextArea('description_de', 'Popis (de)')->setRequired()->setMaxLength(255)->setHtmlAttribute('rows', 3);
        $form->addTextArea('description_en', 'Popis (en)')->setRequired()->setMaxLength(255)->setHtmlAttribute('rows', 3);
        $form->addTextArea('description_ru', 'Popis (en)')->setRequired()->setMaxLength(255)->setHtmlAttribute('rows', 3);

        // Date range
        $form->addText('date_from', 'Datum od')->setRequired()->setHtmlType('date');
        $form->addText('date_to', 'Datum do')->setRequired()->setHtmlType('date');

        // Optional image
        $form->addUpload('image', 'Obrázek (nepovinné)')
            ->addRule(Form::IMAGE, 'Obrázek musí být JPEG/PNG/WebP.');

        $form->addSubmit('save', 'Uložit');

        $form->onSuccess[] = function (Form $form, array $v): void {
            if (!$this->getUser()->isAllowed('event', 'default')) {
                $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
            }

            try {
                $from = DateTime::from($v['date_from'])->format('Y-m-d');
                $to   = DateTime::from($v['date_to'])->format('Y-m-d');
            } catch (\Throwable) {
                $form->addError('Neplatné datum.');
                return;
            }
            if ($from > $to) {
                $form->addError('Datum do musí být stejné nebo pozdější než datum od.');
                return;
            }

            $payload = [
                'ID' => self::SINGLE_ID,
                'Title_cs' => trim($v['title_cs']),
                'Title_de' => trim($v['title_de']),
                'Title_en' => trim($v['title_en']),
                'Title_ru' => trim($v['title_ru']),
                'Description_cs' => trim($v['description_cs']),
                'Description_de' => trim($v['description_de']),
                'Description_en' => trim($v['description_en']),
                'Description_ru' => trim($v['description_ru']),
                'Date_from' => $from,
                'Date_to'   => $to,
            ];

            $existing = $this->eventRepository->getById(self::SINGLE_ID);

            // Optional image upload
            $upload = $v['image'];
            if ($upload && $upload->isOk()) {
                if (!$upload->isImage()) {
                    $form->addError('Nahraný soubor není obrázek.');
                    return;
                }
                $ext = strtolower(pathinfo($upload->getSanitizedName(), PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) {
                    $form->addError('Povoleny jsou pouze JPG, PNG, WebP.');
                    return;
                }

                $dir = __DIR__ . '/../../../../www/css/img/event';
                if (!is_dir($dir)) @mkdir($dir, 0775, true);

                $filename = 'event-' . date('Ymd-His') . '.' . $ext;
                $path = $dir . '/' . $filename;
                $upload->move($path);

                // remove old file if exists
                if ($existing && (int)$existing['Image'] === 1 && !empty($existing['Image_name'])) {
                    $old = $dir . '/' . $existing['Image_name'];
                    if (is_file($old)) @unlink($old);
                }

                $payload['Image'] = 1;
                $payload['Image_name'] = $filename;
            } else {
                // keep existing image flags
                if ($existing) {
                    $payload['Image'] = (int)$existing['Image'];
                    $payload['Image_name'] = $existing['Image_name'];
                } else {
                    $payload['Image'] = 0;
                    $payload['Image_name'] = null;
                }
            }

            if ($existing) {
                $this->eventRepository->update(self::SINGLE_ID, $payload);
            } else {
                $this->eventRepository->insert($payload);
            }

            $this->flashMessage('Akce byla uložena.', 'success');
            $this->redirect('this');
        };

        return $form;
    }

    public function handleDelete(): void
    {
        if (!$this->getUser()->isAllowed('event', 'default')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $row = $this->eventRepository->getById(self::SINGLE_ID);
        if ($row) {
            if ((int)$row['Image'] === 1 && !empty($row['Image_name'])) {
                $path = __DIR__ . '/../../../../www/css/img/event/' . $row['Image_name'];
                if (is_file($path)) @unlink($path);
            }
            $this->eventRepository->delete(self::SINGLE_ID);
            $this->flashMessage('Akce byla smazána.', 'info');
        }
        $this->redirect('this');
    }

    public function handleDeleteImage(): void
    {
        if (!$this->getUser()->isAllowed('event', 'default')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $row = $this->eventRepository->getById(self::SINGLE_ID);
        if ($row && (int)$row['Image'] === 1 && !empty($row['Image_name'])) {
            $path = __DIR__ . '/../../../../www/css/img/event/' . $row['Image_name'];
            if (is_file($path)) @unlink($path);

            $this->eventRepository->update(self::SINGLE_ID, [
                'Image' => 0,
                'Image_name' => null,
            ]);
            $this->flashMessage('Obrázek byl odstraněn.', 'info');
        }
        $this->redirect('this');
    }
}
