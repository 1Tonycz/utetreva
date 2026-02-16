<?php

namespace App\Admin\UI\Table;

use _PHPStan_f9a2208af\Nette\Utils\DateTime;
use App\Admin\UI\BasePresenter;
use App\Core\Repository\TableRepository;
use App\Core\Repository\TableCommentsRepository;
use App\Admin\Forms\GroupForm\GroupFormFactory;
use Nette\Application\UI\Form;
use App\Core\Mail\MailService;

final class TablePresenter extends BasePresenter
{
    public function __construct(
        private TableRepository $tableRepository,
        private TableCommentsRepository $tableCommentsRepository,
        private GroupFormFactory $groupFormFactory,
        private MailService $mailService
    ){}


    public function renderDefault(): void
    {
        if (!$this->getUser()->isAllowed('table', 'default')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $reservation = $this->tableRepository->getAll()
            ->where('Solved', false)
            ->where('Big_group', false)
            ->select('
        *,
        TIME_FORMAT(`Time`, "%H:%i") AS Time_fmt,
        EXISTS(SELECT 1 FROM `table_comments` c WHERE c.`table_id` = `reservation_table`.`ID`) AS Has_note,
        (SELECT COUNT(*) FROM `table_comments` c2 WHERE c2.`table_id` = `reservation_table`.`ID`) AS Comments_cnt
    ')
            ->order('Date ASC');

        $groups = $this->tableRepository->getAll()
            ->where('Solved', false)
            ->where('Big_group', true)
            ->select('
        *,
        TIME_FORMAT(`Time`, "%H:%i") AS Time_fmt,
        EXISTS(SELECT 1 FROM `table_comments` c WHERE c.`table_id` = `reservation_table`.`ID`) AS Has_note,
        (SELECT COUNT(*) FROM `table_comments` c2 WHERE c2.`table_id` = `reservation_table`.`ID`) AS Comments_cnt
    ')
            ->order('Date ASC');


        $this->template->reservation = $reservation;
        $this->template->groups = $groups;
    }

    public function renderDetail(int $id): void
    {
        if (!$this->getUser()->isAllowed('table', 'detail')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $detail = $this->tableRepository->getAll()->where('ID', $id)->select('*, TIME_FORMAT(Time, "%H:%i") AS Time_fmt')->fetch();
        $commmnets = $this->tableCommentsRepository->getAll()->where('table_id', $id)->order('Created_at DESC');

        $this->template->detail = $detail;
        $this->template->comments = $commmnets;
    }

    public function handleSolved(int $id): void
    {
        if (!$this->getUser()->isAllowed('table', 'solved')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $table = $this->tableRepository->getById($id);
        if ($table === null) {
            $this->error('Chyba rezervace nenalezena');
        }
        $to = $table['Mail'];
        $time = $table['Time']->format('%H:%I');
        $params = [
            'time' => $time,
            'date' => $table['Date']
        ];
        $lng = $table['Locale'];
        $this->tableRepository->update($id, ['Solved' => true]);
        if($table['Big_group']){
            $this->flashMessage('Rezervace vyřešena.', 'success');
            $this->redrawControl('reservation');
        } else{
            $this->mailService->sendTableReservation($to, $params, $lng);
            $this->flashMessage('Rezervace vyřešena.', 'success');
            $this->redrawControl('reservation');
        }

    }

    public function handleAddComment(int $id): void
    {
        if (!$this->getUser()->isAllowed('table', 'comments')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }
        $note = trim((string)($this->getHttpRequest()->getPost('note') ?? ''));
        $this->tableCommentsRepository->insert(['table_id' => $id, 'note' => $note, 'created_at' => date('Y-m-d H:i:s')]);

        $this->flashMessage('Poznámka uložena.');
        $this->redirect('this', ['id' => $id]);
    }

    public function handleChangeDates(int $id): void
    {
        if (!$this->getUser()->isAllowed('table', 'change')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        if (!$this->getUser()->isAllowed('home', 'changeDate')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $dateStr = (string) ($this->getHttpRequest()->getPost('date') ?? '');
        $timeStr = (string) ($this->getHttpRequest()->getPost('time') ?? '');

        try {
            $date = new \DateTimeImmutable($dateStr);
            $time   = new \DateTimeImmutable($timeStr);
        } catch (\Throwable $e) {
            $this->flashMessage('Neplatné datum.', 'error');
            $this->redirect('this', ['id' => $id]);
            return;
        }

        $this->tableRepository->update($id, [
            'Date' => $date->format('Y-m-d'),
            'Time' => $time->format('H:i:s'),
        ]);

        $this->flashMessage('Termín upraven.');
        $this->redirect('this', ['id' => $id]);
    }

    public function handleCancelReservation(int $id): void
    {
        if (!$this->getUser()->isAllowed('table', 'cancel')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $reservation = $this->tableRepository->getById($id);
        if (!$reservation) {
            $this->error('Rezervace nenalezena.');
        }

        $this->tableRepository->update($id, ['Solved' => true]);
        $this->redirect('Table:default');

    }

    public function handleGroup(int $id): void
    {
        if (!$this->getUser()->isAllowed('table', 'group')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $this->tableRepository->update($id, ['Big_group' => true]);
        $this->flashMessage('Rezervace byla převedena na skupinovou.');
        $this->redirect('Table:detail', $id);
    }

    public function createComponentGroupForm(): Form
    {
        $form = $this->groupFormFactory->create();
        $form->onSuccess[] = function(Form $form, array $v): void
        {
            if ($v['Tel'] == null){
                $tel = 0;
            } else {
                $tel = $v['Tel'];
            }

            $this->tableRepository->insert([
                'First' => $v['First'],
                'Second' => $v['Second'],
                'Mail' => $v['Mail'],
                'Tel' => $tel,
                'Date' => $v['Date']->format('Y-m-d'),
                'Time' => $v['Time']->format('H:i:s'),
                'Person' => $v['Person'],
                'Note' => $v['Note'],
                'Big_group' => true,
                'Gdpr' => date('Y-m-d H:i:s'),
                'Solved' => false
            ]);
            $this->flashMessage('Skupinová rezervace byla uložena.', 'success');
            $this->redirect('Table:default');
        };
        return $form;
    }

    public function renderCreate(): void
    {
        if (!$this->getUser()->isAllowed('table', 'create_group')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

    }

    public function handleDeleteComment(int $commentId): void
    {
        if (!$this->getUser()->isAllowed('home', 'deleteComment')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $this->tableCommentsRepository->delete($commentId);

        $tableId = $this->getParameter('id');
        $this->flashMessage('Poznámka smazána.');
        $this->redirect('Table:detail', ['id' => $tableId]);
    }



}