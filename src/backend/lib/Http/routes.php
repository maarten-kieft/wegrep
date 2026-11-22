<?php
declare(strict_types=1);

use Wegrep\Http\Access;
use Wegrep\Http\Endpoints;
use Wegrep\Http\Router;

/**
 * The route table of the API: every endpoint, who may call it and which controller method answers.
 * A route without an access level needs an approved account.
 */
return static function (Router $r, Endpoints $c): void {
    // Open: the app needs these before anybody is signed in.
    $r->add('GET',  '/meta',                $c->meta->meta(...),    Access::Anyone);
    $r->add('GET',  '/logos',               $c->meta->logos(...),   Access::Anyone);
    $r->add('POST', '/auth/register',       $c->auth->register(...), Access::Anyone);
    $r->add('POST', '/auth/code',           $c->auth->code(...),     Access::Anyone);
    $r->add('POST', '/auth/verify',         $c->auth->verify(...),   Access::Anyone);

    // A new account can only ask who it is, to find out when it was approved.
    $r->add('GET',  '/auth/me',             $c->auth->me(...),       Access::Account);

    // Administrators.
    $r->add('GET',    '/admin/users',                         $c->admin->users(...),    Access::Admin);
    $r->add('PATCH',  '/admin/users/(\d+)',                   $c->admin->setRole(...),  Access::Admin);
    $r->add('DELETE', '/admin/users/(\d+)',                   $c->admin->remove(...),   Access::Admin);
    $r->add('GET',    '/admin/sessions',                      $c->admin->sessions(...), Access::Admin);
    $r->add('GET',    '/admin/sessions/(\d+)/(\d+)',          $c->admin->session(...),  Access::Admin);
    $r->add('POST',   '/admin/sessions/(\d+)/(\d+)/take-over', $c->admin->takeOver(...), Access::Admin);

    // Everything else: approved accounts.
    $r->add('GET',    '/sessions',            $c->sessions->list(...));
    $r->add('GET',    '/sessions/(\d+)',      $c->sessions->show(...));
    $r->add('PUT',    '/sessions/(\d+)',      $c->sessions->save(...));
    $r->add('DELETE', '/sessions/(\d+)',      $c->sessions->delete(...));

    $r->add('GET',  '/leagues',                $c->leagues->leagues(...));
    $r->add('GET',  '/leagues/(\d+)/games',    $c->leagues->games(...));
    $r->add('GET',  '/games/(\d+)',            $c->games->show(...));
    $r->add('GET',  '/games/(\d+)/precheck',   $c->games->precheck(...));
    $r->add('POST', '/games/(\d+)/preview',    $c->games->preview(...));
    $r->add('POST', '/games/(\d+)/upload',     $c->games->upload(...));
};
