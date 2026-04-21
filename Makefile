# ══════════════════════════════════════════════════════════════════════════════
#  API Platform Starter — Makefile
#  Usage : make <target>
# ══════════════════════════════════════════════════════════════════════════════

.DEFAULT_GOAL := help
.PHONY: help install start stop restart build shell shell-nginx logs \
        jwt migrate fixtures reset test lint cs-fix analyse clean

DC      = docker compose
PHP     = $(DC) exec -u $$(id -u):$$(id -g) php
CONSOLE = $(PHP) php bin/console

# ── Couleurs ──────────────────────────────────────────────────────────────────
BOLD  = \033[1m
GREEN = \033[0;32m
CYAN  = \033[0;36m
GRAY  = \033[0;90m
RESET = \033[0m

# ══════════════════════════════════════════════════════════════════════════════
#  AIDE
# ══════════════════════════════════════════════════════════════════════════════

help: ## Affichage de l'aide
	@echo ""
	@echo "  $(BOLD)API Platform Starter$(RESET)"
	@echo ""
	@awk 'BEGIN {FS = ":.*##"} /^[a-zA-Z_-]+:.*##/ \
		{ printf "  $(CYAN)%-18s$(RESET) $(GRAY)%s$(RESET)\n", $$1, $$2 }' $(MAKEFILE_LIST)
	@echo ""

# ══════════════════════════════════════════════════════════════════════════════
#  INSTALLATION
# ══════════════════════════════════════════════════════════════════════════════

install: ## 🚀 Installation complète du projet
	@echo "$(BOLD)$(GREEN)▶ Installation...$(RESET)"
	@[ -f .env ] || cp .env.example .env && echo "  .env créé depuis .env.example"
	$(DC) build --no-cache
	$(DC) up -d
	$(PHP) composer install
	@$(MAKE) jwt
	@$(MAKE) migrate
	@echo "$(GREEN)✔ Projet prêt sur http://localhost:$$(grep NGINX_PORT .env | cut -d= -f2)$(RESET)"

# ══════════════════════════════════════════════════════════════════════════════
#  DOCKER
# ══════════════════════════════════════════════════════════════════════════════

start: ## Démarrer les conteneurs
	$(DC) up -d

stop: ## Arrêter les conteneurs
	$(DC) down

restart: ## Redémarrer les conteneurs
	$(DC) restart

build: ## Rebuilder l'image PHP
	$(DC) build php

logs: ## Afficher les logs en temps réel
	$(DC) logs -f

shell: ## Ouvrir un shell dans le conteneur PHP
	$(PHP) bash

shell-nginx: ## Ouvrir un shell dans le conteneur Nginx
	$(DC) exec nginx sh

# ══════════════════════════════════════════════════════════════════════════════
#  SYMFONY
# ══════════════════════════════════════════════════════════════════════════════

jwt: ## Générer les clés JWT
	@echo "$(CYAN)▶ Génération des clés JWT...$(RESET)"
	@mkdir -p config/jwt
	$(CONSOLE) lexik:jwt:generate-keypair --overwrite
	@echo "$(GREEN)✔ Clés JWT générées$(RESET)"

migrate: ## Lancer les migrations Doctrine
	@echo "$(CYAN)▶ Migrations...$(RESET)"
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration

migrate-diff: ## Générer une migration depuis les entités
	$(CONSOLE) doctrine:migrations:diff

fixtures: ## Charger les fixtures
	$(CONSOLE) doctrine:fixtures:load --no-interaction

reset: ## 🔄 Réinitialiser la base de données
	@echo "$(CYAN)▶ Reset de la base de données...$(RESET)"
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	@$(MAKE) migrate
	@$(MAKE) fixtures

cache-clear: ## Vider le cache Symfony
	$(CONSOLE) cache:clear

# ══════════════════════════════════════════════════════════════════════════════
#  QUALITÉ DE CODE
# ══════════════════════════════════════════════════════════════════════════════

test: ## ✅ Lancer les tests PHPUnit
	@echo "$(CYAN)▶ Tests...$(RESET)"
	$(PHP) php bin/phpunit --testdox

test-coverage: ## Lancer les tests avec couverture de code
	$(PHP) php bin/phpunit --coverage-html var/coverage

lint: ## Vérifier la syntaxe PHP et Twig
	$(CONSOLE) lint:yaml config
	$(CONSOLE) lint:container
	$(PHP) php bin/console doctrine:schema:validate --skip-sync

cs: ## Vérifier le style de code (PHP CS Fixer)
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix: ## Corriger automatiquement le style de code
	$(PHP) vendor/bin/php-cs-fixer fix

analyse: ## Analyser le code avec PHPStan
	$(PHP) vendor/bin/phpstan analyse

qa: lint cs analyse test ## 🔍 Lancer tous les contrôles qualité

# ══════════════════════════════════════════════════════════════════════════════
#  UTILITAIRES
# ══════════════════════════════════════════════════════════════════════════════

composer: ## Lancer une commande composer (ex: make composer c="require package")
	$(PHP) composer $(c)

console: ## Lancer une commande Symfony (ex: make console c="debug:router")
	$(CONSOLE) $(c)

clean: ## 🧹 Supprimer les conteneurs, volumes et cache
	@echo "$(CYAN)▶ Nettoyage...$(RESET)"
	$(DC) down -v --remove-orphans
	rm -rf var/cache/* var/log/* var/coverage
	@echo "$(GREEN)✔ Nettoyage terminé$(RESET)"
