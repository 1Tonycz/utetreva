<?php

namespace App\Admin\UI\Voucher;

use App\Admin\UI\BasePresenter;
use App\Core\Repository\VoucherRepository;
use App\Core\Repository\VoucherAmountHistoryRepository;
use App\Admin\Forms\VoucherForm\VariablSymbolFormFactory;
use App\Admin\Forms\VoucherForm\VoucherCreateFormFactory;
use Nette\Application\UI\Form;
use App\Core\Mail\MailService;
use setasign\Fpdi\Fpdi;


final class VoucherPresenter extends BasePresenter
{
    public function __construct(
        protected VoucherRepository $voucherRepository,
        protected VariablSymbolFormFactory $variablSymbolFormFactory,
        protected MailService $mailService,
        protected VoucherAmountHistoryRepository $voucherAmountHistoryRepository,
        protected VoucherCreateFormFactory $voucherCreateFormFactory
    )
    {
        parent::__construct();
    }

    public function renderDefault(): void
    {
        if (!$this->getUser()->isAllowed('voucher', 'default')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }
        $this->template->Paid = $this->voucherRepository->getAll()->where('Paid', true)->where('Solved', false)->order('Code ASC');
        $this->template->Unpaid = $this->voucherRepository->getAll()->where('Paid', false)->where('Solved', false)->order('Code ASC');
    }

