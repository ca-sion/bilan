<?php
declare(strict_types=1);

$birth_year = (int)$athlete['birth_year'];
$category_label = CategoryHelper::get_category_label($birth_year, $athlete['category']);
$age = CategoryHelper::get_athlete_age($birth_year);
$club_name = SettingsService::get_club_name();
$season_name = htmlspecialchars($season['name'] ?? '2026-2027');

$athlete_answers = safe_json_decode($interview['athlete_answers'] ?? null);
$trainer_answers = safe_json_decode($interview['trainer_answers'] ?? null);
$decisions = safe_json_decode($interview['decisions'] ?? null);
$meta = safe_json_decode($athlete['meta'] ?? null);

// Disciplines
$d1_raw = $decisions['primary_discipline'] 
    ?? ($decisions['friday_discipline_approved'] 
    ?? ($decisions['approved_disciplines'][0] 
    ?? ($athlete_answers['chosen_discipline_1'] 
    ?? ($athlete_answers['friday_discipline'] 
    ?? ''))));
$d2_raw = $decisions['secondary_discipline'] 
    ?? ($decisions['approved_disciplines'][1] 
    ?? ($athlete_answers['chosen_discipline_2'] 
    ?? ''));

$d1 = CategoryHelper::get_discipline_label($d1_raw);
$d2 = CategoryHelper::get_discipline_label($d2_raw);

// Volume & Jours
$sessions = $decisions['approved_weekly_sessions'] ?? ($athlete_answers['target_sessions_count'] ?? '3');
$approved_days = $decisions['approved_training_days'] ?? ($athlete_answers['available_days'] ?? []);
if (!is_array($approved_days)) {
    $approved_days = [$approved_days];
}

$days_fr_map = [
    'monday'    => 'Lundi',
    'tuesday'   => 'Mardi',
    'wednesday' => 'Mercredi',
    'thursday'  => 'Jeudi',
    'friday'    => 'Vendredi',
    'saturday'  => 'Samedi',
];

$formatted_days = [];
foreach ($days_fr_map as $k => $lbl) {
    if (in_array($k, $approved_days, true) || in_array("{$k}_morning", $approved_days, true)) {
        $formatted_days[] = $lbl;
    }
}
$days_str = !empty($formatted_days) ? implode(', ', $formatted_days) : 'À convenir';

// Cadres sportifs
$cadres_raw = $decisions['cadres'] ?? ($athlete_answers['cadres'] ?? []);
if (!is_array($cadres_raw)) {
    $cadres_raw = [$cadres_raw];
}
$cadres_labels = array_map(fn($k) => CategoryHelper::get_cadre_label((string)$k), array_filter($cadres_raw));
$cadres_str = !empty($cadres_labels) ? implode(', ', $cadres_labels) : '';

$val_date_str = $interview['validated_at'] ?? $interview['updated_at'] ?? date('Y-m-d');
$val_date_fr = date('d.m.Y', strtotime((string)$val_date_str));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche Bilan — <?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?> — <?= htmlspecialchars($club_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }
        .font-heading {
            font-family: 'Space Grotesk', sans-serif;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .print-card {
                border: 1px solid #cbd5e1 !important;
                box-shadow: none !important;
                page-break-inside: avoid;
            }
            .page-break {
                page-break-before: always;
            }
        }
    </style>
