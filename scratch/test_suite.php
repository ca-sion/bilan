<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/CategoryHelper.php';
require_once __DIR__ . '/../src/WhatsAppHelper.php';
require_once __DIR__ . '/../src/AthleteController.php';
require_once __DIR__ . '/../src/AdminController.php';
require_once __DIR__ . '/../src/ApiController.php';

echo "=== 1. TEST SWISS ATHLETICS CATEGORIES ===\n";
$tests = [
    2013 => ['age' => 14, 'form' => 'u16_1', 'label' => 'U16 (1ère année)'],
    2012 => ['age' => 15, 'form' => 'u16_2', 'label' => 'U16 (2ème année)'],
    2010 => ['age' => 17, 'form' => 'u18_elite', 'label' => 'U18'],
    2008 => ['age' => 19, 'form' => 'u18_elite', 'label' => 'U20'],
    2005 => ['age' => 22, 'form' => 'u18_elite', 'label' => 'U23'],
    2000 => ['age' => 27, 'form' => 'u18_elite', 'label' => 'Actif']
];

foreach ($tests as $year => $expected) {
    $age = CategoryHelper::get_athlete_age($year);
    $form = CategoryHelper::get_form_type($year);
    $label = CategoryHelper::get_category_label($year);
    echo "Year {$year} -> Age: {$age} (expected {$expected['age']}), Form: {$form} (expected {$expected['form']}), Label: {$label}\n";
    assert($age === $expected['age'], "Age mismatch for {$year}");
    assert($form === $expected['form'], "Form mismatch for {$year}");
}

echo "\n=== 2. TEST DISCIPLINE COMPATIBILITY ===\n";
$comp1 = CategoryHelper::check_disciplines_compatibility('middle_distance', 'sprint');
echo "Middle distance + Sprint compatible? " . ($comp1['is_compatible'] ? 'YES' : 'NO') . " Warning: {$comp1['warning']}\n";
assert(!$comp1['is_compatible'], "Middle distance + sprint should trigger warning");

$comp2 = CategoryHelper::check_disciplines_compatibility('combined_events', 'shot_put');
echo "Combined events + Shot put compatible? " . ($comp2['is_compatible'] ? 'YES' : 'NO') . "\n";
assert(!$comp2['is_compatible'], "Combined events + any discipline should be incompatible");

$comp3 = CategoryHelper::check_disciplines_compatibility('sprint', 'long_jump');
echo "Sprint + Long jump compatible? " . ($comp3['is_compatible'] ? 'YES' : 'NO') . "\n";
assert($comp3['is_compatible'], "Sprint + Long jump should be compatible");

echo "\n=== 3. TEST WHATSAPP SYNTHESIS ===\n";
$dummy_athlete = [
    'first_name' => 'Alexis',
    'last_name' => 'Reynard',
    'birth_year' => 2008,
    'category' => 'U20'
];
$dummy_interview = [
    'validated_at' => '2026-09-14 10:00:00',
    'decisions' => json_encode([
        'primary_discipline' => 'sprint',
        'secondary_discipline' => 'short_hurdles',
        'approved_weekly_sessions' => 4,
        'approved_training_days' => ['monday', 'wednesday', 'friday', 'saturday'],
        'target_milestones' => '10.95s au 100m et finale CS U20',
        'mandatory_rule_1' => 'Ponctualité et échauffement sans retard',
        'mandatory_rule_2' => 'Communication proactive sous 24h'
    ]),
    'trainer_answers' => '{}',
    'athlete_answers' => '{}'
];
$synthesis = WhatsAppHelper::build_synthesis_text($dummy_athlete, $dummy_interview);
echo "Generated Synthesis:\n";
echo "----------------------------------------\n";
echo $synthesis . "\n";
echo "----------------------------------------\n";

assert(str_contains($synthesis, '🔴 CA SION — BILAN ET PROJECTION DE SAISON ⚪'));
assert(str_contains($synthesis, 'Athlète : Alexis Reynard (U20)'));
assert(str_contains($synthesis, 'Discipline prioritaire : Sprint'));
assert(str_contains($synthesis, 'Discipline secondaire : Haies courtes'));
assert(str_contains($synthesis, 'Volume d\'entraînement : 4 séances / semaine'));
assert(str_contains($synthesis, 'Jours retenus : Lu, Me, Ve, Sa'));
assert(str_contains($synthesis, '1. Ponctualité et échauffement sans retard'));
assert(str_contains($synthesis, '2. Communication proactive sous 24h'));
assert(str_contains($synthesis, '✅ Statut : Entretien officiel validé d\'un commun accord avec l\'entraîneur.'));

echo "\n=== 4. TEST DATABASE INTEGRITY ===\n";
$db = get_db();
$count = $db->query('SELECT COUNT(*) FROM athletes')->fetchColumn();
echo "Total athletes in DB: {$count}\n";
assert($count >= 6, "At least 6 sample athletes should be present");

echo "\nALL AUTOMATED TESTS PASSED SUCCESSFULLY! ✓\n";
