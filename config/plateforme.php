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
        // Délai avant de pouvoir redemander un code pour la même carte.
        'renvoi_apres_secondes' => 60,
        // Anti-harcèlement du titulaire (SMS) : codes par carte.
        'codes_par_carte_15_minutes' => 3,
        'codes_par_carte_par_jour' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Vérification de carte (anti-énumération des numéros)
    |--------------------------------------------------------------------------
    */

    'verification' => [
        'par_minute_par_operateur' => 20,
        'par_minute_par_partenaire' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Alertes d'expiration (en mois avant expiration)
    |--------------------------------------------------------------------------
    */

    // Paliers : 3, 2 et 1 mois avant l'échéance (enum PalierAlerte). Un seul SMS
    // par carte et par palier ; si un palier a été manqué (tâche arrêtée), seul
    // le plus proche de l'échéance est envoyé.
    'alertes_expiration' => [
        'sms' => (bool) env('ALERTES_EXPIRATION_SMS', true),
        // Heure d'envoi quotidienne (jamais la nuit).
        'heure' => env('ALERTES_EXPIRATION_HEURE', '09:00'),
        // :numero, :date et :delai sont remplacés ; rester sous 160 caractères.
        'message' => 'ADVANTAGE : votre carte :numero expire le :date (dans :delai). Rendez-vous en agence fontaine GROUP pour la renouveler.',
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS
    |--------------------------------------------------------------------------
    */

    'sms' => [
        // « ticafrique » : envoi réel via TICAFRIQUE SMS (config/services.php).
        // « simulation » : aucun envoi réel, messages consultables dans la boîte
        // « SMS simulés » (interdit en production).
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

    /*
    |--------------------------------------------------------------------------
    | Rétention des données techniques (commande donnees:purger, chaque nuit)
    |--------------------------------------------------------------------------
    |
    | Les demandes de code liées à une transaction sont toujours conservées.
    | Le journal d'audit a sa propre rétention ; l'historique des cartes est
    | permanent.
    |
    */

    'retention' => [
        'demandes_otp_jours' => (int) env('RETENTION_DEMANDES_OTP_JOURS', 90),
        'messages_sms_jours' => (int) env('RETENTION_MESSAGES_SMS_JOURS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sauvegardes de la base (Administration › Paramètres › Sauvegardes)
    |--------------------------------------------------------------------------
    |
    | Dossier par défaut (modifiable dans l'application, jamais dans public/),
    | nombre de sauvegardes conservées, binaires MySQL et sauvegarde nocturne.
    |
    */

    'sauvegardes' => [
        'dossier' => env('SAUVEGARDES_DOSSIER', storage_path('app/sauvegardes')),
        'conserver' => (int) env('SAUVEGARDES_CONSERVER', 10),
        'mysqldump' => env('SAUVEGARDES_MYSQLDUMP', 'mysqldump'),
        'mysql' => env('SAUVEGARDES_MYSQL', 'mysql'),
        'automatique' => (bool) env('SAUVEGARDES_AUTOMATIQUE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Exports des listes (CSV, Excel, PDF)
    |--------------------------------------------------------------------------
    |
    | CSV et Excel sont écrits en flux (mémoire constante) ; le PDF est rendu
    | en mémoire, d'où une limite plus basse. Au-delà : export refusé avec
    | un message invitant à affiner les filtres.
    |
    */

    'exports' => [
        'lignes_max' => [
            'csv' => (int) env('EXPORT_LIGNES_MAX_TABLEUR', 100000),
            'xlsx' => (int) env('EXPORT_LIGNES_MAX_TABLEUR', 100000),
            'pdf' => (int) env('EXPORT_LIGNES_MAX_PDF', 2000),
        ],
    ],

    'journal_audit' => [
        'retention_jours' => (int) env('JOURNAL_AUDIT_RETENTION_JOURS', 14),
    ],

    /*
    |--------------------------------------------------------------------------
    | Protection contre les robots
    |--------------------------------------------------------------------------
    |
    | Chemins sondés par les scanners (.env, .git, wp-login.php…) : 404 immédiat,
    | sans session ni base, puis blocage temporaire de l'IP au-delà d'un seuil.
    | Limites de navigation : par IP pour les visiteurs, par compte une fois
    | connecté (des caisses partagent souvent une même IP mobile : CGNAT).
    |
    */

    'robots' => [
        'sondes_avant_blocage' => (int) env('ROBOTS_SONDES_AVANT_BLOCAGE', 10),
        'fenetre_sondes_minutes' => 10,
        'duree_blocage_minutes' => (int) env('ROBOTS_DUREE_BLOCAGE_MINUTES', 30),
        'requetes_par_minute_visiteur' => (int) env('ROBOTS_REQUETES_PAR_MINUTE_VISITEUR', 120),
        'requetes_par_minute_connecte' => (int) env('ROBOTS_REQUETES_PAR_MINUTE_CONNECTE', 600),
        // Délai minimal (secondes) entre l'affichage du formulaire de connexion et son envoi.
        'delai_minimal_connexion' => (int) env('ROBOTS_DELAI_MINIMAL_CONNEXION', 1),
    ],

];