</head>
<body class="p-4 sm:p-8">

    <!-- Barre d'actions (visible à l'écran uniquement) -->
    <div class="no-print max-w-4xl mx-auto mb-6 bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-3">
        <a href="<?= url('/admin') ?>" class="text-xs font-bold text-slate-600 hover:text-slate-900 flex items-center gap-1.5 transition-colors">
            <span>←</span>
            <span>Retour au tableau de bord</span>
        </a>
        <div class="flex items-center gap-2">
            <button 
                type="button" 
                onclick="copyWhatsAppSynthesis(<?= (int)$interview['id'] ?>, this)" 
                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center gap-1.5"
            >
                <span>💬</span>
                <span>Copier synthèse WhatsApp</span>
            </button>
            <button 
                type="button" 
                onclick="window.print()" 
                class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center gap-1.5"
            >
                <span>🖨️</span>
                <span>Imprimer / Enregistrer en PDF</span>
            </button>
        </div>
    </div>

    <!-- FEUILLE A4 OFFICIELLE -->
    <div class="max-w-4xl mx-auto bg-white p-8 sm:p-10 rounded-2xl border border-slate-200 shadow-md print-card space-y-6">
        
        <!-- En-tête officiel Club -->
        <div class="flex items-start justify-between border-b-2 border-slate-900 pb-5">
            <div>
                <span class="text-xs font-extrabold uppercase tracking-widest text-slate-900 block"><?= htmlspecialchars($club_name) ?></span>
                <h1 class="text-2xl font-bold font-heading text-slate-900 mt-0.5">
                    Fiche de bilan
                </h1>
                <p class="text-xs text-slate-500 font-medium mt-0.5">
                    Saison <?= $season_name ?> &bull; Entretien individuel
                </p>
            </div>
            <div class="text-right">
                <span class="inline-block px-3 py-1 bg-slate-100 text-slate-800 font-bold text-xs rounded-lg border border-slate-200">
                    <?= htmlspecialchars($category_label) ?>
                </span>
                <span class="text-[11px] text-slate-500 block mt-1">Édité le <?= date('d.m.Y') ?></span>
            </div>
        </div>

        <!-- Identité de l'athlète -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs">
            <div>
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Athlète</span>
                <strong class="text-sm font-bold text-slate-900 block mt-0.5"><?= htmlspecialchars($athlete['last_name'] . ' ' . $athlete['first_name']) ?></strong>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Année de naissance</span>
                <span class="font-semibold text-slate-800 block mt-0.5"><?= $birth_year > 0 ? "{$birth_year} ({$age} ans)" : "Non renseignée" ?></span>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Date d'entretien</span>
                <span class="font-semibold text-slate-800 block mt-0.5"><?= htmlspecialchars($val_date_fr) ?></span>
            </div>
        </div>

        <!-- 1. Projet sportif et orientations validées -->
        <div class="space-y-3">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900 pb-1 border-b border-slate-200 flex items-center gap-1.5">
                <span class="w-4 h-4 rounded bg-slate-900 text-white flex items-center justify-center text-[10px] font-black">1</span>
                Projet sportif et orientations retenues
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-1.5">
                    <div>
                        <span class="text-[10px] font-bold text-slate-500 uppercase block">Discipline 1</span>
                        <strong class="text-slate-900 text-sm"><?= htmlspecialchars($d1) ?></strong>
                    </div>
                    <?php if ($d2 !== 'Aucune' && $d2 !== ''): ?>
                        <div class="pt-1 border-t border-slate-200/60">
                            <span class="text-[10px] font-bold text-slate-500 uppercase block">Discipline 2</span>
                            <span class="font-semibold text-slate-800"><?= htmlspecialchars($d2) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-1.5">
                    <div>
                        <span class="text-[10px] font-bold text-slate-500 uppercase block">Nombre d'entraînements par semaine</span>
                        <strong class="text-slate-900 text-sm"><?= htmlspecialchars((string)$sessions) ?> séances / semaine</strong>
                    </div>
                    <div class="pt-1 border-t border-slate-200/60">
                        <span class="text-[10px] font-bold text-slate-500 uppercase block">Disponibilités</span>
                        <span class="font-semibold text-slate-800"><?= htmlspecialchars($days_str) ?></span>
                    </div>
                </div>
            </div>

            <?php if (!empty($cadres_str)): ?>
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Cadres :</span>
                    <strong class="text-slate-900 font-semibold mt-0.5 block"><?= htmlspecialchars($cadres_str) ?></strong>
                </div>
            <?php endif; ?>

            <!-- Objectif cible -->
            <?php 
                $target_perf = $decisions['target_milestones'] 
                    ?? ($athlete_answers['target_performance'] 
                    ?? ($athlete_answers['target_competitions'] 
                    ?? ''));
                $pride = $athlete_answers['pride_highlight'] ?? ($athlete_answers['top_success_description'] ?? '');
            ?>
            <?php if (!empty($pride)): ?>
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Réussite et fierté marquante de la saison :</span>
                    <p class="italic font-medium text-slate-800 mt-0.5">« <?= htmlspecialchars($pride) ?> »</p>
                </div>
            <?php endif; ?>

            <?php if ($target_perf !== ''): ?>
                <div class="p-3 bg-indigo-50/60 rounded-xl border border-indigo-200 text-xs">
                    <span class="text-[10px] font-bold text-indigo-900 uppercase tracking-wider block">Objectifs :</span>
                    <p class="font-semibold text-indigo-950 mt-0.5"><?= htmlspecialchars($target_perf) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- 2. Contrat moral et comportements d'excellence -->
        <div class="space-y-3">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900 pb-1 border-b border-slate-200 flex items-center gap-1.5">
                <span class="w-4 h-4 rounded bg-slate-900 text-white flex items-center justify-center text-[10px] font-black">2</span>
                Contrat moral
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                    <span class="text-[10px] font-bold text-slate-500 uppercase block">Engagement n°1</span>
                    <p class="font-semibold text-slate-900 mt-1">
                        <?= htmlspecialchars($decisions['mandatory_rule_1'] ?? ($athlete_answers['commitment_1'] ?? ($athlete_answers['attitude_contract'] ?? 'Présence assidue et ponctualité exemplaire.'))) ?>
                    </p>
                </div>
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                    <span class="text-[10px] font-bold text-slate-500 uppercase block">Engagement n°2</span>
                    <p class="font-semibold text-slate-900 mt-1">
                        <?= htmlspecialchars($decisions['mandatory_rule_2'] ?? ($athlete_answers['commitment_2'] ?? 'Communication immédiate de toute douleur ou fatigue.')) ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- 3. Synthèse et consignes de l'entraîneur -->
        <?php if (!empty($interview['trainer_notes']) || !empty($decisions['strength_training'])): ?>
            <div class="space-y-2 text-xs">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-900 pb-1 border-b border-slate-200 flex items-center gap-1.5">
                    <span class="w-4 h-4 rounded bg-slate-900 text-white flex items-center justify-center text-[10px] font-black">3</span>
                    Remarques
                </h2>
                <?php if (!empty($decisions['strength_training'])): ?>
                    <p><strong>Recommendations :</strong> <?= htmlspecialchars($decisions['strength_training']) ?></p>
                <?php endif; ?>
                <?php if (!empty($interview['trainer_notes'])): ?>
                    <p class="italic text-slate-700 bg-slate-50 p-2.5 rounded-lg border border-slate-200">
                        <?= nl2br(htmlspecialchars($interview['trainer_notes'])) ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Signatures & Validation -->
        <div class="pt-6 border-t-2 border-slate-200 grid grid-cols-2 gap-8 text-xs">
            <div>
                <span class="text-[10px] font-bold text-slate-500 uppercase block">Signature de l'athlète :</span>
                <div class="mt-8 border-b border-slate-400 w-3/4"></div>
                <span class="text-[10px] text-slate-400 block mt-1"><?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?></span>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-500 uppercase block">Entraîneur :</span>
                <div class="mt-8 border-b border-slate-400 w-3/4"></div>
                <span class="text-[10px] text-slate-400 block mt-1">Validé le <?= htmlspecialchars($val_date_fr) ?></span>
            </div>
        </div>

    </div>

    <script src="<?= url('/assets/js/app.js') ?>"></script>
</body>
</html>
