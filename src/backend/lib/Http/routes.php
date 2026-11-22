<?php
declare(strict_types=1);

use Wegrep\Auth\Role;
use Wegrep\Http\Endpoints;
use Wegrep\Http\Router;

/**
 * The route table of the API: every endpoint, who may call it and which controller method answers.
 *  - open():    anybody, also without being signed in
 *  - account(): any signed-in account, also one that waits for approval
 *  - add():     the roles that may call it; by default every role (so approved accounts only)
 */
return static function (Router $r, Endpoints $c): void {
    // The app needs these before anybody is signed in.
    $r->open('GET',  '/meta',          $c->meta->meta(...));
    $r->open('GET',  '/logos',         $c->meta->logos(...));
    $r->open('POST', '/auth/register', $c->auth->register(...));
    $r->open('POST', '/auth/code',     $c->auth->code(...));
    $r->open('POST', '/auth/verify',   $c->auth->verify(...));

    // A new account can only ask who it is, to find out when it was approved.
    $r->account('GET', '/auth/me', $c->auth->me(...));

    // Administrators.
    $admin = [Role::Administrator];
    $r->add('GET',    '/admin/users',                          $c->admin->users(...),    $admin);
    $r->add('PATCH',  '/admin/users/(\d+)',                    $c->admin->setRole(...),  $admin);
    $r->add('DELETE', '/admin/users/(\d+)',                    $c->admin->remove(...),   $admin);
    $r->add('GET',    '/admin/sessions',                       $c->admin->sessions(...), $admin);
    $r->add('GET',    '/admin/sessions/(\d+)/(\d+)',           $c->admin->session(...),  $admin);
    $r->add('POST',   '/admin/sessions/(\d+)/(\d+)/take-over', $c->admin->takeOver(...), $admin);

    // Everything else: every role.
    $r->add('GET',    '/sessions',       $c->sessions->list(...));
    $r->add('GET',    '/sessions/(\d+)', $c->sessions->show(...));
    $r->add('PUT',    '/sessions/(\d+)', $c->sessions->save(...));
    $r->add('DELETE', '/sessions/(\d+)', $c->sessions->delete(...));

    $r->add('GET',  '/leagues',              $c->leagues->leagues(...));
    $r->add('GET',  '/leagues/(\d+)/games',  $c->leagues->games(...));
    $r->add('GET',  '/games/(\d+)',          $c->games->show(...));
    $r->add('GET',  '/games/(\d+)/precheck', $c->games->precheck(...));
    $r->add('GET',  '/games/(\d+)/eligible/(\d+)', $c->games->eligible(...));
    $r->add('POST', '/games/(\d+)/preview',  $c->games->preview(...));
    $r->add('POST', '/games/(\d+)/upload',   $c->games->upload(...));
};
