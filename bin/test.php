<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Ce script est réservé à l'exécution en ligne de commande (CLI).\n");
}

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/src/autoload.php';

Auth::init_session();

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
    $pin = Auth::calculate_pin_from_birth_date($birthDate);
    $t->assertEquals('1904', $pin);
});

$t->test('Parsing date avec horodatage "2011-11-07 00:00:00" (format CSV utilisateur)', function () use ($t) {
    $parsed = Helper::parse_birth_date('2011-11-07 00:00:00');
    $t->assertEquals('2011-11-07', $parsed['birth_date']);
    $t->assertEquals(2011, $parsed['birth_year']);
    $t->assertEquals('0711', $parsed['pin']);
});

$t->test('Parsing date format suisse "07.11.2011"', function () use ($t) {
    $parsed = Helper::parse_birth_date('07.11.2011');
    $t->assertEquals('2011-11-07', $parsed['birth_date']);
    $t->assertEquals(2011, $parsed['birth_year']);
    $t->assertEquals('0711', $parsed['pin']);
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

$t->test('Page : Cadrage groupé U16 (GET /admin/entretien/u16)', function () use ($t, $db, $test_athlete) {
    $ctrl = new AdminController($db);
    $_SESSION['is_coach_admin'] = true;
    $_GET['ids'] = (string)$test_athlete['id'];
    ob_start();
    $ctrl->u16_group_view();
    $html = ob_get_clean();
    unset($_SESSION['is_coach_admin'], $_GET['ids']);
    $t->assert(str_contains($html, 'Cadrage') || str_contains($html, 'U16') || str_contains($html, 'modal-history'), 'L\'interface de cadrage groupé U16 doit être rendue');
});

$t->test('Routeur : Enregistrement et résolution déclarative (Router)', function () use ($t) {
    $router = new Router();
    $matched = false;
    $router->get('/test-route', function () use (&$matched) {
        $matched = true;
    });
    $router->dispatch('GET', '/test-route');
    $t->assert($matched === true, 'Le routeur doit résoudre correctement les routes GET déclarées');
});

// =========================================================================
// 6. Tests des Endpoints API & Autosave (ApiController)
// =========================================================================
echo "\n\033[1m6. Endpoints API, Autosave & Soumission (ApiController)\033[0m\n";

$active_season = (new SeasonRepository($db))->getActive();
$active_interview = (new InterviewRepository($db))->getOrCreateForSeason((int)$test_athlete['id'], (int)$active_season['id']);
$test_interview_id = (int)$active_interview['id'];

$t->test('API : Enregistrement automatique des réponses (POST /api/save)', function () use ($t, $db, $test_athlete, $test_interview_id) {
    // S'assurer que l'entretien est en statut modifiable (draft)
    $db->prepare("UPDATE interviews SET status = 'draft' WHERE id = ?")->execute([$test_interview_id]);
    
    $api = new ApiController($db);
    $_SESSION['athlete_id'] = $test_athlete['id'];
    $_GET = [];
    $_POST = [
        'interview_id' => $test_interview_id,
        'athlete_id' => $test_athlete['id'],
        'answers' => [
            'discipline_1' => 'sprint_court',
            'proud_moment' => 'Record personnel sur 100m',
            'weekly_sessions' => '3'
        ]
    ];
    
    ob_start();
    $api->saveAnswers();
    $json = ob_get_clean();
    $_POST = [];
    unset($_SESSION['athlete_id']);
    
    $res = json_decode($json, true);
    $t->assert(is_array($res) && ($res['success'] ?? false) === true, 'L\'autosave doit retourner success: true');
    
    // Vérifier en base sur la fiche active
    $stmt = $db->prepare("SELECT athlete_answers FROM interviews WHERE id = ?");
    $stmt->execute([$test_interview_id]);
    $saved = json_decode($stmt->fetchColumn() ?: '{}', true);
    $t->assertEquals('Record personnel sur 100m', $saved['proud_moment'] ?? null);
});

$t->test('API : Soumission définitive du bilan (POST /api/submit)', function () use ($t, $db, $test_athlete, $test_interview_id) {
    $api = new ApiController($db);
    $_SESSION['athlete_id'] = $test_athlete['id'];
    $_GET = [];
    $_POST = [
        'interview_id' => $test_interview_id,
        'athlete_id' => $test_athlete['id'],
        'answers' => [
            'goals_perf' => 'Participer aux championnats suisses'
        ]
    ];
    
    ob_start();
    $api->submitInterview();
    $json = ob_get_clean();
    $_POST = [];
    unset($_SESSION['athlete_id']);
    
    $res = json_decode($json, true);
    $t->assert(is_array($res) && ($res['success'] ?? false) === true, 'La soumission doit retourner success: true');
    
    // Vérifier que le statut passe à submitted
    $stmt = $db->prepare("SELECT status FROM interviews WHERE id = ?");
    $stmt->execute([$test_interview_id]);
    $t->assertEquals('submitted', $stmt->fetchColumn());
});

$t->test('API : Validation dynamique de compatibilité d\'épreuves (GET /api/validate-disciplines)', function () use ($t, $db) {
    $api = new ApiController($db);
    $_POST = [];
    $_GET = [
        'discipline_1' => 'sprint_court',
        'discipline_2' => 'demi_fond'
    ];
    
    ob_start();
    $api->validateDisciplines();
    $json = ob_get_clean();
    $_GET = [];
    
    $res = json_decode($json, true);
    $t->assert(is_array($res) && isset($res['is_compatible']), 'La réponse doit être un JSON contenant is_compatible');
    $t->assertEquals(false, $res['is_compatible']);
});

$t->test('API : Génération de la synthèse WhatsApp (POST /api/export-whatsapp)', function () use ($t, $db, $test_interview_id) {
    $api = new ApiController($db);
    $_GET = [];
    $_POST = ['interview_id' => $test_interview_id];
    
    ob_start();
    $api->exportWhatsapp();
    $json = ob_get_clean();
    $_POST = [];
    
    $res = json_decode($json, true);
    $t->assert(is_array($res) && ($res['success'] ?? false) === true, 'L\'export WhatsApp doit retourner success: true');
    $t->assert(!empty($res['text']), 'Le texte de synthèse WhatsApp ne doit pas être vide');
});

$t->test('API : Génération de la synthèse WhatsApp (GET /api/export-whatsapp)', function () use ($t, $db, $test_interview_id) {
    $api = new ApiController($db);
    $_GET = ['interview_id' => $test_interview_id];
    $_POST = [];
    
    ob_start();
    $api->exportWhatsapp();
    $json = ob_get_clean();
    $_GET = [];
    
    $res = json_decode($json, true);
    $t->assert(is_array($res) && ($res['success'] ?? false) === true, 'L\'export WhatsApp via GET doit retourner success: true');
    $t->assert(!empty($res['text']), 'Le texte de synthèse WhatsApp ne doit pas être vide');
});

// =========================================================================
// 7. Structures de Cadres & Actions Entraîneur (AdminController & Helpers)
// =========================================================================
echo "\n\033[1m7. Structures de Cadres & Actions Entraîneur (AdminController & Helpers)\033[0m\n";

$t->test('Cadres : Configuration des 4 structures de cadres (CategoryHelper::get_cadres)', function () use ($t) {
    $cadres = CategoryHelper::get_cadres();
    $t->assert(isset($cadres['team_jeunesse']), 'Structure Team jeunesse présente');
    $t->assert(isset($cadres['cadres_vs']), 'Structure Cadres VS présente');
    $t->assert(isset($cadres['cadres_romands']), 'Structure Cadres romands présente');
    $t->assert(isset($cadres['cadres_suisses']), 'Structure Cadres suisses présente');
    $t->assertEquals('Cadres VS', CategoryHelper::get_cadre_label('cadres_vs'));
});

$t->test('Admin : Enregistrement et validation des arbitrages avec Cadres (POST /admin/save-trainer)', function () use ($t, $db, $test_athlete, $test_interview_id) {
    $ctrl = new AdminController($db);
    $_SESSION['is_coach_admin'] = true;
    $_POST = [
        'interview_id' => $test_interview_id,
        'action' => 'validate',
        'trainer_answers' => [
            'attitude_status' => 'ok',
            'coach_rating_rigor' => '5'
        ],
        'decisions' => [
            'primary_discipline' => 'sprint_court',
            'approved_weekly_sessions' => '4',
            'approved_training_days' => ['monday', 'wednesday', 'friday'],
            'cadres' => ['cadres_vs', 'cadres_romands']
        ],
        'trainer_notes' => 'Excellent engagement constaté.',
        'redirect_to' => '/admin'
    ];

    $ctrl->save_trainer_form();
    $_POST = [];
    unset($_SESSION['is_coach_admin']);

    $stmt = $db->prepare("SELECT status, is_validated, decisions, trainer_notes FROM interviews WHERE id = ?");
    $stmt->execute([$test_interview_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $t->assertEquals('completed', $row['status'] ?? null);
    $t->assertEquals(1, (int)($row['is_validated'] ?? 0));
    $t->assertEquals('Excellent engagement constaté.', $row['trainer_notes'] ?? null);
    $dec = json_decode($row['decisions'] ?? '{}', true);
    $t->assertEquals('sprint_court', $dec['primary_discipline'] ?? null);
    $t->assert(in_array('cadres_vs', $dec['cadres'] ?? [], true), 'Cadres VS doit être validé');
});

$t->test('Admin : Déverrouillage d\'un entretien par le coach (POST /admin/reopen)', function () use ($t, $db, $test_interview_id) {
    $ctrl = new AdminController($db);
    $_SESSION['is_coach_admin'] = true;
    $_POST = [
        'interview_id' => $test_interview_id,
        'redirect_to' => '/admin'
    ];

    $ctrl->reopen_interview();
    $_POST = [];
    unset($_SESSION['is_coach_admin']);

    $stmt = $db->prepare("SELECT status, is_validated FROM interviews WHERE id = ?");
    $stmt->execute([$test_interview_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $t->assertEquals('draft', $row['status'] ?? null);
    $t->assertEquals(0, (int)($row['is_validated'] ?? 0));
});

$t->test('Athlète : Déverrouillage autonome du bilan (POST /unlock)', function () use ($t, $db, $test_athlete, $test_interview_id) {
    // Reverrouiller d'abord
    $db->prepare("UPDATE interviews SET status = 'submitted', is_validated = 0 WHERE id = ?")->execute([$test_interview_id]);

    $ctrl = new AthleteController($db);
    $_SESSION['athlete_id'] = $test_athlete['id'];
    $_POST = [];

    $ctrl->unlockForm();
    unset($_SESSION['athlete_id']);

    $stmt = $db->prepare("SELECT status FROM interviews WHERE id = ?");
    $stmt->execute([$test_interview_id]);
    $t->assertEquals('draft', $stmt->fetchColumn());
});

$t->test('Admin : Réinitialisation du code PIN (POST /admin/reset-pin)', function () use ($t, $db, $test_athlete) {
    $ctrl = new AdminController($db);
    $_SESSION['is_coach_admin'] = true;
    $_POST = [
        'athlete_id' => $test_athlete['id']
    ];

    $ctrl->reset_pin();
    $_POST = [];
    unset($_SESSION['is_coach_admin']);

    $stmt = $db->prepare("SELECT access_pin, birth_date FROM athletes WHERE id = ?");
    $stmt->execute([$test_athlete['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $expected_pin = !empty($row['birth_date']) ? Auth::calculate_pin_from_birth_date($row['birth_date']) : '0000';
    $t->assertEquals($expected_pin, $row['access_pin'] ?? null);
});

$t->test('Admin : Renommer une saison (POST /admin/rename-season)', function () use ($t, $db, $active_season) {
    $ctrl = new AdminController($db);
    $_SESSION['is_coach_admin'] = true;
    $orig_name = $active_season['name'];
    $temp_name = $orig_name . ' - Modifiée';
    
    $_POST = [
        'season_id' => $active_season['id'],
        'new_name' => $temp_name
    ];
    $ctrl->rename_season();

    $stmt = $db->prepare("SELECT name FROM seasons WHERE id = ?");
    $stmt->execute([$active_season['id']]);
    $t->assertEquals($temp_name, $stmt->fetchColumn());

    // Rétablir le nom original
    $_POST = [
        'season_id' => $active_season['id'],
        'season_name' => $orig_name
    ];
    $ctrl->rename_season();
    $stmt->execute([$active_season['id']]);
    $t->assertEquals($orig_name, $stmt->fetchColumn());
    $_POST = [];
    unset($_SESSION['is_coach_admin']);
});

// =========================================================================
// 8. Tests InterviewHistoryHelper (Continuité N-1)
// =========================================================================
echo "\n\033[1m8. Reprise d'Historique N-1 (InterviewHistoryHelper)\033[0m\n";

$t->test('Historique N-1 : Athlète sans historique retourne null', function () use ($t, $db, $test_athlete, $active_season) {
    // Athlète fictif avec ID inexistant
    $summary = InterviewHistoryHelper::getPreviousSummary(999999, (int)$active_season['id'], $db);
    $t->assertEquals(null, $summary);
});

$t->test('Historique N-1 : Extraction et normalisation des objectifs passés', function () use ($t, $db, $test_athlete, $active_season) {
    // Créer une saison N-1 passée
    $db->prepare("INSERT INTO seasons (name, is_active) VALUES ('2025-2026 (Test)', 0)")->execute();
    $past_season_id = (int)$db->lastInsertId();

    $past_answers = json_encode([
        'target_performance' => 'Courir sous les 11.00s au 100m',
        'target_competitions' => 'Podium Championnats suisses U18',
        'commitment_1' => '95% d\'assiduité aux séances techniques',
        'study_work_situation' => 'Gymnase de la Planta - 2ème année'
    ], JSON_UNESCAPED_UNICODE);

    $past_decisions = json_encode([
        'primary_discipline' => 'sprint_court',
        'approved_weekly_sessions' => '4'
    ], JSON_UNESCAPED_UNICODE);

    $db->prepare("
        INSERT INTO interviews (athlete_id, season_id, interview_type, status, athlete_answers, decisions, is_validated) 
        VALUES (?, ?, 'individual_elite', 'completed', ?, ?, 1)
    ")->execute([$test_athlete['id'], $past_season_id, $past_answers, $past_decisions]);

    $summary = InterviewHistoryHelper::getPreviousSummary((int)$test_athlete['id'], (int)$active_season['id'], $db);

    $t->assert($summary !== null, 'Le résumé N-1 doit être extrait');
    $t->assertEquals('Courir sous les 11.00s au 100m', $summary['perf_goal'] ?? null);
    $t->assertEquals('Podium Championnats suisses U18', $summary['comp_goal'] ?? null);
    $t->assertEquals('95% d\'assiduité aux séances techniques', $summary['attitude_goal'] ?? null);
    $t->assertEquals('Gymnase de la Planta - 2ème année', $summary['study_work'] ?? null);
    $t->assertEquals(true, $summary['has_goals'] ?? false);

    // Nettoyage de la saison de test
    $db->prepare("DELETE FROM interviews WHERE season_id = ?")->execute([$past_season_id]);
    $db->prepare("DELETE FROM seasons WHERE id = ?")->execute([$past_season_id]);
});

$t->summary();



