<?php
$page_title = "Entretien individuel — " . htmlspecialchars($interview['first_name'] . ' ' . $interview['last_name']);
$is_val = (int)($interview['is_validated'] ?? 0) === 1;
$status = $interview['status'] ?? 'waiting';
$goals = is_array($athlete_answers['goals_results'] ?? null) ? $athlete_answers['goals_results'] : [];
$get_goal = fn(string $k): string => (string)($goals[$k] ?? $athlete_answers["goals_results[{$k}]"] ?? $athlete_answers[$k] ?? '');
$ath_days = $athlete_answers['available_days'] ?? [];
if (!is_array($ath_days)) $ath_days = [$ath_days];

$selected_days = $decisions['approved_training_days'] ?? ($athlete_answers['available_days'] ?? []);
if (!is_array($selected_days)) $selected_days = [$selected_days];

$ath_cadres = $athlete_answers['cadres'] ?? [];
if (!is_array($ath_cadres)) $ath_cadres = [$ath_cadres];

$selected_cadres = $decisions['cadres'] ?? ($athlete_answers['cadres'] ?? []);
if (!is_array($selected_cadres)) $selected_cadres = [$selected_cadres];

$pillars = [
    'rigor' => [
        'label' => 'Rigueur à l\'entraînement',
        'desc' => 'Présence, écoute des consignes, concentration technique et engagement.',
        'ath_key' => 'rating_rigor',
        'coach_key' => 'coach_rating_rigor'
    ],
    'care_injuries' => [
        'label' => 'Soins et gestion des blessures',
        'desc' => 'Prévention, communication immédiate au staff, soins et respect des protocoles.',
        'ath_key' => 'rating_care_injuries',
        'coach_key' => 'coach_rating_care_injuries'
    ],
    'lifestyle' => [
        'label' => 'Hygiène de vie',
        'desc' => 'Sommeil, alimentation, sorties et récupération.',
        'ath_key' => 'rating_lifestyle',
        'coach_key' => 'coach_rating_lifestyle'
    ],
    'mental' => [
        'label' => 'Stabilité mentale',
        'desc' => 'Gestion du stress, combativité sous pression et routine de compétition.',
        'ath_key' => 'rating_mental_stability',
        'coach_key' => 'coach_rating_mental_stability'
    ],
    'dual_career' => [
        'label' => 'Double projet (études / sport)',
        'desc' => 'Organisation du planning, anticipation des examens et équilibre global.',
        'ath_key' => 'rating_dual_career',
        'coach_key' => 'coach_rating_dual_career'
    ]
];

ob_start();
?>

