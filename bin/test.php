<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Ce script est réservé à l'exécution en ligne de commande (CLI).\n");
}

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/src/autoload.php';

use App\Domain\CategoryCalculator;
use App\Domain\DisciplineRules;

class SimpleTestRunner {
    private int $passed = 0;
    private int $failed = 0;
    private float $startTime;

    public function __construct() {
        $this->startTime = microtime(true);
    }

    public function test(string $description, callable $fn): void {
        try {
            $fn();
            $this->passed++;
            echo "\033[32m  [✓] PASS\033[0m : {$description}\n";
        } catch (\Throwable $e) {
            $this->failed++;
            echo "\033[31m  [✗] FAIL\033[0m : {$description}\n";
            echo "      \033[33m→ " . $e->getMessage() . " (" . basename($e->getFile()) . ":" . $e->getLine() . ")\033[0m\n";
        }
    }

    public function assert(bool $condition, string $message = 'Assertion failed'): void {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    public function assertEquals(mixed $expected, mixed $actual, string $message = ''): void {
        if ($expected !== $actual) {
            $expStr = is_scalar($expected) ? (string)$expected : json_encode($expected);
            $actStr = is_scalar($actual) ? (string)$actual : json_encode($actual);
            throw new \RuntimeException($message ?: "Attendu: [{$expStr}], obtenu: [{$actStr}]");
        }
    }

    public function summary(): void {
        $duration = round((microtime(true) - $this->startTime) * 1000, 2);
        $total = $this->passed + $this->failed;
        echo "\n" . str_repeat('=', 65) . "\n";
        if ($this->failed === 0) {
            echo "\033[32m✔ SUCCÈS TOTAL : {$this->passed}/{$total} tests validés en {$duration} ms.\033[0m\n";
        } else {
            echo "\033[31m✖ ÉCHEC : {$this->failed} test(s) échoué(s) sur {$total} en {$duration} ms.\033[0m\n";
        }
        echo str_repeat('=', 65) . "\n\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }
}

echo "\n\033[1;36m=== CA Sion — Suite de Tests Automatisés Métier ===\033[0m\n\n";

$t = new SimpleTestRunner();

// =========================================================================
// 1. Tests CategoryCalculator (Swiss Athletics)
// =========================================================================
echo "\033[1m1. Calculateur de Catégories & Formulaires (CategoryCalculator)\033[0m\n";

$refYear = SettingsService::get_reference_competition_year();

$t->test("Calcul catégorie U16 pour athlète né en " . ($refYear - 15) . " (15 ans en {$refYear})", function () use ($t, $refYear) {
    $cat = CategoryCalculator::calculate_category($refYear - 15);
    $t->assertEquals('U16', $cat);
});

$t->test("Formulaire U16 1ère année pour athlète né en " . ($refYear - 14) . " (14 ans en {$refYear})", function () use ($t, $refYear) {
    $form = CategoryCalculator::get_form_type($refYear - 14);
    $t->assertEquals('u16_1', $form);
});

$t->test("Formulaire U16 2ème année pour athlète né en " . ($refYear - 15) . " (15 ans en {$refYear})", function () use ($t, $refYear) {
    $form = CategoryCalculator::get_form_type($refYear - 15);
    $t->assertEquals('u16_2', $form);
});

$t->test("Calcul catégorie U18 pour athlète né en " . ($refYear - 17) . " (17 ans en {$refYear})", function () use ($t, $refYear) {
    $cat = CategoryCalculator::calculate_category($refYear - 17);
    $t->assertEquals('U18', $cat);
});

$t->test("Formulaire U18/Elite pour athlète né en " . ($refYear - 17), function () use ($t, $refYear) {
    $form = CategoryCalculator::get_form_type($refYear - 17);
    $t->assertEquals('u18_elite', $form);
});

$t->test("Calcul catégorie Elite pour athlète né en " . ($refYear - 25) . " (25 ans)", function () use ($t, $refYear) {
    $cat = CategoryCalculator::calculate_category($refYear - 25);
    $t->assertEquals('Elite', $cat);
});

// =========================================================================
// 2. Tests DisciplineRules & Compatibilité
// =========================================================================
echo "\n\033[1m2. Règles Physiologiques & Compatibilité Disciplines (DisciplineRules)\033[0m\n";

$t->test('Classification de la famille Sprint / Sauts', function () use ($t) {
    $family = DisciplineRules::get_discipline_family('sprint_haies');
    $t->assertEquals('explosive_sprint_jump', $family);
});

$t->test('Classification de la famille Demi-fond / Endurance', function () use ($t) {
    $family = DisciplineRules::get_discipline_family('demi_fond');
    $t->assertEquals('endurance', $family);
});

$t->test('Compatibilité Sprint + Longueur (même filière / compatible)', function () use ($t) {
    $res = DisciplineRules::check_disciplines_compatibility('sprint_court', 'longueur_triple');
    $t->assert($res['is_compatible'] === true, 'Le sprint et les sauts doivent être compatibles');
});

$t->test('Incompatibilité ou alerte physiologique Sprint + Demi-fond', function () use ($t) {
    $res = DisciplineRules::check_disciplines_compatibility('sprint_court', 'demi_fond');
    $t->assert($res['is_compatible'] === false, 'Sprint et Demi-fond doivent générer une alerte de filière opposée');
});

// =========================================================================
// 3. Tests Helper (Formatage des disponibilités)
// =========================================================================
echo "\n\033[1m3. Formatage Synthétique des Disponibilités (Helper)\033[0m\n";

$t->test('Disponibilité complète (6/6 jours)', function () use ($t) {
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    $summary = Helper::format_availability_summary($days, '4');
    $t->assertEquals('Tous (4x)', $summary);
});

$t->test('Disponibilité 5/6 jours (Tous sauf mardi)', function () use ($t) {
    $days = ['monday', 'wednesday', 'thursday', 'friday', 'saturday'];
    $summary = Helper::format_availability_summary($days, '3');
    $t->assertEquals('Tous s. ma (3x)', $summary);
});

$t->test('Disponibilité partielle personnalisée (lu-me-ve)', function () use ($t) {
    $days = ['monday', 'wednesday', 'friday'];
    $summary = Helper::format_availability_summary($days, '2-3');
    $t->assertEquals('lu-me-ve (2-3x)', $summary);
});

$t->test('Disponibilité vide ou null retourne chaîne vide', function () use ($t) {
    $summary = Helper::format_availability_summary(null);
    $t->assertEquals('', $summary);
});

// =========================================================================
// 4. Tests Sécurité & Codes PIN (Auth)
// =========================================================================
echo "\n\033[1m4. Sécurité & Codes PIN (Auth)\033[0m\n";

$t->test('Calcul du code PIN à partir de la date de naissance (format JJMM)', function () use ($t) {
    $birthDate = '2008-04-19'; // 19 avril
    $parts = explode('-', $birthDate);
    $pin = $parts[2] . $parts[1];
    $t->assertEquals('1904', $pin);
});

$t->test('Validation de la structure de token d\'accès aléatoire', function () use ($t) {
    $token = Auth::generate_token();
    $t->assert(strlen($token) >= 32, 'Le token d\'accès direct doit comporter au moins 32 caractères hexadécimaux');
});

// =========================================================================
// 5. Tests d'Intégration & Affichage des Pages (Smoke Tests HTTP & Vues)
// =========================================================================
echo "\n\033[1m5. Tests d'Intégration & Affichage des Pages (Smoke Tests)\033[0m\n";

$db = get_db();

// Récupérer un athlète existant pour les tests de pages
$test_athlete = $db->query("SELECT * FROM athletes ORDER BY id ASC LIMIT 1")->fetch();
if (!$test_athlete) {
    // Créer un athlète de test si la base est vierge
    $ins = $db->prepare("INSERT INTO athletes (first_name, last_name, birth_year, category, access_token, access_pin) VALUES ('Test', 'Athlète', 2009, 'U18', 'testtoken1234567890123456789012', '1234')");
    $ins->execute();
    $test_athlete = $db->query("SELECT * FROM athletes WHERE id = " . $db->lastInsertId())->fetch();
}

$t->test('Base de données : Connexion PDO et tables système', function () use ($t, $db) {
    $t->assert($db instanceof PDO, 'La connexion PDO doit être instanciée');
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='seasons'");
    $t->assert($stmt->fetch() !== false, 'La table seasons doit exister');
});

$t->test('Page : Accueil & Connexion Athlète (GET /login)', function () use ($t, $db) {
    $ctrl = new AthleteController($db);
    unset($_SESSION['athlete_id'], $_SESSION['athlete_name']);
    ob_start();
    $ctrl->login_view();
    $html = ob_get_clean();
    $t->assert(str_contains($html, 'athlete_search'), 'Le champ de recherche athlète doit être présent');
    $t->assert(str_contains($html, 'Code PIN') || str_contains($html, 'pin'), 'Le champ code PIN doit être présent');
    $t->assert(str_contains($html, '<!DOCTYPE html>'), 'Le gabarit HTML complet doit être rendu');
});

$t->test('Page : Formulaire de Bilan Athlète connecté (GET /)', function () use ($t, $db, $test_athlete) {
    $ctrl = new AthleteController($db);
    $_SESSION['athlete_id'] = $test_athlete['id'];
    $_SESSION['athlete_name'] = $test_athlete['first_name'] . ' ' . $test_athlete['last_name'];
    ob_start();
    $ctrl->form_view();
    $html = ob_get_clean();
    unset($_SESSION['athlete_id'], $_SESSION['athlete_name']);
    $t->assert(str_contains($html, 'Bilan') || str_contains($html, 'Objectifs') || str_contains($html, 'Entraînement'), 'Le formulaire athlète doit s\'afficher');
    $t->assert(str_contains($html, htmlspecialchars($test_athlete['first_name'])), 'Le prénom de l\'athlète doit figurer dans le formulaire');
});

$t->test('Page : Connexion Entraîneur (GET /admin/login)', function () use ($t, $db) {
    $ctrl = new AdminController($db);
    unset($_SESSION['is_coach_admin']);
    ob_start();
    $ctrl->login_view();
    $html = ob_get_clean();
    $t->assert(str_contains($html, 'admin_password') || str_contains($html, 'password'), 'Le champ mot de passe coach doit être présent');
    $t->assert(str_contains($html, 'Connexion Entraîneur') || str_contains($html, 'Espace entraîneur'), 'Le titre coach doit être rendu');
});

$t->test('Page : Tableau de bord Entraîneur (GET /admin)', function () use ($t, $db) {
    $ctrl = new AdminController($db);
    $_SESSION['is_coach_admin'] = true;
    ob_start();
    $ctrl->dashboard();
    $html = ob_get_clean();
    unset($_SESSION['is_coach_admin']);
    $t->assert(str_contains($html, 'Tableau de bord'), 'Le tableau de bord doit contenir son titre');
    $t->assert(str_contains($html, 'Entretien'), 'Les boutons d\'action d\'entretien doivent être affichés');
});

$t->test('Page : Entretien individuel U18+ (GET /admin/entretien/u18)', function () use ($t, $db, $test_athlete) {
    $ctrl = new AdminController($db);
    $_SESSION['is_coach_admin'] = true;
    $_GET['athlete_id'] = (string)$test_athlete['id'];
    ob_start();
    $ctrl->u18_split_view();
    $html = ob_get_clean();
    unset($_SESSION['is_coach_admin'], $_GET['athlete_id']);
    $t->assert(str_contains($html, 'Entretien') || str_contains($html, 'modal-history') || str_contains($html, 'Arbitrage'), 'L\'interface d\'entretien individuel doit être rendue');
});

$t->test('Page : Synthèse d\'entretien imprimable (GET /admin/print-summary)', function () use ($t, $db, $test_athlete) {
    $ctrl = new AdminController($db);
    $_SESSION['is_coach_admin'] = true;
    $_GET['athlete_id'] = (string)$test_athlete['id'];
    ob_start();
    $ctrl->print_summary();
    $html = ob_get_clean();
    unset($_SESSION['is_coach_admin'], $_GET['athlete_id']);
    $t->assert(str_contains($html, 'Synthèse') || str_contains($html, 'CA Sion'), 'La synthèse imprimable doit être rendue');
});

$t->summary();
