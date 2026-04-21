# API Platform Starter

Starterkit Symfony 7.4 + API Platform 4.3 prêt à l'emploi.

## Stack

- **PHP 8.5**
- **Symfony 7.4**
- **API Platform 4.3**
- **Doctrine ORM 3** + PostgreSQL 16
- **JWT** via LexikJWTAuthenticationBundle
- **CORS** via NelmioCorsBundle
- **PHPUnit 13**
- Nginx 1.25 · Mailpit (catcher mail dev)

## Prérequis

- Docker + Docker Compose

> Ne pas utiliser le PHP du host pour composer/symfony — tout passe par les conteneurs.

## Installation

```bash
cp .env.example .env
# Ajuster les variables dans .env si nécessaire
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
make reset          # Drop BDD + migrate + fixtures

make test           # PHPUnit
make qa             # lint + cs + analyse + test

make composer c="require mon/bundle"
make console  c="debug:router"
```

## Variables d'environnement

| Variable | Description | Défaut |
|---|---|---|
| `DATABASE_URL` | DSN PostgreSQL | — |
| `JWT_SECRET_KEY` | Chemin clé privée JWT | `%kernel.project_dir%/config/jwt/private.pem` |
| `JWT_PUBLIC_KEY` | Chemin clé publique JWT | `%kernel.project_dir%/config/jwt/public.pem` |
| `JWT_PASSPHRASE` | Passphrase des clés JWT | — |
| `CORS_ALLOW_ORIGIN` | Regex origines CORS autorisées | `^https?://(localhost\|127\.0\.0\.1)(:[0-9]+)?$` |
| `NGINX_PORT` | Port HTTP exposé | `8080` |

## Structure

```
src/
  ApiResource/    # Ressources API Platform
  Entity/         # Entités Doctrine
  Repository/     # Repositories
config/
  packages/       # Configuration bundles
  routes/         # Routes (security, api_platform)
migrations/       # Migrations Doctrine
tests/            # Tests PHPUnit
```
