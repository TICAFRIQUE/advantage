<?php

/*
|--------------------------------------------------------------------------
| Rôles et permissions de la plateforme (source de vérité)
|--------------------------------------------------------------------------
|
| Synchronisés en base par : php artisan permissions:synchroniser
|
| La synchronisation est ADDITIVE et idempotente (production comme local) :
| - crée uniquement les rôles et permissions manquants (jamais de doublon) ;
| - n'attribue les « roles » par défaut qu'à la PREMIÈRE création d'une
|   permission (ou d'un rôle) : les réglages faits ensuite dans Paramètres
|   ne sont jamais écrasés ni retirés ;
| - ne supprime rien, sauf option explicite --supprimer-obsoletes.
|
| « espace » : gestion (back-office) ou partenaire. Les permissions d'un
| espace ne sont jamais attribuables à un rôle de l'autre espace.
|
| Espace « superadmin » : permissions réservées au superadmin (qui les
| détient via Gate::before). Aucun rôle n'appartient à cet espace : elles ne
| sont donc jamais attribuables, et la synchronisation les retire de tout
| rôle qui les porterait.
|
*/

return [

    'roles' => [
        'superadmin' => ['libelle' => 'Super administrateur', 'espace' => 'gestion', 'verrouille' => true],
        'admin' => ['libelle' => 'Administrateur', 'espace' => 'gestion', 'verrouille' => false],
        'agent' => ['libelle' => 'Agent', 'espace' => 'gestion', 'verrouille' => false],
        'partenaire' => ['libelle' => 'Partenaire', 'espace' => 'partenaire', 'verrouille' => false],
    ],

    'groupes' => [

        'general' => [
            'libelle' => 'Général',
            'espace' => 'gestion',
            'permissions' => [
                'acceder-gestion' => ['libelle' => 'Accéder au back-office', 'roles' => ['admin', 'agent']],
                'voir-tableau-de-bord' => ['libelle' => 'Voir le tableau de bord', 'roles' => ['admin', 'agent']],
            ],
        ],

        'cartes' => [
            'libelle' => 'Cartes',
            'espace' => 'gestion',
            'permissions' => [
                'activer-carte' => ['libelle' => 'Activer une carte', 'roles' => ['admin', 'agent']],
                'voir-cartes' => ['libelle' => 'Voir les cartes', 'roles' => ['admin', 'agent']],
                'gerer-statut-carte' => ['libelle' => 'Gérer le statut des cartes (suspendre, réactiver, révoquer)', 'roles' => ['admin']],
                'modifier-titulaire' => ['libelle' => 'Modifier le nom et les prénoms du titulaire', 'roles' => ['admin']],
                'modifier-telephone-titulaire' => ['libelle' => 'Modifier le téléphone du titulaire', 'roles' => ['admin']],
                'voir-rapport-cartes' => ['libelle' => 'Voir le rapport des cartes', 'roles' => ['admin', 'agent']],
            ],
        ],

        'partenaires' => [
            'libelle' => 'Partenaires',
            'espace' => 'gestion',
            'permissions' => [
                'voir-partenaires' => ['libelle' => 'Voir les partenaires', 'roles' => ['admin', 'agent']],
                'gerer-partenaires' => ['libelle' => 'Créer et modifier les partenaires (taux compris)', 'roles' => ['admin']],
                'supprimer-partenaires' => ['libelle' => 'Supprimer (archiver) un partenaire et ses utilisateurs', 'roles' => ['admin']],
                'gerer-operateurs-partenaires' => ['libelle' => 'Gérer les utilisateurs des partenaires', 'roles' => ['admin']],
                'effectuer-transaction-partenaire' => ['libelle' => 'Effectuer une transaction pour un partenaire', 'roles' => ['admin']],
                'voir-rapport-transactions' => ['libelle' => 'Voir le rapport des transactions', 'roles' => ['admin']],
            ],
        ],

        'parametres' => [
            'libelle' => 'Administration',
            'espace' => 'gestion',
            'permissions' => [
                'gerer-utilisateurs' => ['libelle' => 'Gérer les utilisateurs du back-office (création, modification)', 'roles' => ['admin']],
                'reinitialiser-pin' => ['libelle' => "Réinitialiser le PIN d'un compte", 'roles' => ['admin']],
                'supprimer-comptes' => ['libelle' => 'Supprimer (archiver) un compte utilisateur', 'roles' => ['admin']],
                'gerer-roles' => ['libelle' => 'Gérer les rôles et permissions', 'roles' => []],
                'gerer-parametres' => ['libelle' => "Modifier les paramètres (nom et logo de l'application)", 'roles' => ['admin']],
                'voir-journal-audit' => ['libelle' => "Consulter le journal d'audit", 'roles' => ['admin']],
                'purger-journal-audit' => ['libelle' => "Supprimer des entrées du journal d'audit", 'roles' => []],
            ],
        ],

        'exports' => [
            'libelle' => 'Exports',
            'espace' => 'gestion',
            'permissions' => [
                'exporter-donnees' => ['libelle' => 'Exporter les listes (PDF, Excel, CSV)', 'roles' => ['admin']],
            ],
        ],

        'outils' => [
            'libelle' => 'Outils de test',
            'espace' => 'gestion',
            'permissions' => [
                'voir-sms-simules' => ['libelle' => 'Voir les SMS simulés (hors production)', 'roles' => ['admin']],
            ],
        ],

        'superadmin' => [
            'libelle' => 'Réservé au superadmin',
            'espace' => 'superadmin',
            'permissions' => [
                'restaurer-elements' => ['libelle' => 'Restaurer les partenaires et comptes supprimés', 'roles' => []],
                'gerer-sauvegardes' => ['libelle' => 'Créer, télécharger et restaurer les sauvegardes', 'roles' => []],
                'tester-sms' => ['libelle' => "Tester l'envoi réel de SMS (consomme des unités)", 'roles' => []],
                'voir-commandes-production' => ['libelle' => 'Consulter les commandes de mise en production', 'roles' => []],
            ],
        ],

        'espace_partenaire' => [
            'libelle' => 'Espace partenaire',
            'espace' => 'partenaire',
            'permissions' => [
                'acceder-espace-partenaire' => ['libelle' => "Accéder à l'espace partenaire", 'roles' => ['partenaire']],
                'effectuer-transaction' => ['libelle' => 'Effectuer une transaction', 'roles' => ['partenaire']],
                'voir-historique-transactions' => ['libelle' => 'Voir son historique', 'roles' => ['partenaire']],
            ],
        ],

    ],

];
