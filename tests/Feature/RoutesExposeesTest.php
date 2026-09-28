<?php

it('does not expose self-service account routes', function (string $methode, string $uri) {
    $this->call($methode, $uri)->assertNotFound();
})->with([
    'inscription (formulaire)' => ['GET', '/register'],
    'inscription (envoi)' => ['POST', '/register'],
    'mot de passe oublié' => ['POST', '/forgot-password'],
    'réinitialisation' => ['POST', '/reset-password'],
    'connexion par passkey' => ['POST', '/passkeys/login'],
    'activation 2FA' => ['POST', '/user/two-factor-authentication'],
]);