    public function renderDetail(int $id): void
    {
        if (!$this->getUser()->isAllowed('voucher', 'detail')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $detail = $this->voucherRepository->getAll()->where('ID', $id)->fetch();
        if (!$detail) {
            $this->error('Voucher nenalezen', \Nette\Http\IResponse::S404_NOT_FOUND);
        }

        $history = $this->voucherAmountHistoryRepository
            ->getAll()
            ->where('voucher_id', $id)
            ->order('changed_at DESC'); // Selection je OK, můžeš iterovat v šabloně

        $this->template->detail = $detail;
        $this->template->history = $history;
    }

    public function createComponentVariablSymbolForm(): Form
    {
        if (!$this->getUser()->isAllowed('voucher', 'vs')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $form = $this->variablSymbolFormFactory->create();
        $form->onSuccess[] = function (Form $form, array $v): void {
            $voucher = $this->voucherRepository->getAll()->where('Vs', $v['Vs'])->fetch();
            if ($voucher['Paid'] === 0) {
                $uploadDir = __DIR__ . '/../../../Core/Mail/Voucher/';
                $files = glob($uploadDir . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                $lng = $voucher['Locale'];

                $id = $voucher['ID'];
                $code = rand(0000, 9999);
                $to = $voucher['Mail'];
                $params = [
                    'Code' => $code,
                    'Amount' => $voucher['Amount']
                ];
                $this->voucherRepository->update($id, ['Paid' => true]);
                $this->voucherRepository->update($id, ['Code' => $code]);
                $this->voucherRepository->update($id, ['Date' => new \DateTimeImmutable()]);
                $this->createVoucher($params);
                $file = 'voucher_' . $params['Code'] . '.pdf';
                $this->mailService->sendVoucherPaid($to, $params, $file, $lng);
                $this->flashMessage('Voucher byl úspěšně označen jako zaplacený a byl odeslán email.', 'success');
                $this->redirect('this');
            } else {
                $this->flashMessage('Voucher s tímto variabilním symbolem nebyl nalezen.', 'error');
                $this->redirect('this');
            }
        };
        return $form;
    }

    public function createComponentCreateVoucherForm(): Form
    {
        if (!$this->getUser()->isAllowed('voucher', 'create')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $form = $this->voucherCreateFormFactory->create();
        $form->onSuccess[] = function (Form $form, array $v): void {
            $this->voucherRepository->insert([
                'Amount' => $v['Amount'],
                'Code' => $v['Code'],
                'Paid' => true,
                'Date' => $v['Date'],
                'Note' => $v['Note']
            ]);
            $this->flashMessage('Vypiš na voucher cenu: <strong>' . $v['Amount'] . ',-</strong> a číslo voucheru: <strong>' . $v['Code'] . '</strong>.', 'success');
            $this->redirect('default');
        };
        return $form;
    }

    public function handleUsedVoucher(int $id): void
    {
        if (!$this->getUser()->isAllowed('voucher', 'used')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $this->voucherRepository->update($id, ['Solved' => true]);
        $this->flashMessage('Voucher byl označen jako použitý.', 'success');
        $this->redirect('this');
    }

    public function handlePaidVoucher(int $id): void
    {
        if (!$this->getUser()->isAllowed('voucher', 'paid')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }
        $uploadDir = __DIR__ . '/../../../Core/Mail/Voucher/';
        $files = glob($uploadDir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $voucher = $this->voucherRepository->getAll()->where('ID', $id)->fetch();
        $code = rand(0000, 9999);
        $to = $voucher['Mail'];
        $params = [
            'Code' => $code,
            'Amount' => $voucher['Amount']
        ];
        $lng = $voucher['Locale'];
        $this->voucherRepository->update($id, ['Paid' => true]);
        $this->voucherRepository->update($id, ['Code' => $code]);
        $this->voucherRepository->update($id, ['Date' => new \DateTimeImmutable()]);
        $this->createVoucher($params);
        $file = 'voucher_' . $params['Code'] . '.pdf';
        $this->mailService->sendVoucherPaid($to, $params, $file, $lng);
        $this->flashMessage('Voucher byl označen jako zaplacený.', 'success');
        $this->redirect('this');
    }

    public function handleSetAmount(int $id, float $amount = 0): void
    {
        if (!$this->getUser()->isAllowed('voucher', 'set')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $voucher = $this->voucherRepository->getAll()->where('ID', $id)->fetch();
        if (!$voucher) {
            $this->flashMessage('Voucher nebyl nalezen.', 'error');
            $this->redirect('this');
            return;
        }
        if($amount > $voucher['Amount']) {
            $this->flashMessage('Nová částka nemůže být větší než původní částka voucheru.', 'error');
            $this->redirect('this');
            return;
        }
        $this->voucherAmountHistoryRepository->insert([
            'voucher_id' => $id,
            'old_amount' => $voucher['Amount'],
            'new_amount' => $amount,
            'changed_at' => new \DateTimeImmutable(),
        ]);

        $new = max(0.0, (float) $amount);
        $this->voucherRepository->update($id, ['Amount' => $new]);


        $this->flashMessage("Částka voucheru nastavena na {$new}.", 'success');
        $this->redirect('this');
    }

    public function handleSaveComment(): void
    {
        if (!$this->getUser()->isAllowed('voucher', 'comments')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $req  = $this->getHttpRequest();
        $post = $req->getPost();

        $voucherId = isset($post['voucher_id']) ? (int)$post['voucher_id'] : 0;
        $note      = isset($post['note']) ? (string)$post['note'] : '';

        if ($voucherId <= 0) {
            $this->error('Neplatné ID voucheru.', \Nette\Http\IResponse::S400_BAD_REQUEST);
        }

        // existuje?
        $row = $this->voucherRepository->getAll()->get($voucherId);
        if (!$row) {
            $this->error('Voucher nenalezen.', \Nette\Http\IResponse::S404_NOT_FOUND);
        }

        // update
        $this->voucherRepository->getAll()
            ->where('ID', $voucherId)
            ->update(['Note' => $note]);

        $this->flashMessage('Poznámka byla uložena.', 'success');
        $this->redirect('this', ['id' => $voucherId]);
    }

    public function handleSetAmount2(): void
    {
        if (!$this->getUser()->isAllowed('voucher', 'setAmount')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $req   = $this->getHttpRequest();
        $post  = $req->getPost();

        $id     = isset($post['id']) ? (int)$post['id'] : 0;
        $amount = isset($post['amount']) ? (int)$post['amount'] : null;

        if ($id <= 0 || $amount === null || $amount < 0) {
            $this->error('Neplatný vstup.', \Nette\Http\IResponse::S400_BAD_REQUEST);
        }

        $row = $this->voucherRepository->getAll()->where('ID',$id)->fetch();
        if (!$row) {
            $this->error('Voucher nenalezen.', \Nette\Http\IResponse::S404_NOT_FOUND);
        }

        $old = $row->Amount;
        if ($old === $amount) {
            $this->flashMessage('Částka zůstala beze změny.', 'info');
            $this->redirect('this', ['id' => $id]);
        }


        try {
            $this->voucherAmountHistoryRepository->getAll()->insert([
                'voucher_id' => $id,
                'old_amount' => $old,
                'new_amount' => $amount,
                'changed_at' => new \DateTimeImmutable(),
            ]);

            // 2) Update master záznamu
            $this->voucherRepository->getAll()
                ->where('ID', $id)
                ->update(['Amount' => $amount]);

            $this->flashMessage('Částka byla aktualizována.', 'success');
        } catch (\Throwable $e) {
            $this->flashMessage('Nepodařilo se uložit částku.', 'error');
        }

        $this->redirect('this', ['id' => $id]);
    }





    protected function createVoucher($params): void
    {

        $pdf = new Fpdi;
        $pdf->AddPage();

        $pdf->setSourceFile(__DIR__ . '/template/poukaz.pdf');
        $tplIdx = $pdf->importPage(1);
        $pdf->useTemplate($tplIdx, 0, 0, 676, 320, true);

        $pdf->SetFont('Arial', '', 58);
        $pdf->SetTextColor(58, 44, 44);
        $pdf->SetXY(65, 140);
        $pdf->Write(20, $params['Amount'].' ,-');

        $pdf->SetFont('Arial', '', 48);
        $pdf->SetTextColor(58, 44, 44);
        $pdf->SetXY(50, 160);
        $pdf->Write(20, $params['Code']);

        $filePath = __DIR__ . '/../../../Core/Mail/Voucher/voucher_' . $params['Code'] . '.pdf';
        $pdf->Output('F',$filePath);
    }

    public function handleDeleteVoucher(int $id): void
    {
        if (!$this->getUser()->isAllowed('voucher', 'delete')) {
            $this->error('Forbidden', \Nette\Http\IResponse::S403_FORBIDDEN);
        }

        $this->voucherRepository->getAll()->where('ID', $id)->delete();
        $this->flashMessage('Voucher byl smazán.', 'success');
        $this->redirect('default');
    }

}