# P002 — DÉPLOIEMENT VPS GUIDÉ

Ce guide est conçu pour être exécuté ligne par ligne après trois validations : branche publiée, GitHub Actions entièrement verte et fusion sur `main` autorisée par le fondateur.

## 1. Ouvrir le dépôt et vérifier son état

```bash
cd /var/www/html/wasplex-core
git status --short
git branch --show-current
git rev-parse HEAD
```

`git status --short` ne doit rien afficher. Si une ligne apparaît, arrêter et demander de l’aide avant de continuer.

## 2. Préparer les repères et sauvegarder PostgreSQL

```bash
WASPLEX_ROOT=/var/www/html/wasplex-core
WASPLEX_PREVIOUS_COMMIT=$(git rev-parse HEAD)
WASPLEX_DB_NAME=$(sed -n 's/^DB_DATABASE=//p' "$WASPLEX_ROOT/apps/platform/.env" | tail -n 1 | tr -d '\r"')
WASPLEX_APP_URL=$(sed -n 's/^APP_URL=//p' "$WASPLEX_ROOT/apps/platform/.env" | tail -n 1 | tr -d '\r"')
WASPLEX_APP_URL=${WASPLEX_APP_URL%/}
WASPLEX_BACKUP_DIR=/var/backups/wasplex
WASPLEX_BACKUP_FILE="$WASPLEX_BACKUP_DIR/p002-$(date -u +%Y%m%dT%H%M%SZ).dump"

sudo install -d -o postgres -g postgres -m 700 "$WASPLEX_BACKUP_DIR"
sudo -u postgres pg_dump --format=custom --file="$WASPLEX_BACKUP_FILE" "$WASPLEX_DB_NAME"
sudo test -s "$WASPLEX_BACKUP_FILE" && echo "Sauvegarde P002 vérifiée : $WASPLEX_BACKUP_FILE"
```

La dernière commande doit afficher `Sauvegarde P002 vérifiée`. Sinon, arrêter.

## 3. Activer la maintenance et récupérer `main`

```bash
cd "$WASPLEX_ROOT/apps/platform"
php8.4 artisan down --retry=60

cd "$WASPLEX_ROOT"
git fetch origin
git switch main
git pull --ff-only origin main
```

## 4. Installer et construire

```bash
cd "$WASPLEX_ROOT/apps/platform"
export COMPOSER_ALLOW_SUPERUSER=1
php8.4 /usr/local/bin/composer-wasplex install --no-interaction --prefer-dist --no-progress --optimize-autoloader
npm ci
npm run build
```

Chaque commande doit se terminer sans erreur.

## 5. Migrer et initialiser P002

```bash
php8.4 artisan migrate --force
php8.4 artisan ledger:bootstrap-core
php8.4 artisan identity:bootstrap-founder admin@wasplex.com
php8.4 artisan optimize
php8.4 artisan queue:restart
```

`ledger:bootstrap-core` ne crée aucun solde et aucune transaction financière. La commande fondatrice ajoute les capacités P002 au compte nominatif existant.

## 6. Rouvrir et vérifier le service

```bash
sudo systemctl reload php8.4-fpm
sudo systemctl reload nginx
php8.4 artisan up

curl --fail --silent --show-error "$WASPLEX_APP_URL/up"
curl --fail --silent --show-error "$WASPLEX_APP_URL/api/health"
```

Les deux commandes `curl` doivent retourner un état sain. Vérifier ensuite manuellement la connexion, Mon Espace et la Console fondateur avec MFA.

## 7. Vérification P002 sans créer de valeur

```bash
php8.4 artisan tinker --execute="echo 'types=' . App\\Modules\\Ledger\\Infrastructure\\Models\\LedgerAccountType::query()->count() . PHP_EOL; echo 'journaux=' . App\\Modules\\Ledger\\Infrastructure\\Models\\LedgerJournal::query()->count() . PHP_EOL; echo 'transactions=' . App\\Modules\\Ledger\\Infrastructure\\Models\\LedgerTransaction::query()->count() . PHP_EOL;"
```

Le résultat attendu juste après P002 est au moins `types=5`, `journaux=2` et `transactions=0`.

## 8. Rollback sûr avant toute écriture réelle

Exécuter cette section uniquement si le déploiement échoue et si la vérification précédente indique `transactions=0`.

```bash
cd "$WASPLEX_ROOT/apps/platform"
php8.4 artisan down --retry=60
php8.4 artisan migrate:rollback --step=1 --force

cd "$WASPLEX_ROOT"
git switch -c "rollback/p002-$(date -u +%Y%m%dT%H%M%SZ)" "$WASPLEX_PREVIOUS_COMMIT"

cd "$WASPLEX_ROOT/apps/platform"
php8.4 /usr/local/bin/composer-wasplex install --no-interaction --prefer-dist --no-progress --optimize-autoloader
npm ci
npm run build
php8.4 artisan optimize
php8.4 artisan queue:restart
php8.4 artisan up
```

Si `transactions` est supérieur à zéro, ne pas lancer le rollback de migration. Conserver la maintenance et demander une restauration contrôlée depuis `WASPLEX_BACKUP_FILE`.
