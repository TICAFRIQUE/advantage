<?php

use App\Enums\Role;

it('renders the branded home page with a login button', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('ADVANTAGE')
        ->assertSee('href="'.route('login').'"', false);
});

it('offers a link to their space to an authenticated user', function () {
    connecter(utilisateurAvecRole(Role::Agent))->get('/')
        ->assertSee('href="'.route('accueil-espace').'"', false);
});