<!-- Modal Historique N-1 (Comparatif de progression) -->
<div id="modal-history" class="hidden fixed inset-0 z-50 bg-zinc-900/60 backdrop-blur-sm flex items-center justify-center p-4 overflow-y-auto animate-fade-in">
    <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-zinc-200 space-y-5 my-8 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-zinc-100 pb-3">
            <div class="flex items-center gap-2">
                <span class="w-8 h-8 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center text-sm font-bold">📜</span>
                <div>
                    <h3 class="font-heading font-bold text-base text-zinc-900">
                        Historique de saison précédente (N-1)
                    </h3>
                    <p class="text-xs text-zinc-500">
                        Données de référence de la saison <?= htmlspecialchars($history['season_name'] ?? 'précédente') ?>
                    </p>
                </div>
            </div>
            <button type="button" onclick="document.getElementById('modal-history').classList.add('hidden')" class="w-8 h-8 rounded-full bg-zinc-100 hover:bg-zinc-200 text-zinc-500 hover:text-zinc-800 flex items-center justify-center text-sm font-bold transition-colors">
                ✕
            </button>
        </div>

        <?php if ($history): ?>
            <?php
                $h_ath = safe_json_decode($history['athlete_answers'] ?? null);
                $h_dec = safe_json_decode($history['decisions'] ?? null);
                $h_d1 = $h_dec['primary_discipline'] ?? ($h_ath['chosen_discipline_1'] ?? 'Non renseigné');
                $h_d2 = $h_dec['secondary_discipline'] ?? ($h_ath['chosen_discipline_2'] ?? 'Aucune');
                $h_sessions = $h_dec['approved_weekly_sessions'] ?? ($h_ath['target_sessions_count'] ?? '-');
                $h_days = $h_dec['approved_training_days'] ?? ($h_ath['available_days'] ?? []);
                if (!is_array($h_days)) $h_days = [$h_days];
                $h_rule1 = $h_dec['mandatory_rule_1'] ?? ($h_ath['attitude_contract'] ?? ($h_ath['commitment_1'] ?? ''));
                $h_rule2 = $h_dec['mandatory_rule_2'] ?? ($h_ath['commitment_2'] ?? '');
            ?>
            <div class="space-y-4 text-xs">
                <!-- Orientation sportive N-1 -->
                <div class="p-3.5 bg-zinc-50 rounded-xl border border-zinc-200/80 space-y-2">
                    <div class="font-bold text-zinc-800 text-[11px] uppercase tracking-wider text-rose-700">1. Orientation & Volume validés en N-1</div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <div>
                            <span class="text-zinc-500 block">Discipline prioritaire :</span>
                            <span class="font-semibold text-zinc-900"><?= htmlspecialchars(CategoryHelper::get_discipline_label($h_d1)) ?></span>
                        </div>
                        <div>
                            <span class="text-zinc-500 block">Discipline secondaire :</span>
                            <span class="font-semibold text-zinc-900"><?= htmlspecialchars(CategoryHelper::get_discipline_label($h_d2)) ?></span>
                        </div>
                        <div>
                            <span class="text-zinc-500 block">Volume & Jours :</span>
                            <span class="font-semibold text-zinc-900"><?= htmlspecialchars((string)$h_sessions) ?>x / sem (<?= Helper::format_availability_summary($h_days) ?>)</span>
                        </div>
                    </div>
                </div>

                <!-- Réussite & Objectifs N-1 -->
                <div class="p-3.5 bg-zinc-50 rounded-xl border border-zinc-200/80 space-y-2">
                    <div class="font-bold text-zinc-800 text-[11px] uppercase tracking-wider text-rose-700">2. Fierté & Objectifs passés</div>
                    <div>
                        <span class="text-zinc-500 block">Plus grande fierté / Réussite exprimée :</span>
                        <p class="italic text-zinc-800 mt-0.5 bg-white p-2 rounded border border-zinc-200">
                            "<?= htmlspecialchars($h_ath['top_success_description'] ?? ($h_ath['pride_highlight'] ?? 'Non renseignée')) ?>"
                        </p>
                    </div>
                    <?php if (!empty($h_dec['target_milestones']) || !empty($h_ath['target_performance'])): ?>
                        <div>
                            <span class="text-zinc-500 block">Objectifs fixés en N-1 :</span>
                            <span class="font-medium text-zinc-800"><?= htmlspecialchars($h_dec['target_milestones'] ?? $h_ath['target_performance']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Contrat moral N-1 -->
                <div class="p-3.5 bg-zinc-50 rounded-xl border border-zinc-200/80 space-y-2">
                    <div class="font-bold text-zinc-800 text-[11px] uppercase tracking-wider text-rose-700">3. Contrat moral N-1 (Engagements)</div>
                    <ul class="space-y-1.5 list-disc list-inside text-zinc-700">
                        <li><strong>Règle 1 :</strong> <?= htmlspecialchars($h_rule1 ?: 'Non renseignée') ?></li>
                        <?php if ($h_rule2): ?>
                            <li><strong>Règle 2 :</strong> <?= htmlspecialchars($h_rule2) ?></li>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Notes coach N-1 -->
                <?php if (!empty($history['trainer_notes'])): ?>
                    <div class="p-3.5 bg-amber-50/60 rounded-xl border border-amber-200/80 space-y-1">
                        <div class="font-bold text-amber-900 text-[11px] uppercase tracking-wider">4. Remarques du coach en N-1</div>
                        <p class="text-zinc-800 whitespace-pre-line"><?= htmlspecialchars($history['trainer_notes']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-8 text-zinc-500 text-xs">
                <span class="text-2xl block mb-1">📭</span>
                Aucun historique d'entretien trouvé pour cet athlète sur les saisons précédentes.
            </div>
        <?php endif; ?>

        <div class="flex justify-end pt-2">
            <button type="button" onclick="document.getElementById('modal-history').classList.add('hidden')" class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-white rounded-xl text-xs font-bold transition-colors">
                Fermer
            </button>
        </div>
    </div>
</div>

<div class="space-y-6">

    <!-- En-tête de la vue split-screen -->
    <div class="bg-white rounded-2xl shadow-sm border border-zinc-200 p-5 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="<?= url('/admin') ?>" class="text-xs text-zinc-600 hover:text-zinc-900 transition-colors">
                    &larr; Retour au tableau de bord
                </a>
                <span class="text-zinc-300">/</span>
                <span class="text-xs font-semibold text-rose-700 bg-rose-50 px-2.5 py-0.5 rounded-full border border-rose-200">
                    <?= htmlspecialchars($category_label) ?> (<?= $birth_year > 0 ? $birth_year : 'Année non renseignée' ?>)
                </span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold font-heading text-zinc-900 flex items-center gap-3">
                <span><?= htmlspecialchars($interview['first_name'] . ' ' . $interview['last_name']) ?></span>
                <?php if ($is_val): ?>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                        <span>✓</span> Validé
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-800 border border-amber-200">
                        <span>●</span> En cours
                    </span>
                <?php endif; ?>
            </h1>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <?php if ($history): ?>
                <button 
                    type="button" 
                    onclick="document.getElementById('modal-history').classList.remove('hidden')" 
                    class="px-3.5 py-2 bg-white hover:bg-zinc-50 border border-zinc-200 text-zinc-700 font-bold rounded-xl text-xs shadow-sm transition-all flex items-center gap-1.5"
                    title="Consulter les choix et objectifs de la saison précédente"
                >
                    <span>📜</span>
                    <span>Historique N-1</span>
                </button>
            <?php endif; ?>

            <a 
                href="<?= url('/admin/print-summary?interview_id=' . (int)$interview['id']) ?>" 
                target="_blank" 
                class="px-3.5 py-2 bg-white hover:bg-zinc-50 border border-zinc-200 text-zinc-700 font-bold rounded-xl text-xs shadow-sm transition-all flex items-center gap-1.5"
            >
                <span>🖨️</span>
                <span>Fiche Bilan PDF</span>
            </a>

            <button 
                type="button" 
                onclick="copyWhatsAppSynthesis(<?= (int)$interview['id'] ?>, this)" 
                class="px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-800 font-bold rounded-xl text-xs shadow-sm transition-all flex items-center gap-1.5"
            >
                <span>💬</span>
                <span>Copier WhatsApp</span>
            </button>

            <?php if ($is_val): ?>
                <form action="<?= url('/admin/reopen') ?>" method="POST" class="inline" onsubmit="return confirm('Déverrouiller cet entretien pour permettre de nouvelles modifications ?');">
                    <input type="hidden" name="interview_id" value="<?= (int)$interview['id'] ?>">
                    <button type="submit" class="px-3.5 py-2 bg-amber-50 hover:bg-amber-100 border border-amber-200 text-amber-800 font-bold rounded-xl text-xs shadow-sm transition-all flex items-center gap-1.5">
                        <span>🔓</span>
                        <span>Déverrouiller</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Formulaire d'arbitrage Coach (Split-Screen) -->
    <form action="<?= url('/admin/save-trainer') ?>" method="POST" class="space-y-6">
        <input type="hidden" name="interview_id" value="<?= (int)$interview['id'] ?>">
        <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/admin') ?>">

        <!-- Bloc 1 : Bilan de saison et Analyse causale -->
        <div class="bg-white rounded-2xl shadow-sm border border-zinc-200 overflow-hidden">
            <div class="bg-zinc-50 px-5 py-3.5 border-b border-zinc-200 flex items-center justify-between">
                <h2 class="font-heading font-bold text-sm text-zinc-900 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-rose-600 text-white flex items-center justify-center text-xs font-bold">1</span>
                    Volet 1 : Bilan sportif & Analyse causale
                </h2>
                <span class="text-xs text-zinc-500 font-medium">Comparatif Saisie Athlète / Avis Coach</span>
            </div>

            <div class="p-5 sm:p-6 grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8">
                <!-- Colonne Gauche : Réponses de l'Athlète -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between border-b border-zinc-100 pb-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-rose-700">Ce que dit l'athlète</span>
                        <span class="text-[11px] text-zinc-500">Auto-évaluation</span>
                    </div>

                    <!-- Tableau Objectifs vs Réalisations -->
                    <div class="space-y-2">
                        <span class="text-xs font-semibold text-zinc-700 block">Objectifs vs Réalisations :</span>
                        
                        <!-- Goal 1 -->
                        <div class="p-2 bg-white rounded-lg border border-zinc-200 space-y-1">
                            <label class="block text-[10px] font-semibold text-zinc-500">Objectif 1 (performance, chrono ou mesure) :</label>
                            <input type="text" name="athlete_answers[goals_results][goal_1_perf]" value="<?= htmlspecialchars($get_goal('goal_1_perf')) ?>" placeholder="Non renseigné" class="w-full px-2 py-1 border border-zinc-200 rounded text-xs bg-zinc-50 focus:bg-white">
                            <label class="block text-[10px] font-semibold text-emerald-800 pt-0.5">Résultat obtenu :</label>
                            <input type="text" name="athlete_answers[goals_results][achieved_1]" value="<?= htmlspecialchars($get_goal('achieved_1')) ?>" placeholder="Non renseigné" class="w-full px-2 py-1 border border-zinc-200 rounded text-xs bg-zinc-50 focus:bg-white font-medium text-zinc-900">
                        </div>

                        <!-- Goal 2 -->
                        <div class="p-2 bg-white rounded-lg border border-zinc-200 space-y-1">
                            <label class="block text-[10px] font-semibold text-zinc-500">Objectif 2 (sélections ou championnats) :</label>
                            <input type="text" name="athlete_answers[goals_results][goal_2_selection]" value="<?= htmlspecialchars($get_goal('goal_2_selection')) ?>" placeholder="Non renseigné" class="w-full px-2 py-1 border border-zinc-200 rounded text-xs bg-zinc-50 focus:bg-white">
                            <label class="block text-[10px] font-semibold text-emerald-800 pt-0.5">Résultat obtenu :</label>
                            <input type="text" name="athlete_answers[goals_results][achieved_2]" value="<?= htmlspecialchars($get_goal('achieved_2')) ?>" placeholder="Non renseigné" class="w-full px-2 py-1 border border-zinc-200 rounded text-xs bg-zinc-50 focus:bg-white font-medium text-zinc-900">
                        </div>

                        <!-- Goal 3 -->
                        <div class="p-2 bg-white rounded-lg border border-zinc-200 space-y-1">
                            <label class="block text-[10px] font-semibold text-zinc-500">Objectif 3 (attitude et rigueur) :</label>
                            <input type="text" name="athlete_answers[goals_results][goal_3_attitude]" value="<?= htmlspecialchars($get_goal('goal_3_attitude')) ?>" placeholder="Non renseigné" class="w-full px-2 py-1 border border-zinc-200 rounded text-xs bg-zinc-50 focus:bg-white">
                            <label class="block text-[10px] font-semibold text-emerald-800 pt-0.5">Résultat obtenu :</label>
                            <input type="text" name="athlete_answers[goals_results][achieved_3]" value="<?= htmlspecialchars($get_goal('achieved_3')) ?>" placeholder="Non renseigné" class="w-full px-2 py-1 border border-zinc-200 rounded text-xs bg-zinc-50 focus:bg-white font-medium text-zinc-900">
                        </div>
                    </div>

                    <!-- Fierté et action -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div>
                            <label class="font-semibold text-zinc-700 block mb-0.5 text-[11px]">Satisfaction majeure de la saison :</label>
                            <textarea name="athlete_answers[top_success_description]" rows="2" placeholder="Moment marquant..." class="w-full px-2.5 py-1.5 bg-zinc-50 focus:bg-white rounded-lg border border-zinc-200 text-xs"><?= htmlspecialchars($athlete_answers['top_success_description'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="font-semibold text-zinc-700 block mb-0.5 text-[11px]">Action clé ayant permis cette réussite :</label>
                            <textarea name="athlete_answers[top_success_action]" rows="2" placeholder="Action ou comportement clé..." class="w-full px-2.5 py-1.5 bg-zinc-50 focus:bg-white rounded-lg border border-zinc-200 text-xs"><?= htmlspecialchars($athlete_answers['top_success_action'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Facteurs causaux -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div>
                            <label class="font-semibold text-zinc-700 block mb-0.5 text-[11px]">Facteurs sous contrôle (choix, rigueur) :</label>
                            <textarea name="athlete_answers[causes_controllable]" rows="2" placeholder="Ce qui dépendait de l'athlète..." class="w-full px-2.5 py-1.5 bg-zinc-50 focus:bg-white rounded-lg border border-zinc-200 text-xs"><?= htmlspecialchars($athlete_answers['causes_controllable'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="font-semibold text-zinc-700 block mb-0.5 text-[11px]">Facteurs hors contrôle (météo, blessures) :</label>
                            <textarea name="athlete_answers[causes_uncontrollable]" rows="2" placeholder="Ce qui ne dépendait pas de l'athlète..." class="w-full px-2.5 py-1.5 bg-zinc-50 focus:bg-white rounded-lg border border-zinc-200 text-xs"><?= htmlspecialchars($athlete_answers['causes_uncontrollable'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Obstacle majeur -->
                    <div>
                        <label class="font-semibold text-zinc-800 block mb-0.5 text-[11px]">Principal frein à la progression identifié :</label>
                        <input type="text" name="athlete_answers[main_limiting_barrier]" value="<?= htmlspecialchars($athlete_answers['main_limiting_barrier'] ?? '') ?>" placeholder="Frein numéro un..." class="w-full px-2.5 py-1.5 bg-zinc-50 border border-zinc-200 rounded-lg text-xs font-medium text-zinc-900">
                    </div>
                </div>

                <!-- DROITE : Analyse et cadrage entraîneur -->
                <div class="space-y-3">
                    <div class="font-semibold text-zinc-500 text-[10px] uppercase tracking-wider pb-1 border-b border-zinc-100">
                        Analyse et arbitrage de l'entraîneur
                    </div>

                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1 text-[11px]">
                            Lucidité de l'athlète (auto-critique et responsabilité) :
                        </label>
                        <textarea 
                            name="trainer_answers[lucidity_assessment]" 
                            rows="4" 
                            placeholder="L'athlète assume-t-il sa part de responsabilité ou reporte-t-il sur des causes externes ?" 
                            class="w-full px-3 py-2 border border-zinc-300 rounded-xl bg-zinc-50 focus:bg-white text-xs"
                        ><?= htmlspecialchars($trainer_answers['lucidity_assessment'] ?? '') ?></textarea>
                    </div>

                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1 text-[11px]">
                            Écart d'assiduité et ponctualité réelle constatée :
                        </label>
                        <input 
                            type="text" 
                            name="trainer_answers[gap_attendance]" 
                            value="<?= htmlspecialchars($trainer_answers['gap_attendance'] ?? '') ?>" 
                            placeholder="Régularité effective sur la saison, ponctualité..." 
                            class="w-full px-3 py-2 border border-zinc-300 rounded-xl bg-zinc-50 focus:bg-white text-xs"
                        >
                    </div>
                </div>

            </div>
        </section>

        <!-- =========================================================================
             SECTION 2 : MATRICE DE CONFRONTATION DES 5 PILIERS (1 À 5)
             ========================================================================= -->
        <section class="bg-white rounded-2xl border border-zinc-200/80 shadow-sm overflow-hidden">
            <div class="px-4 py-2 bg-zinc-50/80 border-b border-zinc-200/80 flex items-center justify-between">
                <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-800 flex items-center gap-2">
                    <span class="w-4 h-4 rounded bg-rose-600 text-white flex items-center justify-center text-[10px] font-black">2</span>
                    Matrice de confrontation des 5 piliers d'engagement
                </h2>
                <span class="text-[11px] text-zinc-400">Évaluation conjointe de 1 à 5 et mesure des écarts</span>
            </div>

            <div class="p-3.5 sm:p-4 space-y-2.5 text-xs">
                
                <!-- 5 Piliers denses -->
                <div class="space-y-2">
                    <?php foreach ($pillars as $pkey => $p): ?>
                        <?php
                            $ath_val = (int)($athlete_answers[$p['ath_key']] ?? 0);
                            $coach_val = (int)($trainer_answers[$p['coach_key']] ?? 0);
                        ?>
                        <div class="p-2.5 rounded-xl border border-zinc-200/80 bg-zinc-50/60 hover:bg-zinc-100/60 transition-colors pillar-row flex flex-col md:flex-row md:items-center justify-between gap-2" data-pillar="<?= $pkey ?>">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-zinc-800 text-xs"><?= $p['label'] ?></span>
                                    <span class="pillar-gap-badge px-2 py-0.5 rounded-full text-[10px] font-bold"></span>
                                </div>
                                <p class="text-[11px] text-zinc-500 truncate"><?= $p['desc'] ?></p>
                            </div>

                            <div class="flex items-center gap-3 bg-white px-3 py-1 rounded-xl border border-zinc-200 shadow-sm flex-shrink-0">
                                <!-- Note athlète -->
                                <div class="flex items-center gap-1.5">
                                    <span class="text-[10px] font-semibold text-zinc-400">Athlète :</span>
                                    <div class="flex items-center gap-0.5">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <label class="cursor-pointer">
                                                <input 
                                                    type="radio" 
                                                    name="athlete_answers[<?= $p['ath_key'] ?>]" 
                                                    value="<?= $i ?>" 
                                                    <?= ($ath_val === $i) ? 'checked' : '' ?>
                                                    class="sr-only peer ath-rating-input"
                                                    data-pillar="<?= $pkey ?>"
                                                >
                                                <span class="w-6 h-6 rounded-md flex items-center justify-center text-[10px] font-bold border transition-all peer-checked:bg-rose-600 peer-checked:text-white peer-checked:border-rose-600 bg-zinc-100 border-zinc-200 text-zinc-700 hover:bg-zinc-200">
                                                    <?= $i ?>
                                                </span>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <div class="h-4 w-px bg-zinc-200"></div>

                                <!-- Note coach -->
                                <div class="flex items-center gap-1.5">
                                    <span class="text-[10px] font-semibold text-zinc-700">Coach :</span>
                                    <div class="flex items-center gap-0.5">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <label class="cursor-pointer">
                                                <input 
                                                    type="radio" 
                                                    name="trainer_answers[<?= $p['coach_key'] ?>]" 
                                                    value="<?= $i ?>" 
                                                    <?= ($coach_val === $i) ? 'checked' : '' ?>
                                                    class="sr-only peer coach-rating-input"
                                                >
                                                <span class="w-6 h-6 rounded-md flex items-center justify-center text-[10px] font-bold border transition-all peer-checked:bg-zinc-900 peer-checked:text-white peer-checked:border-zinc-900 bg-white border-zinc-300 text-zinc-800 hover:bg-zinc-100">
                                                    <?= $i ?>
                                                </span>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Précisions écosystème compactes -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-1">
                    <div>
                        <label class="font-semibold text-zinc-700 block mb-0.5 text-[11px]">Commentaire investissement athlète :</label>
                        <input type="text" name="athlete_answers[lifestyle_notes]" value="<?= htmlspecialchars($athlete_answers['lifestyle_notes'] ?? '') ?>" placeholder="Contraintes scolaires ou personnelles..." class="w-full px-2.5 py-1.5 bg-zinc-50 focus:bg-white rounded-lg border border-zinc-200 text-xs">
                    </div>
                    <div>
                        <label class="font-semibold text-zinc-700 block mb-0.5 text-[11px]">Écart d'implication constaté :</label>
                        <input type="text" name="trainer_answers[gap_rigor]" value="<?= htmlspecialchars($trainer_answers['gap_rigor'] ?? '') ?>" placeholder="Écart entre auto-évaluation et terrain..." class="w-full px-2.5 py-1.5 bg-zinc-50 focus:bg-white rounded-lg border border-zinc-200 text-xs">
                    </div>
                    <div>
                        <label class="font-semibold text-zinc-700 block mb-0.5 text-[11px]">Écart hygiène de vie constaté :</label>
                        <input type="text" name="trainer_answers[gap_lifestyle]" value="<?= htmlspecialchars($trainer_answers['gap_lifestyle'] ?? '') ?>" placeholder="Sommeil, récupération, soins..." class="w-full px-2.5 py-1.5 bg-zinc-50 focus:bg-white rounded-lg border border-zinc-200 text-xs">
                    </div>
                </div>

            </div>
        </section>

        <!-- =========================================================================
             SECTION 3 : RELATION D'ENTRAÎNEMENT ET CADRAGE DU STAFF
             ========================================================================= -->
        <section class="bg-white rounded-2xl border border-zinc-200/80 shadow-sm overflow-hidden">
            <div class="px-4 py-2 bg-zinc-50/80 border-b border-zinc-200/80 flex items-center justify-between">
                <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-800 flex items-center gap-2">
                    <span class="w-4 h-4 rounded bg-rose-600 text-white flex items-center justify-center text-[10px] font-black">3</span>
                    Relation d'entraînement et ajustements
                </h2>
                <span class="text-[11px] text-zinc-400">Communication, attentes réciproques et méthode</span>
            </div>

            <div class="p-3.5 sm:p-4 grid grid-cols-1 lg:grid-cols-2 gap-4 items-start text-xs">
                
                <!-- GAUCHE : Retours formulés par l'athlète -->
                <div class="space-y-2 border-b lg:border-b-0 lg:border-r border-zinc-100 lg:pr-4 pb-3 lg:pb-0">
                    <div class="font-semibold text-zinc-500 text-[10px] uppercase tracking-wider pb-1 border-b border-zinc-100">
                        Retours formulés par l'athlète
                    </div>
                    <div>
                        <label class="font-semibold text-zinc-800 block mb-0.5 text-[11px]">Points forts de l'encadrement à préserver :</label>
                        <textarea name="athlete_answers[coach_positives_to_keep]" rows="2" class="w-full px-2.5 py-1.5 bg-zinc-50 rounded-lg border border-zinc-200 text-zinc-800 text-xs"><?= htmlspecialchars($athlete_answers['coach_positives_to_keep'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <label class="font-semibold text-zinc-800 block mb-0.5 text-[11px]">Points d'incompréhension ou de friction :</label>
                        <textarea name="athlete_answers[coach_friction_points]" rows="2" class="w-full px-2.5 py-1.5 bg-zinc-50 rounded-lg border border-zinc-200 text-zinc-800 text-xs"><?= htmlspecialchars($athlete_answers['coach_friction_points'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <label class="font-semibold text-zinc-800 block mb-0.5 text-[11px]">Besoins exprimés par l'athlète pour la suite :</label>
                        <textarea name="athlete_answers[coach_needs_next_season]" rows="2" class="w-full px-2.5 py-1.5 bg-zinc-50 rounded-lg border border-zinc-200 text-zinc-800 text-xs font-medium"><?= htmlspecialchars($athlete_answers['coach_needs_next_season'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- DROITE : Engagements de l'entraîneur -->
                <div class="space-y-2">
                    <div class="font-semibold text-zinc-500 text-[10px] uppercase tracking-wider pb-1 border-b border-zinc-100">
                        Ajustements convenus par l'entraîneur
                    </div>
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-0.5 text-[11px]">Besoins spécifiques validés par le staff :</label>
                        <textarea 
                            name="trainer_answers[approved_athlete_needs]" 
                            rows="3" 
                            placeholder="Retours vidéo, suivi individualisé, fermeté..." 
                            class="w-full px-2.5 py-1.5 border border-zinc-300 rounded-lg bg-zinc-50 focus:bg-white text-xs"
                        ><?= htmlspecialchars($trainer_answers['approved_athlete_needs'] ?? ($athlete_answers['coach_needs_next_season'] ?? '')) ?></textarea>
                    </div>
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-0.5 text-[11px]">Ajustements pédagogiques pris en compte :</label>
                        <textarea 
                            name="trainer_answers[coach_adjustments]" 
                            rows="3" 
                            placeholder="Communication, gestion du stress en concours, explications..." 
                            class="w-full px-2.5 py-1.5 border border-zinc-300 rounded-lg bg-zinc-50 focus:bg-white text-xs"
                        ><?= htmlspecialchars($trainer_answers['coach_adjustments'] ?? '') ?></textarea>
                    </div>
                </div>

            </div>
        </section>

        <!-- =========================================================================
             SECTION 4 : PROJET SPORTIF ET CONTRAT D'ENGAGEMENT
             ========================================================================= -->
        <section class="bg-white rounded-2xl border border-zinc-200/80 shadow-sm overflow-hidden">
            <div class="px-4 py-2 bg-zinc-50/80 border-b border-zinc-200/80 flex items-center justify-between">
                <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-800 flex items-center gap-2">
                    <span class="w-4 h-4 rounded bg-rose-600 text-white flex items-center justify-center text-[10px] font-black">4</span>
                    Projet sportif et contrat d'engagement
                </h2>
                <span class="text-[11px] text-zinc-400">Disciplines, volume et 2 règles du contrat moral</span>
            </div>

            <div class="p-3.5 sm:p-4 grid grid-cols-1 lg:grid-cols-2 gap-4 items-start text-xs">
                
                <!-- GAUCHE : Souhaits déclarés de l'athlète -->
                <div class="space-y-2.5 border-b lg:border-b-0 lg:border-r border-zinc-100 lg:pr-4 pb-3 lg:pb-0">
                    <div class="font-semibold text-zinc-500 text-[10px] uppercase tracking-wider pb-1 border-b border-zinc-100">
                        Souhaits exprimés par l'athlète
                    </div>

                    <!-- Disciplines souhaitées -->
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="font-semibold text-zinc-700 block mb-0.5 text-[11px]">Discipline 1 souhaitée :</label>
                            <input type="text" list="disciplines-suggestions" name="athlete_answers[chosen_discipline_1]" value="<?= htmlspecialchars($athlete_answers['chosen_discipline_1'] ?? '') ?>" placeholder="Ex : 100m, Haies..." class="w-full px-2.5 py-1.5 border border-zinc-200 rounded-lg text-xs bg-zinc-50 focus:bg-white font-medium">
                        </div>
                        <div>
                            <label class="font-semibold text-zinc-700 block mb-0.5 text-[11px]">Discipline 2 souhaitée :</label>
                            <input type="text" list="disciplines-suggestions" name="athlete_answers[chosen_discipline_2]" value="<?= htmlspecialchars($athlete_answers['chosen_discipline_2'] ?? '') ?>" placeholder="Ex : Longueur..." class="w-full px-2.5 py-1.5 border border-zinc-200 rounded-lg text-xs bg-zinc-50 focus:bg-white font-medium">
                        </div>
                    </div>

                    <!-- Alerte conflit disciplines athlète -->
                    <div id="ath-discipline-conflict-alert" class="hidden p-2.5 rounded-xl border border-amber-300 bg-amber-50 text-amber-900 text-[11px] flex items-start gap-2 shadow-sm">
                        <span class="font-bold text-amber-700 flex-shrink-0">⚠️</span>
                        <div id="ath-discipline-conflict-text" class="leading-tight font-medium"></div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="font-semibold text-zinc-700 block mb-0.5 text-[11px]">Performance visée :</label>
                            <input type="text" name="athlete_answers[target_performance]" value="<?= htmlspecialchars($athlete_answers['target_performance'] ?? '') ?>" placeholder="Ex : 11.20s..." class="w-full px-2.5 py-1.5 border border-zinc-200 rounded-lg text-xs bg-zinc-50 focus:bg-white font-medium">
                        </div>
                        <div>
                            <label class="font-semibold text-zinc-700 block mb-0.5 text-[11px]">Championnats visés :</label>
                            <input type="text" name="athlete_answers[target_competitions]" value="<?= htmlspecialchars($athlete_answers['target_competitions'] ?? '') ?>" placeholder="Ex : Finale CS..." class="w-full px-2.5 py-1.5 border border-zinc-200 rounded-lg text-xs bg-zinc-50 focus:bg-white font-medium">
                        </div>
                    </div>

                    <!-- Engagements d'attitude -->
                    <div class="p-2 bg-zinc-50/60 rounded-xl border border-zinc-200 space-y-1">
                        <div class="flex items-center justify-between">
                            <label class="font-semibold text-zinc-700 text-[11px]">Engagement d'attitude 1 :</label>
                            <button 
                                type="button" 
                                onclick="copyToRule(1, document.getElementById('ath-commit-1').value)" 
                                class="text-[10px] text-rose-600 hover:underline font-bold"
                            >
                                Copier dans Règle 1 →
                            </button>
                        </div>
                        <input type="text" id="ath-commit-1" name="athlete_answers[commitment_1]" value="<?= htmlspecialchars($athlete_answers['commitment_1'] ?? '') ?>" placeholder="Ex : Rigueur sans faille..." class="w-full px-2 py-1 border border-zinc-200 rounded-lg text-xs bg-white font-medium">
                    </div>

                    <div class="p-2 bg-zinc-50/60 rounded-xl border border-zinc-200 space-y-1">
                        <div class="flex items-center justify-between">
                            <label class="font-semibold text-zinc-700 text-[11px]">Engagement d'attitude 2 :</label>
                            <button 
                                type="button" 
                                onclick="copyToRule(2, document.getElementById('ath-commit-2').value)" 
                                class="text-[10px] text-rose-600 hover:underline font-bold"
                            >
                                Copier dans Règle 2 →
                            </button>
                        </div>
                        <input type="text" id="ath-commit-2" name="athlete_answers[commitment_2]" value="<?= htmlspecialchars($athlete_answers['commitment_2'] ?? '') ?>" placeholder="Ex : Communication des douleurs sous 24h..." class="w-full px-2 py-1 border border-zinc-200 rounded-lg text-xs bg-white font-medium">
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="font-semibold text-zinc-700 block mb-0.5 text-[11px]">Volume souhaité (séances par semaine) :</label>
                            <input type="number" name="athlete_answers[target_sessions_count]" min="2" max="8" value="<?= htmlspecialchars((string)($athlete_answers['target_sessions_count'] ?? '4')) ?>" class="w-full px-2.5 py-1.5 border border-zinc-200 rounded-lg text-xs bg-zinc-50 focus:bg-white font-medium">
                        </div>
                        <div>
                            <label class="font-semibold text-zinc-700 block mb-0.5 text-[11px]">Situation scolaire ou professionnelle :</label>
                            <input type="text" name="athlete_answers[study_work_situation]" value="<?= htmlspecialchars($athlete_answers['study_work_situation'] ?? '') ?>" placeholder="Ex : Collège 3e..." class="w-full px-2.5 py-1.5 border border-zinc-200 rounded-lg text-xs bg-zinc-50 focus:bg-white font-medium">
                        </div>
                    </div>

                    <!-- Jours disponibles déclarés -->
                    <div>
                        <label class="font-semibold text-zinc-700 block mb-1 text-[11px]">Jours déclarés disponibles :</label>
                        <div class="grid grid-cols-4 sm:grid-cols-7 gap-1">
                            <?php
                                $week_days = ['monday' => 'Lu', 'tuesday' => 'Ma', 'wednesday' => 'Me', 'thursday' => 'Je', 'friday' => 'Ve', 'saturday' => 'Sa', 'sunday' => 'Di'];
                                foreach ($week_days as $dk => $dl):
                                    $checked = in_array($dk, $ath_days, true);
                            ?>
                                <label class="flex items-center justify-center p-1 rounded-lg border text-center cursor-pointer text-xs <?= $checked ? 'bg-rose-50 border-rose-300 font-bold text-rose-950' : 'border-zinc-200 text-zinc-600 bg-white' ?>">
                                    <input type="checkbox" name="athlete_answers[available_days][]" value="<?= $dk ?>" <?= $checked ? 'checked' : '' ?> class="sr-only">
                                    <span><?= $dl ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Cadres sportifs déclarés -->
                    <div>
                        <label class="font-semibold text-zinc-700 block mb-1 text-[11px]">Cadres déclarés :</label>
                        <div class="grid grid-cols-2 gap-1">
                            <?php foreach (CategoryHelper::get_cadres() as $ck => $cl): ?>
                                <?php $c_checked = in_array($ck, $ath_cadres, true); ?>
                                <label class="flex items-center gap-1.5 p-1.5 rounded-lg border cursor-pointer text-xs <?= $c_checked ? 'bg-rose-50 border-rose-300 font-bold text-rose-950' : 'border-zinc-200 text-zinc-600 bg-white' ?>">
                                    <input type="checkbox" name="athlete_answers[cadres][]" value="<?= $ck ?>" <?= $c_checked ? 'checked' : '' ?> class="sr-only">
                                    <span><?= htmlspecialchars($cl) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- DROITE : Arbitrage et contrat validé -->
                <div class="space-y-2.5">
                    <div class="font-semibold text-zinc-500 text-[10px] uppercase tracking-wider pb-1 border-b border-zinc-100">
                        Arbitrage et contrat validé par l'entraîneur
                    </div>

                    <!-- Réalisme du projet -->
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-0.5 text-[11px]">Les ambitions déclarées sont-elles réalistes ?</label>
                        <?php $is_real = $decisions['is_project_realistic'] ?? '1'; ?>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-1.5 cursor-pointer font-semibold text-emerald-800">
                                <input type="radio" name="decisions[is_project_realistic]" value="1" <?= $is_real === '1' ? 'checked' : '' ?> class="text-zinc-900 focus:ring-zinc-900">
                                <span>Oui, projet réaliste</span>
                            </label>
                            <label class="flex items-center gap-1.5 cursor-pointer font-semibold text-rose-800">
                                <input type="radio" name="decisions[is_project_realistic]" value="0" <?= $is_real === '0' ? 'checked' : '' ?> class="text-zinc-900 focus:ring-zinc-900">
                                <span>Non, recadrage nécessaire</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-zinc-700 mb-0.5 text-[11px]">Recadrage acté d'un commun accord :</label>
                        <textarea 
                            name="decisions[project_reframing]" 
                            rows="2" 
                            placeholder="Ajustements négociés des objectifs..." 
                            class="w-full px-2.5 py-1.5 border border-zinc-300 rounded-lg bg-zinc-50 focus:bg-white text-xs"
                        ><?= htmlspecialchars($decisions['project_reframing'] ?? '') ?></textarea>
                    </div>

                    <!-- Disciplines validées -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div>
                            <label class="block font-semibold text-zinc-700 mb-0.5 text-[11px]">Discipline prioritaire validée :</label>
                            <input 
                                type="text" 
                                list="disciplines-suggestions"
                                name="decisions[primary_discipline]" 
                                value="<?= htmlspecialchars($decisions['primary_discipline'] ?? ($athlete_answers['chosen_discipline_1'] ?? 'Sprint')) ?>" 
                                placeholder="Ex : 100m, Haies, Perche..." 
                                class="w-full px-2.5 py-1.5 border border-zinc-300 rounded-lg bg-zinc-50 focus:bg-white text-xs font-bold text-zinc-900"
                            >
                        </div>
                        <div>
                            <label class="block font-semibold text-zinc-700 mb-0.5 text-[11px]">Discipline secondaire validée :</label>
                            <input 
                                type="text" 
                                list="disciplines-suggestions"
                                name="decisions[secondary_discipline]" 
                                value="<?= htmlspecialchars($decisions['secondary_discipline'] ?? ($athlete_answers['chosen_discipline_2'] ?? '')) ?>" 
                                placeholder="Ex : Longueur, Haies courtes..." 
                                class="w-full px-2.5 py-1.5 border border-zinc-300 rounded-lg bg-zinc-50 focus:bg-white text-xs font-medium"
                            >
                        </div>
                    </div>

                    <!-- Alerte conflit disciplines validées coach -->
                    <div id="coach-discipline-conflict-alert" class="hidden p-2.5 rounded-xl border border-amber-300 bg-amber-50 text-amber-900 text-[11px] flex items-start gap-2 shadow-sm">
                        <span class="font-bold text-amber-700 flex-shrink-0">⚠️</span>
                        <div id="coach-discipline-conflict-text" class="leading-tight font-medium"></div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div>
                            <label class="block font-semibold text-zinc-700 mb-0.5 text-[11px]">Volume hebdomadaire acté (séances) :</label>
                            <input 
                                type="number" 
                                name="decisions[approved_weekly_sessions]" 
                                min="2" 
                                max="8" 
                                value="<?= htmlspecialchars((string)($decisions['approved_weekly_sessions'] ?? ($athlete_answers['target_sessions_count'] ?? '4'))) ?>" 
                                class="w-full px-2.5 py-1.5 border border-zinc-300 rounded-lg bg-zinc-50 focus:bg-white text-xs font-bold"
                            >
                        </div>
                        <div>
                            <label class="block font-semibold text-zinc-700 mb-0.5 text-[11px]">Échéance majeure cible :</label>
                            <input 
                                type="text" 
                                name="decisions[target_milestones]" 
                                value="<?= htmlspecialchars($decisions['target_milestones'] ?? ($athlete_answers['target_competitions'] ?? ($athlete_answers['target_performance'] ?? 'Championnats suisses'))) ?>" 
                                class="w-full px-2.5 py-1.5 border border-zinc-300 rounded-lg bg-zinc-50 focus:bg-white text-xs font-medium"
                            >
                        </div>
                    </div>

                    <!-- Jours retenus -->
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1 text-[11px]">Jours d'entraînement retenus :</label>
                        <div class="grid grid-cols-4 sm:grid-cols-7 gap-1">
                            <?php
                                $all_days = ['monday' => 'Lu', 'tuesday' => 'Ma', 'wednesday' => 'Me', 'thursday' => 'Je', 'friday' => 'Ve', 'saturday' => 'Sa', 'sunday' => 'Di'];
                                foreach ($all_days as $dk => $dl):
                                    $d_checked = in_array($dk, $selected_days, true);
                            ?>
                                <label class="flex items-center justify-center p-1 rounded-lg border text-center cursor-pointer text-xs transition-all has-[:checked]:bg-zinc-900 has-[:checked]:border-zinc-900 has-[:checked]:text-white has-[:checked]:font-bold border-zinc-200 hover:bg-zinc-100 text-zinc-700 bg-white">
                                    <input type="checkbox" name="decisions[approved_training_days][]" value="<?= $dk ?>" <?= $d_checked ? 'checked' : '' ?> class="sr-only">
                                    <span><?= $dl ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Cadres validés par l'entraîneur -->
                    <div>
                        <label class="block font-semibold text-zinc-700 mb-1 text-[11px]">Cadres validés :</label>
                        <div class="grid grid-cols-2 gap-1">
                            <?php foreach (CategoryHelper::get_cadres() as $ck => $cl): ?>
                                <?php $c_checked = in_array($ck, $selected_cadres, true); ?>
                                <label class="flex items-center gap-1.5 p-1.5 rounded-lg border cursor-pointer text-xs transition-all has-[:checked]:bg-zinc-900 has-[:checked]:border-zinc-900 has-[:checked]:text-white has-[:checked]:font-bold border-zinc-200 hover:bg-zinc-100 text-zinc-700 bg-white">
                                    <input type="checkbox" name="decisions[cadres][]" value="<?= $ck ?>" <?= $c_checked ? 'checked' : '' ?> class="sr-only">
                                    <span><?= htmlspecialchars($cl) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 2 Règles non négociables -->
                    <div class="pt-1.5 space-y-1.5 border-t border-zinc-100">
                        <span class="font-bold text-zinc-900 block text-xs">Contrat moral (2 comportements non négociables) :</span>
                        <div>
                            <label class="block font-semibold text-zinc-600 text-[10px] mb-0.5">Règle 1 :</label>
                            <input 
                                type="text" 
                                id="rule-input-1"
                                name="decisions[mandatory_rule_1]" 
                                value="<?= htmlspecialchars($decisions['mandatory_rule_1'] ?? ($athlete_answers['commitment_1'] ?? 'Présence régulière et échauffement sans retard')) ?>" 
                                class="w-full px-2.5 py-1.5 border border-zinc-300 rounded-lg bg-zinc-50 focus:bg-white text-xs font-semibold text-zinc-900"
                            >
                        </div>
                        <div>
                            <label class="block font-semibold text-zinc-600 text-[10px] mb-0.5">Règle 2 :</label>
                            <input 
                                type="text" 
                                id="rule-input-2"
                                name="decisions[mandatory_rule_2]" 
                                value="<?= htmlspecialchars($decisions['mandatory_rule_2'] ?? ($athlete_answers['commitment_2'] ?? 'Communication immédiate de toute douleur sous 24 heures')) ?>" 
                                class="w-full px-2.5 py-1.5 border border-zinc-300 rounded-lg bg-zinc-50 focus:bg-white text-xs font-semibold text-zinc-900"
                            >
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- =========================================================================
             SECTION 5 : REMARQUES PÉDAGOGIQUES GLOBALES
             ========================================================================= -->
        <section class="bg-white rounded-2xl border border-zinc-200/80 shadow-sm p-3.5 sm:p-4 space-y-2 text-xs">
            <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-800 flex items-center gap-2 pb-1 border-b border-zinc-100">
                <span class="w-4 h-4 rounded bg-rose-600 text-white flex items-center justify-center text-[10px] font-black">5</span>
                Remarques pédagogiques globales de l'entraîneur
            </h2>
            <div>
                <label class="block font-semibold text-zinc-700 mb-1 text-[11px]">Notes confidentielles et consignes particulières :</label>
                <textarea 
                    name="trainer_notes" 
                    rows="2" 
                    placeholder="Synthèse de l'entretien ou consignes spécifiques..." 
                    class="w-full px-3 py-1.5 border border-zinc-300 rounded-lg bg-zinc-50 focus:bg-white text-xs"
                ><?= htmlspecialchars($interview['trainer_notes'] ?? '') ?></textarea>
            </div>
        </section>

        <!-- BARRE COMPACTE D'ACTIONS -->
        <div class="bg-white/95 backdrop-blur-md px-4 py-3 rounded-2xl border border-zinc-200/80 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3 sticky bottom-3 z-20">
            <div class="text-[11px] text-zinc-500">
                Enregistrement conjointe des arbitrages du coach et des corrections de l'athlète.
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto">
                <button 
                    type="submit" 
                    name="action" 
                    value="save" 
                    class="w-full sm:w-auto px-4 py-2 bg-zinc-100 hover:bg-zinc-200 text-zinc-800 text-xs font-bold rounded-xl border border-zinc-200 transition-colors"
                >
                    Enregistrer le brouillon
                </button>

                <button 
                    type="submit" 
                    name="action" 
                    value="validate" 
                    class="w-full sm:w-auto px-5 py-2 bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-bold rounded-xl shadow-sm transition-all flex items-center justify-center gap-1.5"
                    onclick="return confirm('Valider officiellement cet entretien d\'un commun accord ?');"
                >
                    <span>Valider l'entretien</span>
                </button>
            </div>
        </div>

    </form>

</div>

<!-- Modal Historique Saison Précédente -->
<?php if ($history): ?>
    <?php
        $hist_ath = safe_json_decode($history['athlete_answers'] ?? null);
        $hist_trainer = safe_json_decode($history['trainer_answers'] ?? null);
        $hist_dec = safe_json_decode($history['decisions'] ?? null);

        $h_d1_raw = $hist_dec['primary_discipline'] 
            ?? ($hist_dec['friday_discipline_approved'] 
            ?? ($hist_dec['approved_disciplines'][0] 
            ?? ($hist_ath['chosen_discipline_1'] 
            ?? ($hist_ath['friday_discipline'] 
            ?? ''))));
        $h_d2_raw = $hist_dec['secondary_discipline'] 
            ?? ($hist_dec['approved_disciplines'][1] 
            ?? ($hist_ath['chosen_discipline_2'] 
            ?? ''));
        $h_d1 = CategoryHelper::get_discipline_label($h_d1_raw);
        $h_d2 = CategoryHelper::get_discipline_label($h_d2_raw);

        $h_sessions = $hist_dec['approved_weekly_sessions'] ?? ($hist_ath['target_sessions_count'] ?? '');
        $h_days = $hist_dec['approved_training_days'] ?? ($hist_ath['available_days'] ?? []);
        if (!is_array($h_days)) $h_days = [$h_days];
        $h_days_map = ['monday'=>'Lu', 'tuesday'=>'Ma', 'wednesday'=>'Me', 'thursday'=>'Je', 'friday'=>'Ve', 'saturday'=>'Sa', 'sunday'=>'Di'];
        $h_days_str = implode('-', array_map(fn($d) => $h_days_map[$d] ?? $d, $h_days));

        $h_pride = $hist_ath['pride_highlight'] ?? ($hist_ath['top_success_description'] ?? '');
        $h_target = $hist_dec['target_milestones'] ?? ($hist_ath['target_performance'] ?? ($hist_ath['target_competitions'] ?? ''));
        $h_rule1 = $hist_dec['mandatory_rule_1'] ?? ($hist_ath['attitude_contract'] ?? '');
        $h_rule2 = $hist_dec['mandatory_rule_2'] ?? '';
    ?>
    <div id="modal-history" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4">
        <div class="modal-content-card rounded-2xl shadow-2xl border border-zinc-200 max-w-lg w-full overflow-hidden max-h-[85vh] flex flex-col relative z-10 bg-white">
            <div class="p-4 border-b border-zinc-100 flex items-center justify-between">
                <div>
                    <h3 class="font-bold font-heading text-sm text-zinc-900">Historique saison <?= htmlspecialchars($history['season_name'] ?? 'N-1') ?></h3>
                    <p class="text-xs text-zinc-400">Fiche archivée de <?= htmlspecialchars($interview['first_name'] . ' ' . $interview['last_name']) ?></p>
                </div>
                <button onclick="closeModal('modal-history')" class="text-zinc-400 hover:text-zinc-700 text-sm">✕</button>
            </div>
            <div class="p-4 overflow-y-auto space-y-3 text-xs text-zinc-700">
                <!-- 1. Projet sportif N-1 -->
                <div class="p-3 bg-zinc-50 rounded-xl border border-zinc-200 space-y-1.5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500 block">1. Orientation et volume N-1</span>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <span class="text-zinc-500 block text-[11px]">Disciplines :</span>
                            <strong class="text-zinc-900"><?= htmlspecialchars($h_d1) ?><?= ($h_d2 && $h_d2 !== 'Aucune') ? ' + ' . htmlspecialchars($h_d2) : '' ?></strong>
                        </div>
                        <div>
                            <span class="text-zinc-500 block text-[11px]">Volume & Jours :</span>
                            <strong class="text-zinc-900"><?= $h_sessions ? htmlspecialchars((string)$h_sessions) . 'x/sem.' : 'Non spécifié' ?><?= $h_days_str ? ' (' . htmlspecialchars($h_days_str) . ')' : '' ?></strong>
                        </div>
                    </div>
                </div>

                <!-- 2. Fierté & Objectifs fixés N-1 -->
                <div class="p-3 bg-zinc-50 rounded-xl border border-zinc-200 space-y-1.5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500 block">2. Fierté & Objectifs N-1</span>
                    <?php if ($h_pride): ?>
                        <div>
                            <span class="text-zinc-500 block text-[11px]">Fierté majeure passée :</span>
                            <p class="italic text-zinc-800 mt-0.5">« <?= htmlspecialchars($h_pride) ?> »</p>
                        </div>
                    <?php endif; ?>
                    <?php if ($h_target): ?>
                        <div class="pt-1 border-t border-zinc-200/60">
                            <span class="text-zinc-500 block text-[11px]">Objectifs visés la saison passée :</span>
                            <p class="font-semibold text-zinc-900 mt-0.5"><?= htmlspecialchars($h_target) ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 3. Contrat moral N-1 -->
                <?php if ($h_rule1 || $h_rule2): ?>
                    <div class="p-3 bg-zinc-50 rounded-xl border border-zinc-200 space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500 block">3. Contrat moral acté l'an passé</span>
                            <span class="text-[10px] text-zinc-400">Cliquez pour reporter</span>
                        </div>
                        <?php if ($h_rule1): ?>
                            <div class="flex items-center justify-between gap-2 p-1.5 rounded-lg hover:bg-white border border-transparent hover:border-zinc-200 transition-colors cursor-pointer" onclick="copyToRule(1, '<?= addslashes($h_rule1) ?>')">
                                <p><strong>1.</strong> <?= htmlspecialchars($h_rule1) ?></p>
                                <span class="text-[10px] text-brand-600 font-bold shrink-0">Copier règle 1 ↵</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($h_rule2): ?>
                            <div class="flex items-center justify-between gap-2 p-1.5 rounded-lg hover:bg-white border border-transparent hover:border-zinc-200 transition-colors cursor-pointer" onclick="copyToRule(2, '<?= addslashes($h_rule2) ?>')">
                                <p><strong>2.</strong> <?= htmlspecialchars($h_rule2) ?></p>
                                <span class="text-[10px] text-brand-600 font-bold shrink-0">Copier règle 2 ↵</span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- 4. Remarque coach N-1 -->
                <?php if (!empty($history['trainer_notes']) || !empty($hist_trainer['general_comment'])): ?>
                    <div class="p-3 bg-zinc-50 rounded-xl border border-zinc-200 space-y-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500 block">4. Remarque du coach N-1</span>
                        <p class="italic text-zinc-700">
                            <?= nl2br(htmlspecialchars($history['trainer_notes'] ?: ($hist_trainer['general_comment'] ?? ''))) ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function openModal(id) {
    document.getElementById(id)?.classList.remove('hidden');
}

function closeModal(id) {
    document.getElementById(id)?.classList.add('hidden');
}

function copyToRule(ruleNumber, text) {
    const input = document.getElementById(`rule-input-${ruleNumber}`);
    if (input && text) {
        input.value = text;
        input.focus();
        showToast(`Engagement copié dans la règle ${ruleNumber}`, 'success');
    }
}

// Calcul et mise à jour dynamique des écarts sur les 5 piliers
function updatePillarGaps() {
    const rows = document.querySelectorAll('.pillar-row');
    rows.forEach(row => {
        const athInput = row.querySelector(`.ath-rating-input:checked`);
        const coachInput = row.querySelector('.coach-rating-input:checked');
        const badge = row.querySelector('.pillar-gap-badge');

        if (!badge) return;

        const athScore = athInput ? parseInt(athInput.value, 10) : 0;
        const coachScore = coachInput ? parseInt(coachInput.value, 10) : 0;

        if (athScore === 0 || coachScore === 0) {
            badge.className = 'pillar-gap-badge hidden';
            badge.textContent = '';
            return;
        }

        const delta = coachScore - athScore;

        if (delta === 0) {
            badge.className = 'pillar-gap-badge px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200';
            badge.textContent = 'Alignés (écart 0)';
        } else if (delta < 0) {
            badge.className = 'pillar-gap-badge px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-800 border border-rose-200';
            badge.textContent = `Écart (${delta})`;
        } else {
            badge.className = 'pillar-gap-badge px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-800 border border-blue-200';
            badge.textContent = `Écart (+${delta})`;
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const coachRatingInputs = document.querySelectorAll('.coach-rating-input');
    coachRatingInputs.forEach(input => input.addEventListener('change', updatePillarGaps));

    const athRatingInputs = document.querySelectorAll('.ath-rating-input');
    athRatingInputs.forEach(input => input.addEventListener('change', updatePillarGaps));

    updatePillarGaps();
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
