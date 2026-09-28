<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proxys de confiance
    |--------------------------------------------------------------------------
    |
    | Derrière un proxy / répartiteur de charge / Cloudflare, l'adresse IP du
    | client n'est fiable que si l'adresse du proxy est déclarée ici : sinon
    | request()->ip() renvoie l'IP du proxy et toutes les limites de débit
    | (connexion, OTP) s'appliquent à tous les utilisateurs à la fois.
    |
    | Valeurs : vide (aucun proxy, défaut sûr), liste d'IP/CIDR séparées par
    | des virgules, ou « * » uniquement si le serveur n'est joignable QUE via
    | le proxy (sinon un client peut falsifier son IP via X-Forwarded-For).
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
