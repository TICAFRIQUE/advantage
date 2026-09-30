# Mise en production — ADVANTAGE

Commandes à exécuter sur le serveur de production (hébergement mutualisé cPanel ou VPS), dans le dossier du projet. Remplacez `/home/compte/advantage` par le chemin réel.

> **Règles d'or**
> - `composer install`, **jamais** `composer update` en production.
> - `php artisan migrate --force` : les migrations sont les seules à modifier la base.
> - `php artisan permissions:synchroniser` à **chaque** déploiement (additif : ne retire jamais les droits réglés dans Paramètres).
> - Ne jamais lancer `migrate:fresh`, `migrate:reset`, `db:wipe` ni `db:seed` complet en production.

## 1. Prérequis du serveur

- PHP **8.3** ou plus, avec les extensions `pdo_mysql`, `mbstring`, `openssl`, `intl`, `bcmath`, `fileinfo`, `ctype`, `tokenizer`, `xml`, `curl`, `gd` (logo de l'application), `zip` (exports Excel).
- La fonction PHP `proc_open` autorisée et les clients `mysqldump` / `mysql` installés : ils servent aux sauvegardes et restaurations depuis l'application (voir § 7). En mutualisé, vérifier que `proc_open` n'est pas dans `disable_functions`.
- MySQL **8.0** ou plus. L'utilisateur MySQL doit avoir le privilège **TRIGGER** : le journal d'audit et le registre des purges sont protégés par des triggers créés par les migrations.
- Si la journalisation binaire est active (fréquent en mutualisé), les triggers exigent `log_bin_trust_function_creators = 1` ou le privilège SUPER : à demander à l'hébergeur si la migration échoue sur `CREATE TRIGGER`.
- HTTPS actif (certificat SSL) sur le domaine.
- La racine web du domaine doit pointer vers le dossier `public/` du projet (jamais vers la racine du projet). Filet de sécurité : le `.htaccess` à la racine du projet renvoie tout vers `public/`, donc `.env`, `.git` ou `vendor` ne sont jamais servis, même en cas d'erreur de réglage.
- Si l'hébergeur le permet : `expose_php = Off` (php.ini) et `ServerTokens Prod` (Apache), pour ne pas annoncer les versions exactes aux robots.

Vérifier la version de PHP en ligne de commande (elle peut différer de celle du site) :

```bash
php -v
php -m | grep -i -E "pdo_mysql|mbstring|intl|bcmath|gd|zip"
php -r "var_dump(function_exists('proc_open'));"
mysqldump --version
```

## 2. Première installation

### 2.1 Récupérer le code

```bash
cd /home/compte
git clone <url-du-depot> advantage
cd advantage
git checkout main
composer install --no-dev --optimize-autoloader
```

### 2.2 Configurer l'environnement

```bash
cp .env.example .env
php artisan key:generate
```

Valeurs à renseigner dans `.env` :

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine.ci
LOG_LEVEL=warning
LOG_STACK=daily

DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
QUEUE_CONNECTION=database
CACHE_STORE=database

SUPERADMIN_NOM="Super Administrateur"
SUPERADMIN_NOM_UTILISATEUR=superadmin
# PIN à 5 chiffres (ni suite ni chiffre répété) ; vide = PIN généré et affiché une fois
SUPERADMIN_PIN=

SMS_DRIVER=ticafrique
SMS_EXPEDITEUR="FONTAINE G"
TICAFRIQUE_SMS_API_URL=https://sms.ticafrique.ci/api/v1/sms/send
TICAFRIQUE_SMS_API_KEY=<clé API TICAFRIQUE>
TICAFRIQUE_SMS_SENDER_ID="FONTAINE G"

# Uniquement derrière un proxy / CDN (Cloudflare, répartiteur…)
TRUSTED_PROXIES=
```

> L'expéditeur contient un espace : gardez les guillemets. Il doit être celui validé par TICAFRIQUE (11 caractères maximum).
>
> `SMS_DRIVER=simulation` est **refusé en production**. Avec `SMS_DRIVER=ticafrique`, l'URL doit être en HTTPS et la clé renseignée, sinon l'envoi échoue avec un message explicite.

Protéger le fichier `.env` :

```bash
chmod 600 .env
```

### 2.3 Construire les fichiers CSS / JS

Le dossier `public/build` n'est pas versionné. Si Node.js est disponible sur le serveur :

```bash
npm ci
npm run build
```

Sinon, construire sur le poste de développement puis envoyer le dossier `public/build` par SFTP :

```bash
npm ci && npm run build
```

### 2.4 Base de données, rôles et superadmin

```bash
php artisan migrate --force
php artisan permissions:synchroniser
php artisan db:seed --class=SuperAdminSeeder --force
```

Le superadmin se connecte, comme tous les comptes, avec un **PIN à 5 chiffres** : celui de `SUPERADMIN_PIN`, ou à défaut le PIN généré et affiché une seule fois par la commande (le noter). Retirer ensuite `SUPERADMIN_PIN` du `.env`. Le superadmin change ou régénère son mot de passe dans **Mon profil › Mot de passe** ; oublié : `php artisan utilisateur:reinitialiser-pin superadmin`.

Le seeder du superadmin est idempotent : relancé, il ne change jamais le mot de passe d'un compte existant.

### 2.5 Droits sur les dossiers

```bash
chmod -R 775 storage bootstrap/cache
mkdir -p public/uploads && chmod 775 public/uploads
```

`public/uploads` reçoit le logo choisi dans Administration › Paramètres (non versionné).

### 2.6 Mise en cache (performances)

```bash
php artisan optimize
```

### 2.7 Contrôle de sécurité

```bash
php artisan securite:verifier
```

La commande vérifie la configuration sans afficher aucun secret : environnement, débogage, clés, HTTPS, cookies, journal, fournisseur SMS, dossier des sauvegardes, fichiers construits, droits du `.env`. Elle indique la correction de chaque point non conforme. Ne pas ouvrir la plateforme tant qu'elle signale un point à corriger.

## 3. Tâches planifiées (cron)

Deux entrées cron (cPanel → *Tâches Cron*), exécutées chaque minute :

```bash
* * * * * cd /home/compte/advantage && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/compte/advantage && php artisan queue:work --tries=3 --sleep=1 --max-time=55 >> /dev/null 2>&1
```

- La première lance les tâches planifiées :
  - 00:10 : les cartes échues passent au statut « expirée » ;
  - 01:30 : sauvegarde automatique de la base (les 10 plus récentes sont conservées ; désactivable avec `SAUVEGARDES_AUTOMATIQUE=false`) ;
  - 02:00 : purge du journal d'audit (rétention de 14 jours) ;
  - 02:30 : purge des codes de validation et des SMS de plus de 90 jours (ceux liés à une transaction sont conservés) ;
  - 09:00 : SMS d'alerte aux titulaires dont la carte expire dans 3, 2 ou 1 mois (une seule fois par palier ; désactivable avec `ALERTES_EXPIRATION_SMS=false`).
- La seconde traite la file d'attente : envoi des SMS (codes OTP, alertes). Le processus reste à l'écoute 55 secondes puis s'arrête (`--max-time=55`) avant d'être relancé la minute suivante : un code OTP part en 1 à 2 secondes, et l'hébergement mutualisé n'a jamais de processus permanent. (Avec `--stop-when-empty`, un code pourrait attendre jusqu'à une minute.)

Vérifier la planification :

```bash
php artisan schedule:list
```

## 4. Chaque déploiement (mise à jour)

> Ces étapes sont **automatisées par GitHub Actions** à chaque push sur `main` (voir § 9). La procédure manuelle ci-dessous reste valable en secours (sans `git pull` si le serveur est alimenté par rsync).

```bash
cd /home/compte/advantage
php artisan down --retry=60
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan permissions:synchroniser
php artisan optimize:clear
php artisan optimize
php artisan queue:restart
php artisan securite:verifier
php artisan up
```

Si les fichiers CSS / JS ont changé : reconstruire (`npm ci && npm run build`) ou renvoyer `public/build` **avant** `php artisan up`.

## 5. Vérifications après déploiement

```bash
php artisan about
php artisan migrate:status
php artisan schedule:list
php artisan queue:failed
```

- `about` doit afficher `Environment: production` et `Debug Mode: OFF`.
- Les pages doivent porter l'en-tête `Content-Security-Policy` (aucun script en ligne) et, en HTTPS, `Strict-Transport-Security` :

```bash
curl -sI https://votre-domaine.ci/login | grep -iE "content-security|strict-transport"
```
- Tester une connexion, puis `php artisan sms:tester <votre numéro>`, puis une vérification de carte avec réception du code par SMS.

## 6. Commandes d'exploitation

### Comptes

Réinitialiser le PIN d'un compte (par exemple si plus aucun administrateur ne peut se connecter) :

```bash
php artisan utilisateur:reinitialiser-pin nom.utilisateur
```

Le nouveau PIN s'affiche une seule fois dans le terminal.

### Cartes

Lancer à la main les tâches quotidiennes (sans risque de doublon : chaque alerte n'est envoyée qu'une fois par palier) :

```bash
php artisan cartes:marquer-expirees
php artisan cartes:alertes-expiration
```

### Rôles et permissions

```bash
php artisan permissions:synchroniser
```

Retirer aussi les permissions supprimées de `config/permissions.php` (seulement après vérification) :

```bash
php artisan permissions:synchroniser --supprimer-obsoletes
```

### Journal d'audit

La purge automatique tourne chaque nuit. Purge manuelle jusqu'à une date, avec un motif obligatoire (inscrite au registre des purges) :

```bash
php artisan journal:purger --avant=2026-01-31 --motif="Motif de la purge"
```

### SMS

Vérifier les accès au fournisseur en envoyant un SMS de test (sans donnée sensible) à votre propre numéro :

```bash
php artisan sms:tester 0707123456
```

### File d'attente (SMS)

```bash
php artisan queue:failed
php artisan queue:retry all
php artisan queue:flush
```

`queue:flush` supprime définitivement les envois en échec : à n'utiliser qu'après analyse.

### Robots et scanners

La protection est intégrée, sans service externe :

- chemins sondés par les scanners (`.env`, `.git`, `wp-login.php`, `*.php`…) : réponse 404 immédiate, sans session ni accès à la base ;
- outils de scan connus et requêtes sans navigateur déclaré : refusés ;
- 10 sondes en 10 minutes depuis une même adresse IP : l'adresse est bloquée 30 minutes (`ROBOTS_SONDES_AVANT_BLOCAGE`, `ROBOTS_DUREE_BLOCAGE_MINUTES`) ;
- navigation limitée à 120 requêtes par minute et par IP avant connexion, puis 600 par minute et par compte ;
- connexion : un champ piège invisible et un délai minimal écartent les robots, qui reçoivent le même message qu'un PIN erroné ;
- aucune page indexée par les moteurs de recherche (`robots.txt`, en-tête `X-Robots-Tag`).

Débloquer une adresse (par exemple le réseau d'une agence bloqué par erreur) :

```bash
php artisan robots:debloquer 203.0.113.10
```

Derrière Cloudflare ou un répartiteur, renseigner `TRUSTED_PROXIES` : sinon toutes les requêtes semblent venir du proxy, et le blocage viserait tout le monde.

### Maintenance et cache

```bash
php artisan down --retry=60
php artisan up
php artisan optimize:clear
```

## 7. Sauvegardes

Les sauvegardes se gèrent dans **Administration › Paramètres › Sauvegardes** (superadmin) : création, téléchargement, restauration, choix du dossier. Les 10 plus récentes sont conservées et une sauvegarde automatique est faite chaque nuit à 01:30 (cron `schedule:run`).

Réglages dans `.env` (facultatifs) :

```dotenv
# Dossier initial ; par défaut storage/app/sauvegardes (créé automatiquement)
# SAUVEGARDES_DOSSIER=/home/compte/sauvegardes-advantage
SAUVEGARDES_CONSERVER=10
SAUVEGARDES_AUTOMATIQUE=true
# Si mysqldump / mysql ne sont pas dans le PATH du serveur :
SAUVEGARDES_MYSQLDUMP=/usr/bin/mysqldump
SAUVEGARDES_MYSQL=/usr/bin/mysql
```

- **Dossier par défaut** : `storage/app/sauvegardes` dans le projet, créé à chaque déploiement et conservé par lui. Il se change dans **Paramètres › Sauvegardes** (le choix fait dans l'application prime sur `SAUVEGARDES_DOSSIER`).
- Dossiers refusés par l'application : dans `public/` (les sauvegardes seraient téléchargeables), ou dans le projet ailleurs que sous `storage/app/` (le déploiement remplace tout le reste du projet et les effacerait).
- Une restauration crée d'abord une sauvegarde de l'état actuel (« avant-restauration »), puis remet à niveau le schéma et les permissions.
- Copiez régulièrement les sauvegardes hors du serveur (téléchargement depuis l'application).

En ligne de commande :

```bash
php artisan sauvegarde:creer
```

### Sauvegarde manuelle de secours (sans l'application)

Sauvegarde quotidienne de la base (à planifier chez l'hébergeur ou en cron) :

```bash
mysqldump --single-transaction --routines --triggers -u UTILISATEUR -p BASE | gzip > sauvegarde-$(date +%F).sql.gz
```

`--triggers` est indispensable : sans eux, le journal d'audit ne serait plus protégé après une restauration.

Conserver aussi en lieu sûr, hors du serveur : le fichier `.env`, en particulier `APP_KEY`, ainsi que le dossier `public/uploads` (logo). Sans eux, les données chiffrées de la base sont illisibles.

## 8. Checklist d'ouverture

À cocher une fois, avant d'ouvrir la plateforme aux agents et aux partenaires.

**Serveur**
- [ ] Racine web sur `public/`, HTTPS actif, `.env` en `chmod 600`.
- [ ] `php artisan about` : `Environment: production`, `Debug Mode: OFF`.
- [ ] `php artisan securite:verifier` : « Configuration de production conforme ».
- [ ] `https://votre-domaine.ci/.env` et `https://votre-domaine.ci/composer.json` répondent 403 ou 404.
- [ ] `APP_KEY` généré **et** copié hors du serveur.
- [ ] `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`.
- [ ] `TRUSTED_PROXIES` renseigné si le site est derrière Cloudflare ou un répartiteur (sinon les limites par IP visent le proxy).

**Base de données**
- [ ] `php artisan migrate:status` : toutes les migrations « Ran » (index de performance compris).
- [ ] Les 6 triggers existent : `SHOW TRIGGERS;` (journal d'audit, registre des purges, opérations sur les cartes).
- [ ] `php artisan permissions:synchroniser` exécuté, superadmin créé (`SuperAdminSeeder`).

**Tâches de fond**
- [ ] Les deux cron actifs ; `php artisan schedule:list` affiche 00:10, 01:30, 02:00, 02:30 et 09:00.
- [ ] `php artisan sms:tester <votre numéro>` : SMS reçu avec l'expéditeur « FONTAINE G ».
- [ ] `php artisan queue:failed` vide.

**Sauvegardes**
- [ ] Dossier des sauvegardes : `storage/app/sauvegardes` par défaut, ou un dossier choisi dans Paramètres › Sauvegardes (hors de `public/`, hors du projet ou sous `storage/app/`).
- [ ] Une sauvegarde créée depuis Administration › Paramètres, puis téléchargée et ouverte (`gunzip -t`).

**Application**
- [ ] Nom et logo de l'application réglés dans Administration › Paramètres.
- [ ] Comptes admin et agents créés (PIN transmis en main propre), rôles vérifiés.
- [ ] Partenaires créés avec leur taux, un opérateur par partenaire.
- [ ] Parcours complet testé : activation d'une carte → vérification chez un partenaire → code SMS → validation → transaction visible dans le rapport.

## 9. Déploiement automatique (GitHub Actions)

Un seul workflow : `.github/workflows/deploy.yml`, lancé à chaque push sur `main` (ou par *Actions › Déploiement › Run workflow*). Circuit : travailler sur `developpement`, fusionner dans `main` → déploiement automatique.

### 9.1 Déroulement

1. **Tests** : Pint + suite complète sur MySQL 8. Au moindre échec, rien n'est envoyé.
2. **Build** sur GitHub : `composer install --no-dev` et `npm run build` (ni Node ni Composer nécessaires sur le serveur).
3. `php artisan securite:verifier` sur le serveur (s'il signale un point à corriger : arrêt, site non touché), puis `php artisan down`.
4. **rsync** du projet vers le serveur. Jamais touchés : `.env`, `storage/`, `public/uploads/` (logo), `.git/`, et les fichiers de l'hébergeur (`.user.ini`, `php.ini`, `error_log`, `public/.well-known/`, `public/cgi-bin/`). **Tout autre fichier absent du dépôt est supprimé** (`--delete`) : le dossier des sauvegardes doit donc être hors du projet ou dans `storage/` (contrôlé par `securite:verifier`).
5. Suppression des caches de l'ancienne version (`bootstrap/cache/*.php`), puis `optimize:clear`, `migrate --force`, `permissions:synchroniser`, `optimize`, `queue:restart`, `securite:verifier`, puis `php artisan up`.

Deux déploiements ne tournent jamais en même temps.

**En cas d'échec** à partir de l'étape 3, le site **reste en maintenance** (une base à moitié migrée ne doit jamais servir) : se connecter en SSH, lire l'erreur dans le journal du job, corriger (`php artisan migrate:status`…), puis `php artisan up` — ou pousser un correctif sur `main`.

Revenir à une version antérieure : `git revert` du commit fautif sur `main`, puis push. Une migration déjà passée n'est pas annulée par un revert : prévoir une migration corrective.

### 9.2 Mise en place (une seule fois)

Prérequis : accès SSH au compte (cPanel → *Accès SSH*), base MySQL créée et `.env` de production déposé dans `SERVER_PATH` (§ 2.2, `chmod 600`). Le premier déploiement peut partir d'un dossier sans code : seul le `.env` est exigé. Lancer ensuite une fois `php artisan db:seed --class=SuperAdminSeeder --force` et ajouter les cron (§ 3). Pour les déploiements suivants, `php artisan securite:verifier` doit être conforme : c'est le premier contrôle, qui arrête le déploiement sinon. Le dossier `.git` du serveur n'est plus utilisé par les déploiements.

> Tant que les secrets ci-dessous ne sont pas renseignés, un push sur `main` lance les tests puis échoue à l'étape de connexion SSH, sans rien toucher : aucun risque.

**a) Clé SSH de déploiement**, sur le poste de développement :

```bash
ssh-keygen -t ed25519 -f deploiement_advantage -N "" -C "github-actions-advantage"
```

- `deploiement_advantage.pub` : cPanel → *Accès SSH › Gérer les clés SSH › Importer*, puis **Autoriser** la clé.
- `deploiement_advantage` (clé privée) : secret `SSH_PRIVATE_KEY` ci-dessous, puis **supprimer le fichier** du poste.

**b) Environnement GitHub** : dépôt → *Settings › Environments* → `PRODUCTION` (limiter *Deployment branches* à `main` ; *Required reviewers* facultatif pour valider chaque mise en production).

| Secret | Exemple |
|---|---|
| `SERVER_HOST` | `serveur.hebergeur.com` |
| `SERVER_USER` | `compte` (identifiant cPanel) |
| `SERVER_PATH` | `/home/compte/advantage` (sans `/` final) |
| `SSH_PORT` | facultatif, `22` par défaut |
| `SSH_PRIVATE_KEY` | contenu entier de `deploiement_advantage` |

| Variable (facultative) | Exemple |
|---|---|
| `PHP_BIN` | `/opt/cpanel/ea-php83/root/usr/bin/php`, si `php -v` en SSH n'affiche pas 8.3 ou plus |

Le `.env` de production reste **uniquement sur le serveur**, jamais dans GitHub.
