# API Platform Starter

Un starter Symfony + API Platform prêt à l'emploi, avec authentification JWT par cookie httpOnly.

Tu démarres, tu codes tes ressources, tu ne perds pas 3 jours sur l'auth.

---

## Démarrer en 2 minutes

Il te faut juste **Docker**.

```bash
cp .env.example .env
make install         # build + composer + clés JWT + migrations
make reset           # crée la base + un user admin
```

L'API tourne sur **http://localhost:8080/api**. Doc Swagger sur **http://localhost:8080/api/docs**.

Comptes créés par `make reset` :

| Email                | Mot de passe | Rôle       |
|----------------------|--------------|------------|
| `admin@example.com`  | `adminpass`  | ROLE_ADMIN |
| `user@example.com`   | `userpass`   | ROLE_USER  |

---

## Tester que ça marche

```bash
# 1. Login → pose un cookie httpOnly
curl -i -X POST http://localhost:8080/api/login \
  -H 'Content-Type: application/json' \
  -c /tmp/cookies.txt \
  -d '{"email":"admin@example.com","password":"adminpass"}'
# → 200 OK, Set-Cookie: auth_token=...; httponly; samesite=lax

# 2. Appeler /api/me avec le cookie
curl http://localhost:8080/api/me -b /tmp/cookies.txt

# 3. Logout
curl -i -X POST http://localhost:8080/api/logout -b /tmp/cookies.txt
# → 204, cookie effacé
```

---

## Les endpoints fournis

| Méthode | URL                | Qui peut l'appeler   | À quoi ça sert                      |
|---------|--------------------|----------------------|-------------------------------------|
| POST    | `/api/users`       | Public               | S'inscrire (`email`, `plainPassword`) |
| POST    | `/api/login`       | Public               | Se connecter → pose le cookie JWT   |
| POST    | `/api/logout`      | Public               | Efface le cookie                    |
| GET     | `/api/me`          | Connecté             | Profil de l'utilisateur courant     |
| GET     | `/api/users/{id}`  | Admin ou le user lui-même | Lire un user                   |
| GET     | `/api/docs`        | Public               | Doc Swagger/OpenAPI                 |

---

## Côté client (SPA, React, Vue, etc.)

Le navigateur gère le cookie tout seul — tu n'as **rien à stocker en JavaScript**. Juste `credentials: 'include'` :

```js
// Login
await fetch('/api/login', {
  method: 'POST',
  credentials: 'include',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ email, password }),
});

// Tout appel ultérieur — le cookie est envoyé automatiquement
await fetch('/api/me', { credentials: 'include' });

// Logout
await fetch('/api/logout', { method: 'POST', credentials: 'include' });
```

---

## Commandes Make

> Règle d'or : **toutes les commandes Symfony/composer passent par `make`**, jamais par le PHP du Mac.

### Tous les jours

```bash
make start             # démarre les conteneurs
make stop              # les stoppe
make shell             # ouvre un bash dans le conteneur PHP
make logs              # logs en direct
```

### Base de données

```bash
make migrate           # applique les migrations
make migrate-diff      # génère une nouvelle migration depuis tes entités
make fixtures          # recharge les fixtures
make reset             # drop + create + migrate + fixtures (tout neuf)
```

### Qualité de code

```bash
make test              # PHPUnit
make cs                # check style (dry-run)
make cs-fix            # corrige le style
make analyse           # PHPStan
make qa                # lint + cs + analyse + test
```

### Utilitaires

```bash
make composer c="require mon/bundle"
make console  c="debug:router"
make jwt               # régénère les clés JWT
make clean             # supprime conteneurs, volumes, cache
```

---

## Ajouter une ressource

```bash
make console c="make:entity Article"    # crée l'entité + le repository
make migrate-diff                        # génère la migration
make migrate                             # l'applique
```

Ajoute ensuite `#[ApiResource]` sur ta classe dans `src/Entity/Article.php` et l'API est disponible sur `/api/articles`.

---

## Configuration

Le fichier `.env` couvre tout ce qui est utile. Les trucs qu'on touche vraiment :

| Variable                | À quoi ça sert                                     | Défaut                            |
|-------------------------|----------------------------------------------------|-----------------------------------|
| `NGINX_PORT`            | Port HTTP exposé                                   | `8080`                            |
| `POSTGRES_PORT`         | Port Postgres exposé (pour connect depuis le Mac)  | `5432`                            |
| `JWT_TTL`               | Durée de vie du JWT (secondes)                     | `3600`                            |
| `JWT_COOKIE_SECURE`     | `1` en prod HTTPS, `0` en dev HTTP                 | `0` en dev, `1` dans `.env.example` |
| `JWT_COOKIE_SAMESITE`   | `lax` \| `strict` \| `none`                        | `lax`                             |
| `CORS_ALLOW_ORIGIN`     | Regex des origines autorisées                      | `localhost` + `127.0.0.1`         |

Pour overrider en local sans toucher à `.env`, utilise `.env.local` (gitignoré).

---

## ⚠️ À savoir avant la prod

1. **Passe `JWT_COOKIE_SECURE=1` et sers en HTTPS.** Un cookie `Secure=0` sur HTTP est sniffable.
2. **`CORS_ALLOW_ORIGIN` doit lister explicitement tes origines.** Le wildcard `^.*$` est incompatible avec les cookies — les navigateurs refuseront la réponse.
3. **Le logout n'invalide pas le JWT côté serveur.** Le cookie est effacé chez le client, mais un token volé avant le logout reste valide jusqu'à `exp`. Garde `JWT_TTL` court (1 h par défaut est raisonnable).
4. **CSRF : `SameSite=Lax` suffit pour un SPA same-site.** Si ton SPA tourne sur un domaine différent de l'API (`SameSite=None`), ajoute une protection dédiée (double-submit token ou en-tête custom).
5. **Change la passphrase JWT** (`JWT_PASSPHRASE` dans `.env`) et régénère les clés avec `make jwt` avant prod.

---

## Stack

PHP 8.5 · Symfony 7.4 · API Platform 4.3 · Doctrine ORM 3 · PostgreSQL 16 · Lexik JWT · Nelmio CORS · PHPUnit 13 · PHPStan 2 · PHP-CS-Fixer 3 · Nginx 1.25 · Mailpit (mails de dev sur http://localhost:8025).

---

## Arborescence

```
src/
  Controller/      # LoginController (stub), LogoutController
  DataFixtures/    # AppFixtures (admin + user de démo)
  Entity/          # User (UUID v7, ApiResource)
  Repository/      # UserRepository
  Security/        # JwtCookieSubscriber (pose le cookie sur login)
  State/           # UserPasswordHasherProcessor + MeProvider
config/packages/   # security, lexik_jwt, nelmio_cors, doctrine, …
migrations/        # Migrations Doctrine
tests/Api/         # AuthFlowTest (end-to-end du flow de login)
```
