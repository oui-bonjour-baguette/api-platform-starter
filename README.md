# API Platform Starter

Starterkit Symfony 7.4 + API Platform 4.3 prêt à l'emploi, avec authentification JWT via cookie httpOnly.

## Stack

- **PHP 8.5**
- **Symfony 7.4**
- **API Platform 4.3**
- **Doctrine ORM 3** + PostgreSQL 16
- **UUID v7** pour les identifiants (`symfony/uid`)
- **JWT** via LexikJWTAuthenticationBundle, transporté en cookie `httpOnly`
- **CORS** via NelmioCorsBundle (`allow_credentials: true`)
- **PHPUnit 13** — **PHPStan 2** — **PHP-CS-Fixer 3**
- Nginx 1.25 · Mailpit (catcher mail dev)

## Prérequis

- Docker + Docker Compose

> Ne pas utiliser le PHP du host pour composer/symfony — tout passe par les conteneurs.

## Installation

```bash
cp .env.example .env
# Ajuster les variables dans .env si nécessaire (notamment JWT_COOKIE_SECURE=0 en local)
make install
```

`make install` exécute dans l'ordre : build Docker → démarrage → `composer install` → génération des clés JWT → migrations.

## Commandes courantes

```bash
make start          # Démarrer les conteneurs
make stop           # Arrêter les conteneurs
make shell          # Shell PHP dans le conteneur

make jwt            # Régénérer les clés JWT
make migrate        # Lancer les migrations Doctrine
make migrate-diff   # Générer une migration depuis les entités
make reset          # Drop BDD + migrate + fixtures (admin@example.com / adminpass)

make test           # PHPUnit
make qa             # lint + cs + analyse + test

make composer c="require mon/bundle"
make console  c="debug:router"
```

## Authentification

Le JWT est **toujours transporté via un cookie httpOnly** (`auth_token` par défaut). Il ne transite jamais dans le corps JSON ni dans l'en-tête `Authorization`.

### Endpoints

| Méthode | URL               | Description                                   | Accès            |
|--------:|-------------------|-----------------------------------------------|------------------|
| POST    | `/api/users`      | Inscription (`email` + `plainPassword`)       | Public           |
| POST    | `/api/login`      | Connexion (`email` + `password`) → cookie     | Public           |
| POST    | `/api/logout`     | Efface le cookie (logout stateless)           | Public           |
| GET     | `/api/me`         | Utilisateur courant                           | Authentifié      |
| GET     | `/api/users/{id}` | Récupère un user                              | Admin ou soi-même|
| GET     | `/api/docs`       | Documentation OpenAPI                         | Public           |

### Flux côté client (SPA)

```js
await fetch('/api/login', {
    method: 'POST',
    credentials: 'include',           // ← indispensable pour recevoir le cookie
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
});

await fetch('/api/me', { credentials: 'include' });  // le cookie suit automatiquement

await fetch('/api/logout', { method: 'POST', credentials: 'include' });
```

### Test rapide en curl

```bash
BASE=http://localhost:8080/api
JAR=/tmp/api-cookies.txt && rm -f "$JAR"

curl -s -X POST "$BASE/users" -H 'Content-Type: application/ld+json' \
    -d '{"email":"alice@example.com","plainPassword":"supersecret"}' | jq

curl -i -X POST "$BASE/login" -H 'Content-Type: application/json' \
    --cookie-jar "$JAR" \
    -d '{"email":"alice@example.com","password":"supersecret"}'
# → 200, Set-Cookie: auth_token=eyJ...; httponly; samesite=lax  (pas de `token` dans le body)

curl -s "$BASE/me" --cookie "$JAR" -H 'Accept: application/ld+json' | jq
curl -i -X POST "$BASE/logout" --cookie "$JAR" --cookie-jar "$JAR"   # → 204 + cookie expiré
```

## Variables d'environnement

| Variable | Description | Défaut |
|---|---|---|
| `DATABASE_URL` | DSN PostgreSQL | — |
| `JWT_SECRET_KEY` | Chemin clé privée JWT | `%kernel.project_dir%/config/jwt/private.pem` |
| `JWT_PUBLIC_KEY` | Chemin clé publique JWT | `%kernel.project_dir%/config/jwt/public.pem` |
| `JWT_PASSPHRASE` | Passphrase des clés JWT | — |
| `JWT_TTL` | Durée de vie du JWT en secondes | `3600` |
| `JWT_COOKIE_NAME` | Nom du cookie qui transporte le JWT | `auth_token` |
| `JWT_COOKIE_SECURE` | Cookie `Secure` (0 en dev HTTP, 1 en prod HTTPS) | `0` (dev), `1` (example) |
| `JWT_COOKIE_SAMESITE` | `lax` \| `strict` \| `none` | `lax` |
| `JWT_COOKIE_PATH` | Path du cookie | `/` |
| `JWT_COOKIE_DOMAIN` | Domaine du cookie (vide = host-only) | `` |
| `CORS_ALLOW_ORIGIN` | Regex origines CORS autorisées | `^https?://(localhost\|127\.0\.0\.1)(:[0-9]+)?$` |
| `NGINX_PORT` | Port HTTP exposé | `8080` |

## Sécurité — tradeoffs et points de vigilance

1. **CSRF résiduel.** `SameSite=Lax` bloque la classe CSRF classique, mais pas tout. Si vous passez `SameSite=None` (SPA sur un domaine différent de l'API), **ajoutez** une protection dédiée (double-submit cookie ou en-tête custom validé côté serveur).
2. **`JWT_COOKIE_SECURE=0` uniquement en dev.** En prod le cookie DOIT être `Secure` (HTTPS obligatoire).
3. **`SameSite=None` impose `Secure=true`** (contrainte navigateur).
4. **CORS wildcard incompatible avec `allow_credentials: true`.** Ne jamais mettre `CORS_ALLOW_ORIGIN='^.*$'` — le navigateur refusera la réponse.
5. **Logout stateless.** Le cookie est effacé côté client, mais un JWT copié avant logout reste techniquement valide jusqu'à `exp`. Gardez `JWT_TTL` court (1 h par défaut). Pour une révocation stricte, ajoutez une blacklist (hors scope).
6. **Cookie host-only par défaut** (`JWT_COOKIE_DOMAIN=` vide). Pour partager entre sous-domaines, mettez `JWT_COOKIE_DOMAIN=.example.com` — mesurez le blast radius.
7. **L'en-tête `Authorization` n'est pas accepté.** L'extractor Lexik correspondant est désactivé dans `config/packages/lexik_jwt_authentication.yaml`. Si vous le réactivez, remettez aussi `Authorization` dans `allow_headers` de Nelmio CORS.
8. **Le JWT n'apparaît jamais dans le body JSON.** Le `JwtCookieSubscriber` le retire systématiquement — ne pas réactiver `lexik_jwt_authentication.set_cookies` (double-écriture).

## Structure

```
src/
  ApiResource/    # Ressources API Platform (DTO)
  Controller/     # Controllers custom (ex. LogoutController)
  DataFixtures/   # Fixtures Doctrine
  Entity/         # Entités Doctrine (User)
  Repository/     # Repositories
  Security/       # JwtCookieSubscriber
  State/          # Processors / Providers API Platform
config/
  packages/       # Configuration bundles
  routes/         # Routes (security, api_platform)
migrations/       # Migrations Doctrine
tests/            # Tests PHPUnit
```
