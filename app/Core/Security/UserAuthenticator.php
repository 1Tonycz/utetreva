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
        $row = $this->database->table('users')->where('username', $username)->fetch();
        if (!$row) {
            throw new AuthenticationException('Uživatel nenalezen.');
        }
        if (!password_verify($password, $row->password)) {
            throw new AuthenticationException('Špatné heslo.');
        }

        // role může být "admin", "user" nebo "admin,user"
        $roles = $row->role ? array_map('trim', explode(',', (string)$row->role)) : ['user'];

        return new SimpleIdentity(
            $row->id,
            $roles,
            ['username' => $row->username]
        );
    }

}
