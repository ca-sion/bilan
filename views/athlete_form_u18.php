<?php
$page_title = "Bilan individuel de saison — " . htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']);
$is_locked = in_array($interview['status'], ['submitted', 'completed'], true);
$needs_birth_date = empty($athlete['birth_date']) || $athlete['access_pin'] === '0000';
$goals = is_array($answers['goals_results'] ?? null) ? $answers['goals_results'] : [];
$get_goal = fn(string $k): string => (string)($goals[$k] ?? $answers["goals_results[{$k}]"] ?? $answers[$k] ?? '');
$selected_days = $answers['available_days'] ?? [];
if (!is_array($selected_days)) $selected_days = [$selected_days];
$selected_cadres = $answers['cadres'] ?? [];
if (!is_array($selected_cadres)) $selected_cadres = [$selected_cadres];
ob_start();
?>

<div class="max-w-4xl mx-auto my-4 space-y-6">

    <!-- Modal date de naissance si PIN 0000 -->
    <?php if ($needs_birth_date): ?>
        <div class="bg-amber-50 border-2 border-amber-300 rounded-2xl p-6 shadow-md animate-fade-in">
            <div class="flex items-start gap-4">
                <span class="text-3xl">🎂</span>
                <div class="flex-1">
                    <h2 class="text-base font-bold text-amber-900">Complète ta date de naissance</h2>
                    <p class="text-xs text-amber-700 mt-1">
                        Pour sécuriser ton accès et calculer automatiquement ton futur code PIN (jour et mois), renseigne ta date de naissance.
                    </p>
                    <form action="<?= url('/update-birth-date') ?>" method="POST" class="mt-4 flex flex-wrap items-center gap-3">
                        <input 
                            type="date" 
                            name="birth_date" 
                            required 
                            class="px-3 py-2 border border-amber-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 bg-white"
                        >
                        <button 
                            type="submit" 
                            class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl text-sm shadow transition-colors"
                        >
                            Enregistrer ma date de naissance
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- En-tête de la fiche athlète -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200 mb-2">
                <span>Athlète individuel &bull; <?= htmlspecialchars($category_label) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold font-heading text-slate-900">
                <?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?>
            </h1>
        </div>

        <div class="flex items-center gap-3">
            <?php if ($is_locked): ?>
                <span class="inline-flex items-center px-3.5 py-1.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                    ✓ Bilan transmis et verrouillé
                </span>
            <?php else: ?>
                <div id="autosave-indicator" class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border text-slate-500 bg-slate-50 border-slate-200">
                    <span class="inline-block w-2 h-2 rounded-full bg-slate-300 mr-2"></span>
                    <span>Modifications enregistrées</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($is_locked): ?>
        <div class="bg-blue-50 border border-blue-200 rounded-2xl p-5 text-sm text-blue-900 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🔒</span>
                <div>
                    <strong>Ton bilan individuel a été transmis à tes entraîneurs.</strong>
                    <p class="text-xs text-blue-700 mt-0.5">Tu souhaites modifier ou corriger des éléments avant ton entretien ? Tu peux déverrouiller tes réponses en 1 clic.</p>
                </div>
            </div>
            <form action="<?= url('/unlock') ?>" method="POST" class="shrink-0">
                <input type="hidden" name="interview_id" value="<?= (int)$interview['id'] ?>">
                <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-white hover:bg-slate-50 text-slate-800 font-bold rounded-xl border border-slate-300 shadow-sm text-xs transition-colors inline-flex items-center justify-center gap-2">
                    <span>✏️</span>
                    <span>Modifier mes réponses</span>
                </button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Onglets de navigation rapide -->
    <div class="no-print bg-slate-200/70 p-1.5 rounded-2xl flex flex-wrap gap-1 text-xs font-bold">
        <a href="#volet-1" class="flex-1 py-2.5 px-3 text-center rounded-xl bg-white text-slate-900 shadow-sm hover:text-brand-600 transition-colors">
            1. Bilan de saison
        </a>
        <a href="#volet-2" class="flex-1 py-2.5 px-3 text-center rounded-xl text-slate-600 hover:bg-white/80 hover:text-slate-900 transition-colors">
            2. Auto-évaluation des 5 piliers
        </a>
        <a href="#volet-3" class="flex-1 py-2.5 px-3 text-center rounded-xl text-slate-600 hover:bg-white/80 hover:text-slate-900 transition-colors">
            3. Relation d'entraînement
        </a>
        <a href="#volet-4" class="flex-1 py-2.5 px-3 text-center rounded-xl text-slate-600 hover:bg-white/80 hover:text-slate-900 transition-colors">
            4. Saison à venir
        </a>
    </div>

    <!-- Formulaire U18+ -->
    <form id="athlete-u18-form" class="space-y-8">

        <!-- VOLET 1 : Bilan de saison -->
        <section id="volet-1" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8 space-y-6">
            <div class="border-b border-slate-100 pb-4">
                <h2 class="text-xl font-bold font-heading text-slate-900 flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-zinc-900 text-white flex items-center justify-center text-sm font-black">1</span>
                    Bilan de saison
                </h2>
            </div>

            <!-- Tableau Objectifs vs Réalisations -->
            <div class="space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                    <label class="block text-sm font-semibold text-slate-800">
                        Objectifs
                    </label>
                    <?php if (!empty($previous_summary) && $previous_summary['has_goals']): ?>
                        <span class="text-[11px] font-bold text-indigo-700 flex items-center gap-1">
                            <span>💡</span> Historique disponible (saison <?= htmlspecialchars($previous_summary['season_name']) ?>)
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($previous_summary) && $previous_summary['has_goals']): ?>
                    <div class="p-3.5 bg-indigo-50/80 border border-indigo-200/90 rounded-xl space-y-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-bold text-indigo-950 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Tes objectifs fixés en <?= htmlspecialchars($previous_summary['season_name']) ?>
                            </span>
                            <span class="text-[10px] text-indigo-600 font-medium hidden sm:inline">Clique pour insérer dans le champ</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                            <?php if (!empty($previous_summary['perf_goal'])): ?>
                                <div class="p-2.5 bg-white rounded-lg border border-indigo-100 shadow-2xs flex flex-col justify-between gap-2">
                                    <div>
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-700 block">Performance</span>
                                        <p class="text-xs text-slate-800 font-medium line-clamp-2" title="<?= htmlspecialchars($previous_summary['perf_goal']) ?>">
                                            « <?= htmlspecialchars($previous_summary['perf_goal']) ?> »
                                        </p>
                                    </div>
                                    <?php if (!$is_locked): ?>
                                        <button 
                                            type="button" 
                                            onclick="insertPreviousGoal('goals_results[goal_1_perf]', <?= htmlspecialchars(json_encode($previous_summary['perf_goal']), ENT_QUOTES, 'UTF-8') ?>)"
                                            class="self-start text-[11px] font-bold text-indigo-700 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-2 py-0.5 rounded transition-colors"
                                        >
                                            📋 Reprendre en objectif 1
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($previous_summary['comp_goal'])): ?>
                                <div class="p-2.5 bg-white rounded-lg border border-indigo-100 shadow-2xs flex flex-col justify-between gap-2">
                                    <div>
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-700 block">Compétition</span>
                                        <p class="text-xs text-slate-800 font-medium line-clamp-2" title="<?= htmlspecialchars($previous_summary['comp_goal']) ?>">
                                            « <?= htmlspecialchars($previous_summary['comp_goal']) ?> »
                                        </p>
                                    </div>
                                    <?php if (!$is_locked): ?>
                                        <button 
                                            type="button" 
                                            onclick="insertPreviousGoal('goals_results[goal_2_selection]', <?= htmlspecialchars(json_encode($previous_summary['comp_goal']), ENT_QUOTES, 'UTF-8') ?>)"
                                            class="self-start text-[11px] font-bold text-indigo-700 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-2 py-0.5 rounded transition-colors"
                                        >
                                            📋 Reprendre en objectif 2
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($previous_summary['attitude_goal'])): ?>
                                <div class="p-2.5 bg-white rounded-lg border border-indigo-100 shadow-2xs flex flex-col justify-between gap-2">
                                    <div>
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-700 block">Attitude / Engagement</span>
                                        <p class="text-xs text-slate-800 font-medium line-clamp-2" title="<?= htmlspecialchars($previous_summary['attitude_goal']) ?>">
                                            « <?= htmlspecialchars($previous_summary['attitude_goal']) ?> »
                                        </p>
                                    </div>
                                    <?php if (!$is_locked): ?>
                                        <button 
                                            type="button" 
                                            onclick="insertPreviousGoal('goals_results[goal_3_attitude]', <?= htmlspecialchars(json_encode($previous_summary['attitude_goal']), ENT_QUOTES, 'UTF-8') ?>)"
                                            class="self-start text-[11px] font-bold text-indigo-700 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-2 py-0.5 rounded transition-colors"
                                        >
                                            📋 Reprendre en objectif 3
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200/80">
                    
                    <!-- Objectif 1 : Performance -->
                    <div>
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">Objectif de performance</span>
                        <input 
                            type="text" 
                            name="goals_results[goal_1_perf]" 
                            value="<?= htmlspecialchars($get_goal('goal_1_perf')) ?>" 
                            <?= $is_locked ? 'disabled' : '' ?>
                            placeholder="" 
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-sm bg-white"
                        >
                    </div>
                    <div>
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">Réalisé</span>
                        <input 
                            type="text" 
                            name="goals_results[achieved_1]" 
                            value="<?= htmlspecialchars($get_goal('achieved_1')) ?>" 
                            <?= $is_locked ? 'disabled' : '' ?>
                            placeholder="" 
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-sm bg-white font-medium text-slate-800"
                        >
                    </div>

                    <!-- Objectif 2 : Sélection / Compétitions -->
                    <div class="pt-2 sm:pt-0">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">Objectif de compétition / sélection</span>
                        <input 
                            type="text" 
                            name="goals_results[goal_2_selection]" 
                            value="<?= htmlspecialchars($get_goal('goal_2_selection')) ?>" 
                            <?= $is_locked ? 'disabled' : '' ?>
                            placeholder="" 
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-sm bg-white"
                        >
                    </div>
                    <div class="pt-2 sm:pt-0">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">Réalisé</span>
                        <input 
                            type="text" 
                            name="goals_results[achieved_2]" 
                            value="<?= htmlspecialchars($get_goal('achieved_2')) ?>" 
                            <?= $is_locked ? 'disabled' : '' ?>
                            placeholder="" 
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-sm bg-white font-medium text-slate-800"
                        >
                    </div>

                    <!-- Objectif 3 : Attitude -->
                    <div class="pt-2 sm:pt-0">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">Objectif d'attitude, d'engagement</span>
                        <input 
                            type="text" 
                            name="goals_results[goal_3_attitude]" 
                            value="<?= htmlspecialchars($get_goal('goal_3_attitude')) ?>" 
                            <?= $is_locked ? 'disabled' : '' ?>
                            placeholder="" 
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-sm bg-white"
                        >
                    </div>
                    <div class="pt-2 sm:pt-0">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">Réalisé</span>
                        <input 
                            type="text" 
                            name="goals_results[achieved_3]" 
                            value="<?= htmlspecialchars($get_goal('achieved_3')) ?>" 
                            <?= $is_locked ? 'disabled' : '' ?>
                            placeholder="" 
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-sm bg-white font-medium text-slate-800"
                        >
                    </div>

                </div>
            </div>

            <!-- Meilleure réussite -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="top_success_description" class="block text-sm font-semibold text-slate-800 mb-1">
                        Ta plus grande réussite ou fierté cette saison
                    </label>
                    <p class="text-xs text-slate-500 mb-1.5">Compétition, résultat, sélection, maîtrise, apprentissage, etc.</p>
                    <textarea 
                        name="top_success_description" 
                        id="top_success_description" 
                        rows="3" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="" 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm bg-slate-50 focus:bg-white"
                    ><?= htmlspecialchars($answers['top_success_description'] ?? '') ?></textarea>
                </div>
                <div>
                    <label for="top_success_action" class="block text-sm font-semibold text-slate-800 mb-1">
                        Qu'est-ce qui a changé positivement cette saison ?
                    </label>
                    <p class="text-xs text-slate-500 mb-1.5">Changements, adaptations, nouveaux entraînements, rigueur, etc.</p>
                    <textarea 
                        name="top_success_action" 
                        id="top_success_action" 
                        rows="3" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="" 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm bg-slate-50 focus:bg-white"
                    ><?= htmlspecialchars($answers['top_success_action'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Principal frein -->
            <div>
                <label for="main_limiting_barrier" class="block text-sm font-semibold text-slate-800 mb-1">
                    Ton principal frein cette saison
                </label>
                <p class="text-xs text-slate-500 mb-1.5">Ce qui t'a le plus empêché d'atteindre ton plein potentiel ou tes objectifs</p>
                <input 
                    type="text" 
                    name="main_limiting_barrier" 
                    id="main_limiting_barrier" 
                    value="<?= htmlspecialchars($answers['main_limiting_barrier'] ?? '') ?>" 
                    <?= $is_locked ? 'disabled' : '' ?>
                    placeholder="" 
                    class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm bg-slate-50 focus:bg-white font-medium"
                >
            </div>
        </section>

        <!-- VOLET 2 : Auto-évaluation des 5 Piliers (Notes de 1 à 5) -->
        <section id="volet-2" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8 space-y-6">
            <div class="border-b border-slate-100 pb-4">
                <h2 class="text-xl font-bold font-heading text-slate-900 flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-zinc-900 text-white flex items-center justify-center text-sm font-black">2</span>
                    Auto-évaluation
                </h2>
                <p class="text-xs text-slate-500 mt-1">
                    Évalue ton niveau d'exigence sur chaque pilier (1 = En surcharge permanente / Insuffisant, 5 = Parfaitement équilibré / Exemplaire).
                </p>
            </div>

            <?php
                $ratings_config = [
                    'rating_rigor' => [
                        'Rigueur à l\'entraînement', 
                        'Présence, ponctualité, écoute, concentration et intensité.'
                    ],
                    'rating_care_injuries' => [
                        'Soins et gestion des blessures', 
                        'Prévention, plans annexes, communication proactive des douleurs et consultations médicales.'
                    ],
                    'rating_lifestyle' => [
                        'Hygiène de vie et place du sport', 
                        'Place du sport dans ma vie, sommeil (quantité et régularité), alimentation/hydratation, sorties, etc.'
                    ],
                    'rating_mental_stability' => [
                        'Aspect mental', 
                        'Gestion du stress en compétition, capacité à rebondir après un échec et routine pré-compétition.'
                    ],
                    'rating_dual_career' => [
                        'Double projet (études / apprentissage / sport)', 
                        'Organisation du planning, anticipation des périodes d\'examens, communication avec l\'entraîneur et les parents et équilibre personnel.'
                    ]
                ];
            ?>

            <div class="space-y-4">
                <?php foreach ($ratings_config as $key => [$label, $desc]): ?>
                    <?php $current_val = (int)($answers[$key] ?? 3); ?>
                    <div class="p-4 rounded-xl border border-slate-200/80 bg-slate-50 hover:bg-slate-100/60 transition-colors">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-2">
                            <div>
                                <h3 class="text-sm font-bold text-slate-800"><?= $label ?></h3>
                                <p class="text-xs text-slate-500"><?= $desc ?></p>
                            </div>
                            <div class="flex items-center gap-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <label class="cursor-pointer">
                                        <input 
                                            type="radio" 
                                            name="<?= $key ?>" 
                                            value="<?= $i ?>" 
                                            <?= ($current_val === $i) ? 'checked' : '' ?> 
                                            <?= $is_locked ? 'disabled' : '' ?>
                                            class="sr-only peer"
                                        >
                                        <span class="w-9 h-9 rounded-xl flex items-center justify-center text-xs font-bold border transition-all peer-checked:bg-indigo-600 peer-checked:text-white peer-checked:border-indigo-600 peer-checked:shadow-md peer-checked:scale-110 bg-white border-slate-300 text-slate-700 hover:bg-slate-200">
                                            <?= $i ?>
                                        </span>
                                    </label>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div>
                <label for="lifestyle_notes" class="block text-sm font-semibold text-slate-800 mb-1">
                    Précisions sur ton auto-évalaution ou écosystème
                </label>
                <p class="text-xs text-slate-500 mb-1.5">Développement des points ci-dessus</p>
                <textarea 
                    name="lifestyle_notes" 
                    id="lifestyle_notes" 
                    rows="2" 
                    <?= $is_locked ? 'disabled' : '' ?>
                    placeholder="" 
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm bg-slate-50 focus:bg-white"
                ><?= htmlspecialchars($answers['lifestyle_notes'] ?? '') ?></textarea>
            </div>
        </section>

        <!-- VOLET 3 : Relation d'entraînement -->
        <section id="volet-3" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8 space-y-6">
            <div class="border-b border-slate-100 pb-4">
                <h2 class="text-xl font-bold font-heading text-slate-900 flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-zinc-900 text-white flex items-center justify-center text-sm font-black">3</span>
                    Relations
                </h2>
            </div>

            <div class="space-y-4">
                <div>
                    <label for="coach_positives_to_keep" class="block text-sm font-semibold text-slate-800 mb-1">
                        Ce qui a bien fonctionné avec ton entraîneur cette saison
                    </label>
                    <p class="text-xs text-slate-500 mb-1.5">Ce que tu as apprécié dans les séances, les retours, la relation humaine ou l'accompagnement.</p>
                    <textarea 
                        name="coach_positives_to_keep" 
                        id="coach_positives_to_keep" 
                        rows="2" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="" 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm bg-slate-50 focus:bg-white"
                    ><?= htmlspecialchars($answers['coach_positives_to_keep'] ?? '') ?></textarea>
                </div>

                <div>
                    <label for="coach_friction_points" class="block text-sm font-semibold text-slate-800 mb-1">
                        Points de friction ou incompréhensions vécus
                    </label>
                    <p class="text-xs text-slate-500 mb-1.5">Ce qui a pu te frustrer, manquer de clarté ou freiner ta progression dans la dynamique d'entraînement.</p>
                    <textarea 
                        name="coach_friction_points" 
                        id="coach_friction_points" 
                        rows="2" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="" 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm bg-slate-50 focus:bg-white"
                    ><?= htmlspecialchars($answers['coach_friction_points'] ?? '') ?></textarea>
                </div>

                <div>
                    <label for="coach_needs_next_season" class="block text-sm font-semibold text-slate-800 mb-1">
                        Besoins spécifiques de ta part pour la saison à venir
                    </label>
                    <p class="text-xs text-slate-500 mb-1.5">Fermeté, calme, retours vidéo, explications technique, soutien moral, etc.</p>
                    <textarea 
                        name="coach_needs_next_season" 
                        id="coach_needs_next_season" 
                        rows="2" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="" 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm bg-slate-50 focus:bg-white"
                    ><?= htmlspecialchars($answers['coach_needs_next_season'] ?? '') ?></textarea>
                </div>
            </div>
        </section>

        <!-- VOLET 4 : Saison à venir -->
        <section id="volet-4" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8 space-y-6">
            <div class="border-b border-slate-100 pb-4">
                <h2 class="text-xl font-bold font-heading text-slate-900 flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-zinc-900 text-white flex items-center justify-center text-sm font-black">4</span>
                    Saison à venir
                </h2>
            </div>

            <!-- Datalist suggestions de disciplines libres -->
            <datalist id="disciplines-suggestions">
                <?php foreach (CategoryHelper::get_disciplines() as $key => $label): ?>
                    <option value="<?= htmlspecialchars($label) ?>">
                <?php endforeach; ?>
            </datalist>

            <!-- Disciplines souhaitées (Saisie libre) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <label for="chosen_discipline_1" class="block text-sm font-semibold text-slate-800">
                            Discipline 1
                        </label>
                        <?php if (!empty($previous_summary['discipline_1_label']) && $previous_summary['discipline_1_label'] !== 'Aucune' && empty($answers['chosen_discipline_1']) && !$is_locked): ?>
                            <button 
                                type="button" 
                                onclick="insertPreviousGoal('chosen_discipline_1', <?= htmlspecialchars(json_encode($previous_summary['discipline_1_label']), ENT_QUOTES, 'UTF-8') ?>)"
                                class="text-[11px] font-semibold text-indigo-700 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-2 py-0.5 rounded transition-colors"
                                title="Reprendre la discipline principale de la saison passée"
                            >
                                📋 Reprendre (<?= htmlspecialchars($previous_summary['discipline_1_label']) ?>)
                            </button>
                        <?php endif; ?>
                    </div>
                    <input 
                        type="text" 
                        name="chosen_discipline_1" 
                        id="chosen_discipline_1" 
                        list="disciplines-suggestions"
                        value="<?= htmlspecialchars($answers['chosen_discipline_1'] ?? '') ?>" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="" 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm bg-slate-50 focus:bg-white font-medium"
                    >
                </div>
                <div>
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <label for="chosen_discipline_2" class="block text-sm font-semibold text-slate-800">
                            Discipline 2 (optionnelle)
                        </label>
                        <?php if (!empty($previous_summary['discipline_2_label']) && $previous_summary['discipline_2_label'] !== 'Aucune' && empty($answers['chosen_discipline_2']) && !$is_locked): ?>
                            <button 
                                type="button" 
                                onclick="insertPreviousGoal('chosen_discipline_2', <?= htmlspecialchars(json_encode($previous_summary['discipline_2_label']), ENT_QUOTES, 'UTF-8') ?>)"
                                class="text-[11px] font-semibold text-indigo-700 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-2 py-0.5 rounded transition-colors"
                                title="Reprendre la discipline 2 de la saison passée"
                            >
                                📋 Reprendre (<?= htmlspecialchars($previous_summary['discipline_2_label']) ?>)
                            </button>
                        <?php endif; ?>
                    </div>
                    <input 
                        type="text" 
                        name="chosen_discipline_2" 
                        id="chosen_discipline_2" 
                        list="disciplines-suggestions"
                        value="<?= htmlspecialchars($answers['chosen_discipline_2'] ?? '') ?>" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="" 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm bg-slate-50 focus:bg-white font-medium"
                    >
                </div>
            </div>

            <!-- Message d'incompatibilité des disciplines -->
            <div id="discipline-compatibility-warning" class="hidden p-3.5 rounded-xl border border-amber-300 bg-amber-50 text-amber-900 text-xs flex items-start gap-2.5 animate-fade-in shadow-sm">
                <span class="font-bold text-amber-700 text-base leading-none flex-shrink-0">⚠️</span>
                <div id="discipline-warning-text" class="font-medium leading-relaxed"></div>
            </div>

            <!-- Cadres sportifs -->
            <div>
                <label class="block text-sm font-semibold text-slate-800 mb-1">
                    Cadres
                </label>
                <p class="text-xs text-slate-500 mb-2">Cadres dont tu fais partie (inscrit) ou que tu souhaites intégrer la saison prochaine :</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                    <?php foreach (CategoryHelper::get_cadres() as $cadre_key => $cadre_label): ?>
                        <?php $c_checked = in_array($cadre_key, $selected_cadres, true); ?>
                        <label class="flex items-center gap-2.5 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer text-sm font-medium text-slate-700 has-[:checked]:bg-indigo-50/80 has-[:checked]:border-indigo-400 has-[:checked]:font-bold has-[:checked]:text-indigo-950 shadow-sm transition-all">
                            <input 
                                type="checkbox" 
                                name="cadres[]" 
                                value="<?= $cadre_key ?>" 
                                <?= $c_checked ? 'checked' : '' ?> 
                                <?= $is_locked ? 'disabled' : '' ?>
                                class="w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500"
                            >
                            <span><?= htmlspecialchars($cadre_label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="target_performance" class="block text-sm font-semibold text-slate-800 mb-1">
                        Objectif de performance chiffré
                    </label>
                    <p class="text-xs text-slate-500 mb-2">Ex: 10.80 aux 100m</p>
                    <input 
                        type="text" 
                        name="target_performance" 
                        id="target_performance" 
                        value="<?= htmlspecialchars($answers['target_performance'] ?? '') ?>" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="" 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm bg-slate-50 focus:bg-white font-medium"
                    >
                </div>
                <div>
                    <label for="target_competitions" class="block text-sm font-semibold text-slate-800 mb-1">
                        Objectif de compétition, de sélection
                    </label>
                    <p class="text-xs text-slate-500 mb-2">Ex: Champion suisse, cadres valaisans</p>
                    <input 
                        type="text" 
                        name="target_competitions" 
                        id="target_competitions" 
                        value="<?= htmlspecialchars($answers['target_competitions'] ?? '') ?>" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="" 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm bg-slate-50 focus:bg-white font-medium"
                    >
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="commitment_1" class="block text-sm font-semibold text-slate-800 mb-1">
                        Engagement 1
                    </label>
                    <p class="text-xs text-slate-500 mb-2">Pour parvenir à tes objectifs</p>
                    <input 
                        type="text" 
                        name="commitment_1" 
                        id="commitment_1" 
                        value="<?= htmlspecialchars($answers['commitment_1'] ?? '') ?>" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="" 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm bg-slate-50 focus:bg-white"
                    >
                </div>
                <div>
                    <label for="commitment_2" class="block text-sm font-semibold text-slate-800 mb-1">
                        Engagement 2
                    </label>
                    <p class="text-xs text-slate-500 mb-2">Pour parvenir à tes objectifs</p>
                    <input 
                        type="text" 
                        name="commitment_2" 
                        id="commitment_2" 
                        value="<?= htmlspecialchars($answers['commitment_2'] ?? '') ?>" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="" 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm bg-slate-50 focus:bg-white"
                    >
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <label for="study_work_situation" class="block text-sm font-semibold text-slate-800">
                            Situation scolaire ou professionnelle à la rentrée
                        </label>
                        <?php if (!empty($previous_summary['study_work']) && empty($answers['study_work_situation']) && !$is_locked): ?>
                            <button 
                                type="button" 
                                onclick="insertPreviousGoal('study_work_situation', <?= htmlspecialchars(json_encode($previous_summary['study_work']), ENT_QUOTES, 'UTF-8') ?>)"
                                class="text-[11px] font-semibold text-indigo-700 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-2 py-0.5 rounded transition-colors"
                                title="Reprendre la situation de la saison passée"
                            >
                                📋 Reprendre N-1
                            </button>
                        <?php endif; ?>
                    </div>
                    <input 
                        type="text" 
                        name="study_work_situation" 
                        id="study_work_situation" 
                        value="<?= htmlspecialchars($answers['study_work_situation'] ?? '') ?>" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="" 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm bg-slate-50 focus:bg-white"
                    >
                </div>
                <div>
                    <label for="target_sessions_count" class="block text-sm font-semibold text-slate-800 mb-1">
                        Nombre d'entraînements par semaine
                    </label>
                    <input 
                        type="number" 
                        name="target_sessions_count" 
                        id="target_sessions_count" 
                        min="2" 
                        max="12" 
                        placeholder=""
                        value="<?= htmlspecialchars((string)($answers['target_sessions_count'] ?? '')) ?>" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm bg-slate-50 focus:bg-white font-medium"
                    >
                </div>
            </div>

            <!-- Jours disponibles -->
            <div>
                <label class="block text-sm font-semibold text-slate-800 mb-2">
                    Disponibilités
                </label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                    <?php 
                        $days_u18 = [
                            'monday' => 'Lundi',
                            'tuesday' => 'Mardi',
                            'wednesday' => 'Mercredi',
                            'thursday' => 'Jeudi',
                            'friday' => 'Vendredi',
                            'saturday' => 'Samedi',
                            'sunday' => 'Dimanche'
                        ];
                        foreach ($days_u18 as $key => $label): 
                            $checked = in_array($key, $selected_days, true);
                    ?>
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer text-sm font-medium text-slate-700 has-[:checked]:bg-indigo-50/80 has-[:checked]:border-indigo-400 has-[:checked]:font-bold has-[:checked]:text-indigo-950 shadow-sm transition-all">
                            <input 
                                type="checkbox" 
                                name="available_days[]" 
                                value="<?= $key ?>" 
                                <?= $checked ? 'checked' : '' ?> 
                                <?= $is_locked ? 'disabled' : '' ?>
                                class="w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500"
                            >
                            <span><?= htmlspecialchars($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Actions de validation -->
        <?php if (!$is_locked): ?>
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4">
                <p class="text-xs text-slate-500">
                    💾 Sauvegarde automatique active. Tes saisies sont enregistrées en temps réel.
                </p>
                <button 
                    type="button" 
                    id="btn-final-submit"
                    class="w-full sm:w-auto px-8 py-4 bg-zinc-900 hover:bg-zinc-800 text-white font-bold rounded-xl shadow-md hover:shadow-lg transition-all text-sm flex items-center justify-center gap-2"
                >
                    <span>✓</span>
                    <span>Transmettre mon bilan à l'entraîneur</span>
                </button>
            </div>
        <?php elseif ($interview['status'] === 'submitted'): ?>
            <div class="bg-amber-50 border border-amber-200/80 rounded-2xl p-4 flex flex-col sm:flex-row items-center justify-between gap-3 shadow-sm mt-4">
                <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-xl bg-amber-100 text-amber-800 font-bold flex items-center justify-center text-sm">✓</span>
                    <div>
                        <h4 class="font-bold text-xs text-amber-950">Bilan transmis à ton entraîneur</h4>
                        <p class="text-[11px] text-amber-800">Tes réponses sont enregistrées. Tu peux les modifier à tout moment avant ton entretien.</p>
                    </div>
                </div>
                <form action="<?= url('/unlock') ?>" method="POST">
                    <button type="submit" class="px-4 py-2 bg-white hover:bg-amber-100 text-amber-900 border border-amber-300 font-bold rounded-xl text-xs shadow-sm transition-all flex items-center gap-1.5 whitespace-nowrap">
                        <span>✏️</span>
                        <span>Modifier mes réponses</span>
                    </button>
                </form>
            </div>
        <?php endif; ?>

    </form>

</div>

<script>
function insertPreviousGoal(fieldName, textValue) {
    if (!textValue) return;
    const input = document.querySelector(`[name="${fieldName}"]`);
    if (input) {
        input.value = textValue;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        input.focus();
        input.classList.add('ring-2', 'ring-indigo-500', 'bg-indigo-50/40');
        setTimeout(() => {
            input.classList.remove('ring-2', 'ring-indigo-500', 'bg-indigo-50/40');
        }, 1200);
        if (typeof showToast === 'function') {
            showToast('Objectif inséré avec succès', 'info');
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const formId = 'athlete-u18-form';
    const interviewId = <?= (int)$interview['id'] ?>;
    const athleteId = <?= (int)$athlete['id'] ?>;
    const isLocked = <?= $is_locked ? 'true' : 'false' ?>;

    if (!isLocked) {
        const autosave = new FormAutosave(formId, interviewId, athleteId);

        // Soumission finale
        const submitBtn = document.getElementById('btn-final-submit');
        if (submitBtn) {
            submitBtn.addEventListener('click', async () => {
                if (!confirm('Confirmer la transmission de ton bilan à ton entraîneur ?')) {
                    return;
                }

                submitBtn.disabled = true;
                submitBtn.textContent = 'Transmission en cours...';

                try {
                    const payload = {
                        interview_id: interviewId,
                        athlete_id: athleteId,
                        answers: autosave.getFormData()
                    };

                    const response = await fetch(`${window.APP_BASE_URL || ''}/api/submit`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    const result = await response.json();
                    if (result.success) {
                        showToast('Bilan transmis avec succès !', 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 800);
                    } else {
                        alert(result.error || 'Erreur lors de la transmission.');
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Transmettre mon bilan à l\'entraîneur';
                    }
                } catch (e) {
                    console.error(e);
                    alert('Erreur réseau. Veuillez réessayer.');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Transmettre mon bilan à l\'entraîneur';
                }
            });
        }
    }
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
