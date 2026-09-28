<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>


# Plateforme de Cartes de Fidélité — CLAUDE.md

Ce fichier guide le développement de la plateforme de gestion de cartes de fidélité et réductions. Il est basé sur les spécifications fonctionnelles du projet (activation en agence, vérification + validation OTP chez les partenaires, alertes d'expiration).

**Convention de nommage** : tables, colonnes et noms de modèles Eloquent en français dans tout le projet (sauf `users`, `password_reset_tokens`, `sessions`, etc. — tables techniques du scaffolding Laravel/Breeze à conserver telles quelles pour rester compatible avec l'auth native ; renommer uniquement leurs colonnes métier si besoin).

## 1. Stack technique

- **Backend** : Laravel (dernière version stable — installer via `laravel new` pour obtenir la version majeure la plus récente, ne pas figer une version ancienne)
- **Vue / Templates** : Blade (pas de SPA JS front — rendu serveur, composants Blade réutilisables)
- **CSS** : Bootstrap 5.3
- **JS** : Vanilla JS / Alpine.js pour l'interactivité légère (dropdowns dynamiques, inputs OTP, feedback temps réel) — pas de framework JS lourd (React/Vue) sauf besoin explicite
- **Base de données** : MySQL
- **Rôles & permissions** : Spatie `laravel-permission`
- **Tables de données** : Yajra DataTables (server-side, toujours — jamais de rendu client-side sur des jeux de données volumineux)
- **Médias** : Spatie MediaLibrary si besoin de pièces jointes (photo pièce d'identité, logo partenaire)
- **Alertes / confirmations UI** : SweetAlert2
- **Sélecteurs** : Select2
- **SMS (OTP + alertes d'expiration)** : passerelle SMS locale/agrégateur (Orange/MTN/Moov CI ou Africa's Talking / Nexah) — appel toujours via **queue**, jamais synchrone dans la requête HTTP
- **Scheduler** : Laravel Task Scheduling (cron) pour le calcul quotidien des cartes proches de l'expiration
- **Queue worker** : pattern `--stop-when-empty` (compatible hosting mutualisé cPanel)
- **Déploiement** : Git + Composer (`composer install`, jamais `composer update` en prod)

## 2. Rôles et permissions (Spatie)

Guards / rôles à modéliser avec `laravel-permission` :

| Rôle | Description | Permissions clés |
|---|---|---|
| `superadmin` | Accès total (via `Gate::before`), compte créé depuis le `.env` (`SUPERADMIN_*`), mot de passe fort (≥ 12 car.) | toutes, y compris `gerer-roles` et `gerer-parametres` (réservées) |
| `admin` | Pilote toute la plateforme **et peut faire tout ce que font agent et partenaire** (chaque action tracée à son nom + rôle) | `acceder-espace-admin`, `gerer-utilisateurs`, `gerer-cartes`, `gerer-partenaires`, `gerer-taux`, `gerer-activations`, `voir-transactions`, `voir-statistiques`, `voir-journal-audit` + permissions agent et partenaire |
| `agent` | Active les cartes en agence (tous les agents agissent sur toutes les cartes) | `acceder-espace-agent`, `activer-carte`, `rechercher-carte`, `voir-ses-activations`, `signaler-carte-perdue` |
| `partenaire` | Compte partenaire (peut avoir plusieurs opérateurs) | `acceder-espace-partenaire`, `verifier-carte`, `confirmer-otp`, `voir-ses-transactions` |

Source de vérité : enums `App\Enums\Role` (dont `Role::permissions()`) et `App\Enums\Permission`, synchronisés par `RolesEtPermissionsSeeder`.

Règles d'implémentation :
- Un **partenaire** peut avoir plusieurs utilisateurs opérateurs rattachés à un même compte (prévoir la relation `partenaires` ↔ `users` dès le modèle de données, même si le MVP ne gère qu'un utilisateur par partenaire).
- Middleware de redirection post-login selon le rôle (`superadmin`/`admin` → `/admin`, `agent` → `/agent`, `partenaire` → `/partenaire`). Admin et superadmin accèdent aussi aux espaces agent et partenaire.
- Toute route de chaque espace protégée par middleware `role:` + `permission:` (ne jamais se fier uniquement à la visibilité UI).
- Les policies Laravel (`CartePolicy`, `PartenairePolicy`, `TransactionPolicy`) encapsulent les règles d'accès aux ressources (ex. un partenaire ne voit que ses propres transactions).

## 3. Modèle de données (tables et colonnes en français)

### `partenaires` (modèle `Partenaire`)
`id`, `nom`, `secteur`, `localisation`, `contact`, `taux_reduction` (taux unique au MVP), `statut`, `created_at`, `updated_at`

### `users` (table technique, colonnes métier ajoutées)
`nom`, `nom_utilisateur` (unique, minuscules — identifiant de connexion), `email` (nullable), `telephone`, `partenaire_id` (opérateur partenaire), `statut`, `tentatives_echouees`, `verrouille_le`, `derniere_connexion_le`, soft deletes

### `titulaires` (modèle `Titulaire`)
`id`, `nom`, `prenom`, `telephone` (**unique**, format E.164 `+<indicatif><numéro>` — identifie le titulaire et reçoit les OTP), `numero_piece_identite` (**facultatif**, non collecté au MVP ; chiffré si renseigné) + `numero_piece_identite_hash` (HMAC, recherche/unicité), `statut`, `cree_par_id`, `modifie_par_id`, `created_at`, `updated_at`

### `cartes` (modèle `Carte`)
`id`, `numero_carte` (**7 chiffres**, unique à vie, soft-deletées comprises), `titulaire_id`, `active_par_id` (créateur — clé vers `users`), `active_le`, `expire_le` (= `active_le` + 1 an), `statut` (`non_activee`, `active`, `expiree`, `suspendue`, `revoquee`), `motif_statut`, `modifie_par_id`, `created_at`, `updated_at`

### `transactions` (modèle `Transaction`)
`id`, `carte_id`, `partenaire_id`, `demande_otp_id` (**unique** — idempotence), `valide_par_id` (opérateur), `taux_applique`, `validee_le`, `statut`, `created_at`, `updated_at` — **aucun montant** : une transaction atteste le passage chez un partenaire au taux indiqué

### `demandes_otp` (modèle `DemandeOtp`)
`id`, `carte_id`, `partenaire_id`, `demandee_par_id`, `code_hash` (jamais en clair), `demandee_le`, `expire_le`, `tentatives`, `statut`, `utilisee_le`, `created_at`, `updated_at`

### `journaux_audit` (modèle `JournalAudit`)
`id`, `acteur_id`, `type_acteur`, `action`, `type_entite`, `entite_id`, `donnees` (avant/après, JSON), `cree_le` — jamais modifiable ; conservée **14 jours** (purge quotidienne `journal:purger`), suppression manuelle possible (superadmin, motif obligatoire) — toute purge est inscrite dans `purges_journal_audit` (jamais effaçable)

### `purges_journal_audit` (modèle `PurgeJournalAudit`)
`id`, `type` (`automatique`, `manuelle`), `purge_par_id`, `supprime_avant`, `nombre_entrees`, `motif`, `cree_le` — registre en ajout seul (triggers UPDATE/DELETE)

### `messages_sms` (modèle `MessageSms`)
`id`, `telephone`, `type` (`otp`, `alerte_expiration`, `information`), `contenu` (chiffré ; masqué après envoi réel pour un OTP), `statut`, `fournisseur`, `reference_fournisseur`, `erreur`, `tentatives`, `envoye_le`, `created_at`, `updated_at`

### `alertes_expiration` (modèle `AlerteExpiration`)
`id`, `carte_id`, `palier` (`3_mois`, `2_mois`, `1_mois`), `canal` (`sms`, `in_app`), `envoyee_le`, `statut_livraison`, `created_at`

### `historique_taux_partenaires` (modèle `HistoriqueTauxPartenaire`)
`id`, `partenaire_id`, `ancien_taux`, `nouveau_taux`, `modifie_par_id`, `modifie_le` — traçabilité des changements de taux de réduction

Règles métier structurantes à respecter dans les migrations/modèles :
- `expire_le` toujours dérivé de `active_le` (+1 an) — ne jamais le laisser saisissable librement.
- Une carte expirée n'est **jamais réactivée** : le renouvellement crée une nouvelle ligne dans `cartes` (nouveau `numero_carte`), liée au même `titulaire_id`. Garder la relation `Titulaire hasMany Carte` pour l'historique.
- `taux_reduction` vit sur `partenaires` (taux unique MVP) — chaque modification est tracée dans `historique_taux_partenaires`.
- Un OTP est à usage unique, à courte durée de vie (3–5 min), avec compteur de tentatives (`tentatives`) — jamais de transaction créée avant validation OTP.

### Décisions validées (priment sur le reste du document)
- **Pas de stock de cartes** : elles sont produites hors application. La ligne `cartes` est créée à l'activation par l'agent, qui saisit le numéro imprimé (double saisie). La contrainte UNIQUE sur `numero_carte` empêche la double activation concurrente (erreur SQL convertie en message métier).
- **Activation** : nom, prénoms, téléphone, numéro de carte — pas de pièce d'identité. Le **téléphone** retrouve le titulaire lors d'un renouvellement ; une seule carte en circulation par titulaire (déclarer la perte d'abord).
- **Téléphones multi-pays**, **Côte d'Ivoire par défaut** (10 chiffres, tout préfixe) : pays configurés dans `config/plateforme.php` (`telephone.pays`), normalisation par `App\Services\Telephone`.
- **Connexion** : nom d'utilisateur + **PIN permanent à 5 chiffres** généré (`GenerateurPin`, sans suites triviales), affiché une fois, réinitialisable par un admin ou `php artisan utilisateur:reinitialiser-pin`. Protections : 5 essais/min par (utilisateur, IP), limite par IP, verrouillage après 10 échecs, message générique, sessions 8 h max / 2 h d'inactivité.
- **Traçabilité** : chaque action affiche son auteur « Nom · Rôle » (`User::libelleActeur()`), en plus du journal d'audit (`JournaliserAudit`, champs sensibles retirés).
- **SMS** : pilote choisi par `SMS_DRIVER`. En attendant l'API du fournisseur (qui couvre tous les pays), pilote `simulation` : aucun envoi réel, messages lisibles dans la boîte « SMS simulés » de l'admin (codes OTP compris) — **interdit en production**. Tout envoi passe par `EnvoiSms` puis la file d'attente (`EnvoyerSmsJob`, 3 tentatives, idempotent, aucun contenu dans la charge utile du job).
- **Journal d'audit** : rétention 14 jours + suppression manuelle (voir `purges_journal_audit`).
- **Opérateurs partenaires** : créés par l'admin, ou par un agent disposant de la permission dédiée (phase 5).
- **Interface** : coque type ERP (barre latérale réductible, volet mobile, menu utilisateur en dropdown), charte bleu nuit / or tirée des visuels de la carte. La liste des cartes agent est une grille de cartes visuelles paginée côté serveur (Yajra est réservé aux tableaux d'administration).

## 4. Optimisation base de données

- **Index** systématiques sur : `cartes.numero_carte` (unique), `cartes.statut`, `cartes.expire_le`, `titulaires.telephone` (unique), `titulaires.numero_piece_identite_hash` (unique), `transactions.carte_id`, `transactions.partenaire_id`, `transactions.validee_le`, `journaux_audit.type_entite` + `entite_id` (index composite), `demandes_otp.carte_id` + `statut`.
- **Index composites** pour les requêtes fréquentes (ex. `cartes(statut, expire_le)` pour le job d'alertes d'expiration, `transactions(partenaire_id, validee_le)` pour l'historique partenaire).
- **Requêtes N+1** : systématiquement `with()` / eager loading sur les relations affichées dans les DataTables (`Carte::with('titulaire', 'activePar')`), jamais de lazy loading dans une boucle Blade.
- **Pagination serveur obligatoire** partout où le volume peut croître (Yajra DataTables server-side, jamais `->get()` puis pagination côté vue).
- **Cache** : centraliser l'invalidation via des Observers Laravel sur les modèles (`Carte`, `Partenaire`) plutôt que des appels `Cache::forget()` dispersés dans les contrôleurs. Cache candidat : statistiques du dashboard admin, liste des partenaires actifs, taux de réduction (invalidé à chaque modification).
- **Jobs asynchrones** pour tout traitement lourd ou externe : envoi SMS (OTP + alertes), calcul des statistiques agrégées, génération des alertes d'expiration — jamais dans le cycle de requête HTTP.
- **Requêtes d'agrégation** (statistiques admin) : utiliser des requêtes SQL agrégées (`selectRaw`, `groupBy`) plutôt que de charger les collections en mémoire pour les compter/sommer côté PHP.
- **Soft deletes** sur `cartes`, `partenaires`, `titulaires` pour préserver l'intégrité de l'historique — jamais de suppression physique d'une entité référencée par une transaction.
- **Archivage** à prévoir à terme sur `journaux_audit` et `demandes_otp` (tables à forte croissance) — partitionnement ou purge différée des `demandes_otp` expirées au-delà d'une rétention définie, sans jamais toucher aux `journaux_audit`.

## 5. Tables de données (Yajra DataTables)

- Toutes les listes à fort volume (`titulaires`, `cartes`, `transactions`, historique, `journaux_audit`) : **server-side processing obligatoire**.
- Filtres serveur natifs (par statut, par période, par partenaire, par agent) intégrés dans la requête Eloquent du DataTable, jamais filtrés côté client sur les données déjà chargées.
- Colonnes d'action (voir détail, suspendre, révoquer) rendues via des colonnes Blade dédiées (`->addColumn('actions', ...)`), avec vérification de permission avant d'afficher chaque action.
- Export (à terme, hors MVP) : prévoir la compatibilité Yajra avec un export serveur (CSV/Excel) plutôt qu'un export du DOM client.

## 6. Interface — fluide, accessible, responsive

- **Mobile-first obligatoire**, en particulier l'espace Partenaire : le parcours vérification → OTP → validation doit tenir sur un seul écran par étape, gros boutons (zone tactile ≥ 44px), clavier numérique (`inputmode="numeric"`) pour le numéro de carte et l'OTP.
- **Breakpoints Bootstrap** : concevoir d'abord en mobile (`<576px`), puis valider tablette (`md`) et desktop (`lg`/`xl`) — l'espace Administration peut être desktop-first (tableaux de bord, DataTables larges) mais doit rester utilisable en tablette.
- **Accessibilité** : vrais éléments sémantiques (`<button>`, `<label for>`, `<a href>`), jamais de `<div onclick>` ; contraste texte ≥ 4.5:1 ; `aria-label` sur les boutons icône seule ; focus visible sur tous les champs interactifs (important pour la saisie rapide en boutique).
- **Performance perçue** : feedback immédiat (spinners SweetAlert2, désactivation du bouton pendant l'appel OTP) sur toute action réseau, en particulier côté partenaire où la rapidité est une exigence fonctionnelle explicite.
- **Formulaires** : validation côté serveur (Form Requests Laravel) **et** feedback client immédiat (Bootstrap validation states), jamais l'un sans l'autre.
- Pas de dépendance à un framework JS lourd : Alpine.js suffit pour l'état local (compte à rebours OTP, digits d'OTP, toggle de statut).

## 7. Sécurité — points non négociables

- `titulaires.numero_piece_identite` (facultatif) : cast `encrypted` en base (jamais en clair), recherche via empreinte HMAC.
- `demandes_otp.code_hash` : hashé en base, jamais stocké ni loggé en clair ; rate limiting sur la génération d'OTP par carte (anti-spam/anti-harcèlement du titulaire).
- Le partenaire ne voit **jamais** le nom/téléphone du titulaire avant validation OTP — uniquement le statut de la carte.
- Idempotence sur la validation OTP (double soumission réseau ne doit jamais créer deux `transactions`).
- Contrainte unique en base sur `cartes.numero_carte` pour empêcher toute double activation en cas de concurrence entre agents.
- `journaux_audit` : jamais modifiable ; suppression uniquement via l'action `PurgerJournalAudit` (verrou de session MySQL levé le temps de la purge, trigger bloquant sinon), chaque purge étant tracée dans `purges_journal_audit`. Tout ce que fait un agent ou un partenaire est journalisé, consultations sensibles comprises.

## 8. Conventions de code

- Contrôleurs fins, logique métier dans des **Actions** ou **Services** dédiés (`ActiverCarteAction`, `VerifierCarteService`, `ValiderOtpAction`) — testables indépendamment du contrôleur.
- Form Requests pour toute validation d'entrée (`ActiverCarteRequest`, `VerifierCarteRequest`, `ConfirmerOtpRequest`).
- Enums PHP (natifs Laravel/PHP 8.1+) pour les statuts (`StatutCarte`, `StatutTransaction`) plutôt que des chaînes magiques — les valeurs des enums restent en français (`NonActivee`, `Active`, `Expiree`, `Suspendue`, `Revoquee`).
- Un Observer par modèle pour la journalisation automatique dans `journaux_audit` plutôt qu'un appel manuel dispersé dans chaque contrôleur.
- Les noms de colonnes de clé étrangère suivent le suffixe `_id` même en français (`titulaire_id`, `partenaire_id`, `carte_id`) pour rester compatibles avec les conventions Eloquent par défaut.