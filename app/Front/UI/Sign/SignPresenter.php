<?php
declare(strict_types=1);

namespace App\Front\UI\Sign;

use App\Front\UI\BasePresenter;
use Nette;
use Nette\Application\UI\Form;
use Nette\Security\AuthenticationException;

final class SignPresenter extends BasePresenter
{
    /** @inject */
    public Nette\Security\User $user;

    protected function createComponentLoginForm(): Form
    {
        $form = new Form;
        $form->addText('username', 'Uživatelské jméno:')
            ->setRequired('Zadejte uživatelské jméno');

        $form->addPassword('password', 'Heslo:')
            ->setRequired('Zadejte heslo');

        $form->addSubmit('send', 'Přihlásit se');

        $form->onSuccess[] = [$this, 'loginFormSucceeded'];
        return $form;
    }

    public function loginFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            $this->user->login($values->username, $values->password);
            $this->redirect(':Admin:Home:default');
        } catch (AuthenticationException $e) {
            $form->addError('Přihlášení se nezdařilo: ' . $e->getMessage());
        }
    }


    public function actionOut(): void
    {
        $this->user->logout();
        $this->flashMessage('Byl jste odhlášen.');
        $this->redirect('Home:default');
    }

    public function startup(): void
    {
        parent::startup();
        $this->setLayout('sign');
    }
}
