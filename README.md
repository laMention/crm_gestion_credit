# CRM – Gestion de Crédit

## 1. Présentation du projet

CRM – Gestion de Crédit est une application backend destinée à digitaliser la gestion des opérations d'une institution financière disposant de plusieurs agences en Côte d'Ivoire.

L'application couvre le cycle de gestion des clients, des comptes bancaires, des opérations financières et des crédits, depuis l'enregistrement d'une demande jusqu'au remboursement du crédit.

Les montants sont exprimés en francs CFA (XOF).

## 2. Technologies utilisées

| Technologie | Utilisation |
|---|---|
| PHP version 8.3 | Langage backend |
| Laravel 13 | Framework backend |
| MySQL | Système de gestion de base de données |
| InnoDB | Moteur de stockage relationnel |
| Git / GitHub | Gestion du code source |

## 3. Architecture fonctionnelle

L'application s'articule autour des modules suivants :

- Gestion des agences.
- Gestion des utilisateurs et des rôles.
- Gestion des clients.
- Gestion des comptes courants, épargne et crédit.
- Gestion des transactions : dépôts, retraits, virements, déboursements et remboursements.
- Gestion des demandes et du suivi des crédits.
- Reporting quotidien par agence et au niveau du réseau.
- Traçabilité des opérations et des actions utilisateurs.

## 4. Installation

### Prérequis

- PHP compatible avec Laravel 13.
- Composer.
- MySQL.
- Git.
- Extensions PHP requises par Laravel.

### Récupération du projet

```bash
git clone https://github.com/laMention/crm_gestion_credit.git
cd crm_gestion_credit
```

### Installation des dépendances

```bash
composer install
```

### Configuration de l'environnement

```bash
cp .env.example .env
```

Configurer les paramètres de connexion à la base de données dans le fichier `.env` :

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=crm_gestion_credit
DB_USERNAME=root
DB_PASSWORD=
```

### Génération de la clé

```bash
php artisan key:generate
```

### Exécution des migrations

```bash
php artisan migrate
```

### Lancement du serveur

```bash
php artisan serve
```

L'application sera accessible à l'adresse :

http://127.0.0.1:8000

## 5. Choix techniques et justifications

### Laravel 13

Laravel a été retenu pour sa structure MVC, son système de migrations, son ORM Eloquent, ses mécanismes de validation et ses fonctionnalités de sécurité.

### MySQL avec InnoDB

MySQL est utilisé comme système de gestion de base de données relationnelle.

Le moteur InnoDB est retenu pour :
- La gestion des transactions SQL.
- Le respect des contraintes d'intégrité référentielle.
- Les clés étrangères.
- Les mécanismes de verrouillage nécessaires aux opérations financières concurrentes.

### Gestion des montants

Les montants financiers sont stockés avec le type DECIMAL(15,2), afin d'éviter les imprécisions liées aux nombres flottants (float).

### Organisation des données

La base de données est organisée autour des entités principales : agences, utilisateurs, clients, comptes, transactions et reporting. Des tables complémentaires assurent notamment la gestion des crédits et la traçabilité.

### Sécurité et intégrité financière

Les opérations financières devront être exécutées dans des transactions SQL afin de garantir leur atomicité.

Des contrôles d'accès par rôle et par agence seront appliqués pour limiter les opérations et les données accessibles à chaque utilisateur.

## 6. Lancement des tests

```bash
php artisan test
```

## 7. Limites connues

À ce stade du développement, les fonctionnalités métier ne sont pas encore toutes implémentées.

Les éléments restant à développer et à valider comprennent notamment :
- L'authentification et la gestion des autorisations.
- Le contrôle d'accès aux données par agence.
- Le cycle de validation et de déboursement des crédits.
- La sécurisation des opérations financières concurrentes.
- La prévention des transactions en double.
- La génération des tableaux d'amortissement.
- Le reporting quotidien et son mécanisme de recalcul.
- La documentation complète de l'API.
- Les tests automatisés des principaux scénarios métier.

Ces éléments seront implémentés progressivement et documentés au fur et à mesure de leur réalisation.
