# Directives & Contexte du Projet — Débriefing CA Sion

Ce document sert de référence de contexte, d'architecture et de directives de style pour tout travail ou intervention de l'IA sur ce projet.

---

## 1. Vue d'ensemble du Projet

Application web d'entretiens et de débriefings de début de saison pour le club d'athlétisme **CA Sion**.
Elle permet aux athlètes de préparer leur bilan / objectifs et aux entraîneurs de mener les entretiens de cadrage, de consigner les arbitrages techniques, d'éditer des synthèses officielles et de suivre l'évolution d'une saison sur l'autre (comparatif N-1).

### Stack Technique
* **Langage & Environnement** : PHP 8.2+ natif (sans framework externe, architecture MVC légère et lisible).
* **Base de données** : SQLite 3 (`data/debriefing.sqlite`, mode WAL, requêtes préparées systématiques avec `?`). Ce fichier est exclu du versionnage Git.
* **Front-end** : HTML5 sémantique, Tailwind CSS (classes utilitaires modernes), Vanilla JavaScript (sans dépendance lourde).
* **Icônes** : Icônes vectorielles SVG intégrées au balisage (éviter les émojis système disparates dans les barres d'outils et tableaux).
* **Scripts utilitaires** : `bin/seed.php` pour générer des jeux de données de test en local via la ligne de commande.

---

## 2. Architecture & Organisation des Fichiers

* `index.php` : Point d'entrée unique et routeur HTTP léger.
* `config/` :
  * `database.php` : Initialisation de la connexion PDO SQLite, migration du schéma (`init_database()`) et création de la saison active par défaut (`seed_initial_data()`).
  * `athletics.php` : **Source de vérité unique** pour les disciplines, groupes d'entraînement, créneaux horaires, coachs et statuts. Accès via le helper `athletics_config($key)`.
* `src/` :
  * `Database.php` : Singleton de gestion de la connexion PDO.
  * `Auth.php` : Gestion des sessions administrateurs, tokens d'accès direct et codes PIN athlètes.
  * `CategoryHelper.php` : Calcul et attribution automatique des catégories (U16, U18, U20, U23, Elite, Masters) en fonction de l'année de naissance et de la saison active.
  * `AdminController.php` : Logique de gestion du tableau de bord, imports CSV, export PDF, synthèses WhatsApp, déverrouillage et gestion des saisons.
  * `InterviewController.php` : Logique de saisie athlète et enregistrement des entretiens.
* `views/` :
  * `admin_dashboard.php` : Tableau de bord principal des entraîneurs avec barre d'outils harmonisée et filtres.
  * `admin_u18_split.php` : Interface d'entretien individuel approfondi (split-screen athlète / coach) avec modal d'historique N-1.
  * `admin_u16_group.php` : Interface de cadrage groupé (2 à 4 athlètes côte à côte) avec modal d'historique N-1.
  * `athlete_interview.php` : Interface mobile/desktop pour la saisie autonome de l'athlète.
  * `pdf_summary.php` & `summary_view.php` : Synthèses d'entretien imprimables et partageables.

---

## 3. Règles Métier & Cycle de Vie

1. **Catégories & Formats d'entretien** :
   * **U16** : Cadrage synthétique pouvant être conduit individuellement ou en groupe (2 à 4 athlètes). Bouton d'accès : `[Entretien →]`.
   * **U18 et plus** : Entretien individuel approfondi en face à face avec confrontation des réponses athlète et coach. Bouton d'accès : `[Entretien →]`.
2. **Cycle des Statuts** :
   * `draft` (Brouillon) : En cours de préparation ou non commencé.
   * `submitted` (Transmis) : L'athlète a soumis ses souhaits pour relecture par le coach.
   * `locked` (Verrouillé) : Entretien clôturé et validé. Peut être déverrouillé à tout moment par l'administrateur ou réouvert par l'athlète sans friction bloquante.
3. **Historique N-1** :
   * Les données de la saison précédente sont automatiquement chargées et visualisables (orientations, volumes, fiertés, objectifs et contrat moral) pour comparer la progression.

---

## 4. Règles de Style, Typographie & Français

Toute génération de texte, libellé d'interface ou message doit respecter rigoureusement ces normes :

* **Langue & Orthographe** : Français soigné, précis et naturel.
* **Typographie** :
  * Pas de majuscules superflues au milieu des phrases ou dans les titres (privilégier la casse de phrase : *Tableau de bord des entretiens* plutôt que *Tableau De Bord Des Entretiens*).
  * Privilégier le mot « **et** » plutôt que le symbole commercial « & » dans les textes rédactionnels et explications.
  * Éviter le symbole « % » dans le corps du texte (préférer « pour cent » ou une formulation naturelle).
  * Respecter les espaces insécables avant les ponctuations doubles (`:`, `!`, `?`, `»`, `«`).
* **Micro-copies & Ergonomie de l'UI** :
  * Libellés courts, clairs et orientés action (ex : *Entretien*, *Enregistrer*, *Copier le lien*, *Déverrouiller*).
  * Boutons d'action harmonisés en hauteur et largeur pour garantir un alignement parfait dans les tableaux.
  * Utiliser des infobulles explicatives (`title="..."`) sur les icônes discrètes plutôt que d'encombrer l'écran de texte superflu.
  * Ne jamais afficher de boutons de simulation / seed dans l'interface de production.

---

## 5. Directives de Développement pour l'IA

* Toujours vérifier la syntaxe PHP via `php -l <fichier>` après toute modification.
* Ne jamais altérer la structure de la base de données sans adapter `config/database.php` et `bin/seed.php`.
* Maintenir l'indépendance de `config/athletics.php` en tant que source unique de données sportives.
* Assurer une compatibilité mobile et desktop réactive (Tailwind CSS propre, touch targets confortables).
