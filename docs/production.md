# Mise en production — ADVANTAGE

Commandes à exécuter sur le serveur de production (hébergement mutualisé cPanel ou VPS), dans le dossier du projet. Remplacez `/home/compte/advantage` par le chemin réel.

> **Règles d'or**
> - `composer install`, **jamais** `composer update` en production.
> - `php artisan migrate --force` : les migrations sont les seules à modifier la base.
> - `php artisan permissions:synchroniser` à **chaque** déploiement (additif : ne retire jamais les droits réglés dans Paramètres).
> - Ne jamais lancer `migrate:fresh`, `migrate:reset`, `db:wipe` ni `db:seed` complet en production.

## 1. Prérequis du serveur

- PHP **8.3** ou plus, avec les extensions `pdo_mysql`, `mbstring`, `openssl`, `intl`, `bcmath`, `fileinfo`, `ctype`, `tokenizer`, `xml`, `curl`.
- MySQL **8.0** ou plus. L'utilisateur MySQL doit avoir le privilège **TRIGGER** : le journal d'audit et le registre des purges sont protégés par des triggers créés par les migrations.
- Si la journalisation binaire est active (fréquent en mutualisé), les triggers exigent `log_bin_trust_function_creators = 1` ou le privilège SUPER : à demander à l'hébergeur si la migration échoue sur `CREATE TRIGGER`.
- HTTPS actif (certificat SSL) sur le domaine.
- La racine web du domaine doit pointer vers le dossier `public/` du projet (jamais vers la racine du projet).

Vérifier la version de PHP en ligne de commande (elle peut différer de celle du site) :

```bash
php -v
php -m | grep -i -E "pdo_mysql|mbstring|intl|bcmath"
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

Clé HMAC de la plateforme (à coller dans `PLATEFORME_CLE_HMAC`, **à ne jamais changer ensuite**) :

```bash
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

Valeurs à renseigner dans `.env` :

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine.ci
LOG_LEVEL=warning

DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
QUEUE_CONNECTION=database
CACHE_STORE=database

SUPERADMIN_NOM="Super Administrateur"
SUPERADMIN_NOM_UTILISATEUR=superadmin
SUPERADMIN_MOT_DE_PASSE=<mot de passe fort, 12 caractères minimum>
PLATEFORME_CLE_HMAC=<clé générée ci-dessus>

SMS_DRIVER=ticafrique
SMS_EXPEDITEUR=ADVANTAGE
TICAFRIQUE_SMS_API_URL=https://sms.ticafrique.ci/api/v1/sms/send
TICAFRIQUE_SMS_API_KEY=<clé API TICAFRIQUE>
TICAFRIQUE_SMS_SENDER_ID=<expéditeur validé par TICAFRIQUE>

# Uniquement derrière un proxy / CDN (Cloudflare, répartiteur…)
TRUSTED_PROXIES=
```

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

Le seeder du superadmin est idempotent : relancé, il ne change jamais le mot de passe d'un compte existant.

### 2.5 Droits sur les dossiers

```bash
chmod -R 775 storage bootstrap/cache
```

### 2.6 Mise en cache (performances)

```bash
php artisan optimize
```

## 3. Tâches planifiées (cron)

Deux entrées cron (cPanel → *Tâches Cron*), exécutées chaque minute :

```bash
* * * * * cd /home/compte/advantage && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/compte/advantage && php artisan queue:work --tries=3 --sleep=1 --max-time=55 >> /dev/null 2>&1
```

- La première lance les tâches planifiées :
  - 00:10 : les cartes échues passent au statut « expirée » ;
  - 02:00 : purge du journal d'audit (rétention de 14 jours) ;
  - 02:30 : purge des codes de validation et des SMS de plus de 90 jours (ceux liés à une transaction sont conservés) ;
  - 09:00 : SMS d'alerte aux titulaires dont la carte expire dans 3, 2 ou 1 mois (une seule fois par palier ; désactivable avec `ALERTES_EXPIRATION_SMS=false`).
- La seconde traite la file d'attente : envoi des SMS (codes OTP, alertes). Le processus reste à l'écoute 55 secondes puis s'arrête (`--max-time=55`) avant d'être relancé la minute suivante : un code OTP part en 1 à 2 secondes, et l'hébergement mutualisé n'a jamais de processus permanent. (Avec `--stop-when-empty`, un code pourrait attendre jusqu'à une minute.)

Vérifier la planification :

```bash
php artisan schedule:list
```

## 4. Chaque déploiement (mise à jour)

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

### Maintenance et cache

```bash
php artisan down --retry=60
php artisan up
php artisan optimize:clear
```

## 7. Sauvegardes

Sauvegarde quotidienne de la base (à planifier chez l'hébergeur ou en cron) :

```bash
mysqldump --single-transaction --routines --triggers -u UTILISATEUR -p BASE | gzip > sauvegarde-$(date +%F).sql.gz
```

`--triggers` est indispensable : sans eux, le journal d'audit ne serait plus protégé après une restauration.

Conserver aussi en lieu sûr, hors du serveur : le fichier `.env`, en particulier `APP_KEY` et `PLATEFORME_CLE_HMAC`. Sans eux, les données chiffrées de la base sont illisibles.
