<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Compte super administrateur
    |--------------------------------------------------------------------------
    |
    | Compte créé par le seeder de manière idempotente. Le mot de passe n'est
    | appliqué qu'à la création du compte, jamais écrasé ensuite.
    |
    */

    'superadmin' => [
        'nom' => env('SUPERADMIN_NOM', 'Super Administrateur'),
        'nom_utilisateur' => env('SUPERADMIN_NOM_UTILISATEUR', 'superadmin'),
        'email' => env('SUPERADMIN_EMAIL') ?: null,
        'mot_de_passe' => env('SUPERADMIN_MOT_DE_PASSE'),
        'longueur_min_mot_de_passe' => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | Indexation aveugle (blind index)
    |--------------------------------------------------------------------------
    |
    | Clé HMAC servant à calculer l'empreinte recherchable des données
    | chiffrées (numéro de pièce d'identité). Ne jamais la modifier après la
    | mise en production : les empreintes existantes deviendraient invalides.
    |
    */

    'cle_hmac' => env('PLATEFORME_CLE_HMAC'),

    /*
    |--------------------------------------------------------------------------
    | Cartes
    |--------------------------------------------------------------------------
    */

    'carte' => [
        'longueur_numero' => 7,
        'duree_validite_mois' => 12,
    ],

    /*
    |--------------------------------------------------------------------------
    | Téléphones (format par pays)
    |--------------------------------------------------------------------------
    |
    | Numéros stockés au format international E.164 : +<indicatif><numéro>.
    | - longueur : nombre de chiffres du numéro national (sans indicatif) ;
    | - prefixe_national : préfixe de numérotation interne retiré à
    |   l'enregistrement (ex. « 0 » au Ghana ou en France), null sinon ;
    | - groupes : découpage d'affichage (somme = longueur).
    |
    | Côte d'Ivoire : 10 chiffres depuis 2021, le 0 initial fait partie du numéro.
    |
    */

    'telephone' => [
        'pays_defaut' => env('TELEPHONE_PAYS_DEFAUT', 'CI'),
        'pays' => [
            'CI' => ['nom' => "Côte d'Ivoire", 'indicatif' => '225', 'longueur' => 10, 'prefixe_national' => null, 'groupes' => [2, 2, 2, 2, 2]],
            'SN' => ['nom' => 'Sénégal', 'indicatif' => '221', 'longueur' => 9, 'prefixe_national' => null, 'groupes' => [2, 3, 2, 2]],
            'ML' => ['nom' => 'Mali', 'indicatif' => '223', 'longueur' => 8, 'prefixe_national' => null, 'groupes' => [2, 2, 2, 2]],
            'BF' => ['nom' => 'Burkina Faso', 'indicatif' => '226', 'longueur' => 8, 'prefixe_national' => null, 'groupes' => [2, 2, 2, 2]],
            'GN' => ['nom' => 'Guinée', 'indicatif' => '224', 'longueur' => 9, 'prefixe_national' => null, 'groupes' => [3, 2, 2, 2]],
            'TG' => ['nom' => 'Togo', 'indicatif' => '228', 'longueur' => 8, 'prefixe_national' => null, 'groupes' => [2, 2, 2, 2]],
            'BJ' => ['nom' => 'Bénin', 'indicatif' => '229', 'longueur' => 10, 'prefixe_national' => null, 'groupes' => [2, 2, 2, 2, 2]],
            'NE' => ['nom' => 'Niger', 'indicatif' => '227', 'longueur' => 8, 'prefixe_national' => null, 'groupes' => [2, 2, 2, 2]],
            'GH' => ['nom' => 'Ghana', 'indicatif' => '233', 'longueur' => 9, 'prefixe_national' => '0', 'groupes' => [2, 3, 4]],
            'CM' => ['nom' => 'Cameroun', 'indicatif' => '237', 'longueur' => 9, 'prefixe_national' => null, 'groupes' => [1, 2, 2, 2, 2]],
            'FR' => ['nom' => 'France', 'indicatif' => '33', 'longueur' => 9, 'prefixe_national' => '0', 'groupes' => [1, 2, 2, 2, 2]],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Connexion (PIN à 5 chiffres)
    |--------------------------------------------------------------------------
    */

    'connexion' => [
        'longueur_pin' => 5,
        'echecs_avant_verrouillage' => (int) env('CONNEXION_ECHECS_AVANT_VERROUILLAGE', 10),
        'tentatives_par_minute' => 5,
        // Large : sous CGNAT (opérateurs mobiles), beaucoup d'abonnés partagent une IP.
        'tentatives_par_minute_par_ip' => (int) env('CONNEXION_TENTATIVES_PAR_IP', 60),
        // Durée maximale d'une session, quelle que soit l'activité (journée de travail).
        // L'inactivité est bornée séparément par SESSION_LIFETIME (minutes).
        'duree_max_session_minutes' => 480,
    ],

    /*
    |--------------------------------------------------------------------------
    | Codes à usage unique (OTP)
    |--------------------------------------------------------------------------
    */

    'otp' => [
        'longueur' => 6,
        'duree_minutes' => (int) env('OTP_DUREE_MINUTES', 5),
        'tentatives_max' => (int) env('OTP_TENTATIVES_MAX', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alertes d'expiration (en mois avant expiration)
    |--------------------------------------------------------------------------
    */

    'alertes_expiration' => [
        '3_mois' => 3,
        '2_mois' => 2,
        '1_mois' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS
    |--------------------------------------------------------------------------
    */

    'sms' => [
        // « simulation » : aucun envoi réel, messages consultables dans la boîte
        // « SMS simulés » (interdit en production). Le pilote du fournisseur
        // sera ajouté dès que son API sera disponible.
        'driver' => env('SMS_DRIVER', 'simulation'),
        'expediteur' => env('SMS_EXPEDITEUR', 'ADVANTAGE'),
        'tentatives' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Journal d'audit : rétention
    |--------------------------------------------------------------------------
    |
    | Les entrées plus anciennes que la rétention sont purgées chaque jour
    | (commande journal:purger). Chaque purge est inscrite dans le registre
    | des purges, qui lui n'est jamais effaçable.
    |
    */

    'journal_audit' => [
        'retention_jours' => (int) env('JOURNAL_AUDIT_RETENTION_JOURS', 14),
    ],

];
