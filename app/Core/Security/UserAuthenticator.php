<?php
declare(strict_types=1);

namespace App\Core\Security;

use Nette\Security\Authenticator;
use Nette\Security\Identity;
use Nette\Security\SimpleIdentity;
use Nette\Security\AuthenticationException;
use Nette\Database\Explorer;

final class UserAuthenticator implements Authenticator
{
    public function __construct(
        private Explorer $database,
    ) {}

    public function authenticate(string $username, string $password): SimpleIdentity
    {
        $row = $this->database
            ->table('users')
            ->where('username', $username)
            ->fetch();

        if (!$row) {
            throw new AuthenticationException('Uživatel nenalezen.');
        }

        bdump($password, 'Zadané heslo');
        bdump($row->password, 'Hash z databáze');
        bdump(password_hash('1234', PASSWORD_DEFAULT), 'Nově vygenerovaný hash hesla "1234"');
        bdump(password_verify($password, $row->password), 'Výsledek ověření');

        if (!password_verify($password, $row->password)) {
            throw new AuthenticationException('Špatné heslo.');
        }

        return new SimpleIdentity($row->id, $row->role ?? 'user', ['username' => $row->username]);
    }
}
