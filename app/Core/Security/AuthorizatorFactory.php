<?php

namespace App\Core\Security;
use Nette\Security\Permission;

final class AuthorizatorFactory
{
    public static function create(): Permission
    {
        $acl = new Permission();

        $acl->addRole('user');
        $acl->addRole('admin', 'user'); // admin dědí usera

        foreach (['home', 'food', 'galerie', 'reservation', 'settings', 'newsletter', 'voucher', 'table', 'event'] as $res) {
            if (!$acl->hasResource($res)) {
                $acl->addResource($res);
            }
        }

        // admin má vše
        $acl->allow('admin', Permission::ALL, Permission::ALL);

        $acl->allow('user', 'home', ['default','detail','reservation', 'comments', 'paid', 'changeRoom', 'clean', 'book']);
        $acl->allow('user', 'voucher', ['default', 'create', 'used', 'set']);
        $acl->allow('user', 'table', ['default', 'create_group', 'detail', 'solved', 'comments', 'group', 'cancel', 'change']);

        return $acl;
    }
}
