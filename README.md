# 🚀 Symfony API Platform Starter Kit

Bienvenue dans ce **Starter Kit** conçu pour le développement d'APIs modernes, robustes et hautement scalables. Ce projet repose sur **PHP 8.3**, **Symfony 7** et **API Platform 3**, avec une architecture orientée "Resource-Driven".

## 🏗️ 1. Architecture & Design

Le projet suit une architecture découplée où chaque ressource API est une entité riche, gérée par les composants de Symfony.

* **API Platform 3** : Gère la couche d'exposition REST/JSON-LD. L'accès aux données est piloté par des **State Providers** (lecture) et **State Processors** (écriture), permettant de séparer l'infrastructure de la logique métier.
* **Security (JWT & HttpOnly Cookies)** : Contrairement au stockage LocalStorage classique, ce starter utilise un `JwtCookieSubscriber` pour injecter le token JWT dans un cookie `HttpOnly` et `Secure`. Cela élimine les risques de failles XSS tout en restant compatible avec les clients SPA/Mobile.
* **Doctrine ORM** : Couche d'abstraction pour PostgreSQL, utilisant les attributs PHP 8 pour le mapping.



---

## 🛠️ 2. Installation & Setup

### Pré-requis
* Docker & Docker Compose
* Make (optionnel, mais recommandé)

### Lancement rapide
Le projet est entièrement conteneurisé. Utilisez le `Makefile` pour une installation automatisée :

```bash
# Clone le dépôt
git clone <repository-url>
cd api-platform-starter

# Initialise l'environnement (build docker, composer install, migrations, fixtures)
make install
```

### Configuration
Les variables d'environnement sont gérées dans le fichier `.env`. Pour la production, créez un fichier `.env.local` :
* `DATABASE_URL` : Connexion PostgreSQL.
* `CORS_ALLOW_ORIGIN` : Domaines autorisés à consommer l'API.
* `JWT_SECRET_KEY` / `JWT_PUBLIC_KEY` : Clés pour la signature des tokens.

---

## 💻 3. Implémentation & Développement

### Structure du Code
Le projet impose une séparation stricte des responsabilités :

* `src/Entity` : Modèles de données avec attributs API Platform.
* `src/State` : Logique de traitement (ex: `UserPasswordHasherProcessor` pour le hachage automatique des mots de passe).
* `src/Security` : Gestion de l'authentification et des abonnés aux événements.

### Ajouter une Ressource
Pour créer une nouvelle ressource, utilisez la CLI Symfony :
```bash
docker compose exec php bin/console make:entity --api-resource
```

**Exemple d'implémentation propre (Attributs PHP 8.3) :**
```php
#[ORM\Entity]
#[ApiResource(
    operations: [
        new Get(),
        new Post(processor: UserPasswordHasherProcessor::class)
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:write']]
)]
class User { ... }
```

---

## 🧪 4. Tests & Validation

La qualité du code est assurée par une suite de tests automatisés et des outils d'analyse statique.

### Exécuter les tests
```bash
# Tests unitaires et fonctionnels (PHPUnit)
make test
make test-coverage

# Analyse statique (PHPStan)
make analyse
```

Les tests se trouvent dans le répertoire `tests/`.

---

## 💡 5. Best Practices (Production Ready)

Pour maintenir ce projet à un haut niveau de qualité, voici trois piliers appliqués :

1.  **Sécurité (OWASP)** :
    * Utilisation systématique des **Voters** Symfony pour une gestion fine des droits (ACL).
    * Protection contre les attaques CSRF grâce à l'utilisation de cookies `SameSite: Lax`.
2.  **Performance & Cache** :
    * Utilisation de l'**Eager Loading** dans les requêtes Doctrine pour éviter le problème du "N+1 queries".
    * Activation du cache d'identification (Invalidation via Varnish ou Redis).
3.  **Typage Strict** :
    * `declare(strict_types=1);` dans tous les fichiers.
    * Utilisation des propriétés `readonly` pour les services injectés afin de garantir l'immutabilité.

---
