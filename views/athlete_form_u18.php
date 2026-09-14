<?php
$page_title = "Bilan individuel de saison — " . htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']);
$is_locked = in_array($interview['status'], ['submitted', 'completed'], true);
$needs_birth_date = empty($athlete['birth_date']) || $athlete['access_pin'] === '0000';
$goals = is_array($answers['goals_results'] ?? null) ? $answers['goals_results'] : [];
$get_goal = fn(string $k): string => (string)($goals[$k] ?? $answers["goals_results[{$k}]"] ?? $answers[$k] ?? '');
$selected_days = $answers['available_days'] ?? [];
if (!is_array($selected_days)) $selected_days = [$selected_days];
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
            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200 mb-2">
                <span>Athlète individuel &bull; <?= htmlspecialchars($category_label) ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold font-heading text-slate-900">
                <?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?>
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Bilan de performance, écosystème, relation d'entraînement et projection N+1
            </p>
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
            1. Bilan sportif et causal
        </a>
        <a href="#volet-2" class="flex-1 py-2.5 px-3 text-center rounded-xl text-slate-600 hover:bg-white/80 hover:text-slate-900 transition-colors">
            2. Écosystème (1 à 5)
        </a>
        <a href="#volet-3" class="flex-1 py-2.5 px-3 text-center rounded-xl text-slate-600 hover:bg-white/80 hover:text-slate-900 transition-colors">
            3. Relation d'entraînement
        </a>
        <a href="#volet-4" class="flex-1 py-2.5 px-3 text-center rounded-xl text-slate-600 hover:bg-white/80 hover:text-slate-900 transition-colors">
            4. Projection N+1
        </a>
    </div>

    <!-- Formulaire U18+ -->
    <form id="athlete-u18-form" class="space-y-8">

        <!-- VOLET 1 : Bilan sportif et analyse causale -->
        <section id="volet-1" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8 space-y-6">
            <div class="border-b border-slate-100 pb-4">
                <h2 class="text-xl font-bold font-heading text-slate-900 flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center text-sm font-black">1</span>
                    Volet 1 : Bilan sportif et analyse causale
                </h2>
                <p class="text-xs text-slate-500 mt-1">
                    Confrontation des objectifs fixés, réussites marquantes et facteurs de réussite ou d'échec.
                </p>
            </div>

            <!-- Tableau Objectifs vs Réalisations -->
            <div class="space-y-3">
                <label class="block text-sm font-semibold text-slate-800">
                    Objectifs de la saison écoulée vs Réalisations effectives
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200/80">
                    
                    <!-- Objectif 1 : Performance -->
                    <div>
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">Objectif 1 : Performance (chrono / mesure)</span>
                        <input 
                            type="text" 
                            name="goals_results[goal_1_perf]" 
                            value="<?= htmlspecialchars($get_goal('goal_1_perf')) ?>" 
                            <?= $is_locked ? 'disabled' : '' ?>
                            placeholder="Ex : Descendre sous les 11.20s au 100m" 
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-sm bg-white"
                        >
                    </div>
                    <div>
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">Résultat effectif atteint</span>
                        <input 
                            type="text" 
                            name="goals_results[achieved_1]" 
                            value="<?= htmlspecialchars($get_goal('achieved_1')) ?>" 
                            <?= $is_locked ? 'disabled' : '' ?>
                            placeholder="Ex : 11.14s aux Championnats romands" 
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-sm bg-white font-medium text-slate-800"
                        >
                    </div>

                    <!-- Objectif 2 : Sélection / Championnat -->
                    <div class="pt-2 sm:pt-0">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">Objectif 2 : Sélections / Podiums</span>
                        <input 
                            type="text" 
                            name="goals_results[goal_2_selection]" 
                            value="<?= htmlspecialchars($get_goal('goal_2_selection')) ?>" 
                            <?= $is_locked ? 'disabled' : '' ?>
                            placeholder="Ex : Finale aux Championnats suisses U20" 
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-sm bg-white"
                        >
                    </div>
                    <div class="pt-2 sm:pt-0">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">Résultat effectif atteint</span>
                        <input 
                            type="text" 
                            name="goals_results[achieved_2]" 
                            value="<?= htmlspecialchars($get_goal('achieved_2')) ?>" 
                            <?= $is_locked ? 'disabled' : '' ?>
                            placeholder="Ex : 5ème place en finale" 
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-sm bg-white font-medium text-slate-800"
                        >
                    </div>

                    <!-- Objectif 3 : Attitude / Progression -->
                    <div class="pt-2 sm:pt-0">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">Objectif 3 : Attitude / Rigueur</span>
                        <input 
                            type="text" 
                            name="goals_results[goal_3_attitude]" 
                            value="<?= htmlspecialchars($get_goal('goal_3_attitude')) ?>" 
                            <?= $is_locked ? 'disabled' : '' ?>
                            placeholder="Ex : Assiduité 90% et routine d'échauffement" 
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-sm bg-white"
                        >
                    </div>
                    <div class="pt-2 sm:pt-0">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block mb-1">Résultat effectif atteint</span>
                        <input 
                            type="text" 
                            name="goals_results[achieved_3]" 
                            value="<?= htmlspecialchars($get_goal('achieved_3')) ?>" 
                            <?= $is_locked ? 'disabled' : '' ?>
                            placeholder="Ex : Rigueur tenue toute la saison" 
                            class="w-full px-3.5 py-2.5 rounded-lg border border-slate-300 text-sm bg-white font-medium text-slate-800"
                        >
                    </div>

                </div>
            </div>

            <!-- Meilleure réussite -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="top_success_description" class="block text-sm font-semibold text-slate-800 mb-1">
                        Meilleure performance ou grand moment de satisfaction
                    </label>
                    <textarea 
                        name="top_success_description" 
                        id="top_success_description" 
                        rows="3" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="Décris ton plus beau moment sportif de la saison..." 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-rose-500 text-sm bg-slate-50 focus:bg-white"
                    ><?= htmlspecialchars($answers['top_success_description'] ?? '') ?></textarea>
                </div>
                <div>
                    <label for="top_success_action" class="block text-sm font-semibold text-slate-800 mb-1">
                        Action ou décision concrète de ta part ayant rendu cela possible
                    </label>
                    <textarea 
                        name="top_success_action" 
                        id="top_success_action" 
                        rows="3" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="Qu'as-tu fait concrètement pour y parvenir ?" 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-rose-500 text-sm bg-slate-50 focus:bg-white"
                    ><?= htmlspecialchars($answers['top_success_action'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Analyse causale -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="causes_controllable" class="block text-sm font-semibold text-slate-800 mb-1">
                        Facteurs sous ton contrôle face aux contre-performances
                    </label>
                    <p class="text-xs text-slate-500 mb-1.5">Choix de vie, rigueur, investissement, gestion du stress.</p>
                    <textarea 
                        name="causes_controllable" 
                        id="causes_controllable" 
                        rows="3" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="Ce qui dépendait directement de toi..." 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-rose-500 text-sm bg-slate-50 focus:bg-white"
                    ><?= htmlspecialchars($answers['causes_controllable'] ?? '') ?></textarea>
                </div>
                <div>
                    <label for="causes_uncontrollable" class="block text-sm font-semibold text-slate-800 mb-1">
                        Facteurs hors de ton contrôle
                    </label>
                    <p class="text-xs text-slate-500 mb-1.5">Météo, blessure accidentelle, décision d'officiel, calendrier.</p>
                    <textarea 
                        name="causes_uncontrollable" 
                        id="causes_uncontrollable" 
                        rows="3" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="Ce qui ne dépendait pas de toi..." 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-rose-500 text-sm bg-slate-50 focus:bg-white"
                    ><?= htmlspecialchars($answers['causes_uncontrollable'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Le seul frein limitant -->
            <div>
                <label for="main_limiting_barrier" class="block text-sm font-semibold text-slate-800 mb-1">
                    Le seul frein majeur qui a limité ta progression cette année
                </label>
                <input 
                    type="text" 
                    name="main_limiting_barrier" 
                    id="main_limiting_barrier" 
                    value="<?= htmlspecialchars($answers['main_limiting_barrier'] ?? '') ?>" 
                    <?= $is_locked ? 'disabled' : '' ?>
                    placeholder="Nomme avec franchise ton obstacle numéro 1..." 
                    class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-rose-500 text-sm bg-slate-50 focus:bg-white font-medium"
                >
            </div>
        </section>

        <!-- VOLET 2 : Écosystème de performance -->
        <section id="volet-2" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8 space-y-6">
            <div class="border-b border-slate-100 pb-4">
                <h2 class="text-xl font-bold font-heading text-slate-900 flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center text-sm font-black">2</span>
                    Volet 2 : Écosystème de performance (Auto-évaluation 1 à 5)
                </h2>
                <p class="text-xs text-slate-500 mt-1">
                    Évalue ton niveau d'exigence sur chaque pilier de la performance sportive (1 = Très insuffisant, 5 = Exemplaire).
                </p>
            </div>

            <?php
                $ratings_config = [
                    'rating_rigor' => ['Rigueur à l\'entraînement', 'Présence, écoute des consignes, concentration technique et engagement maximal.'],
                    'rating_care_injuries' => ['Gestion des blessures et soins', 'Prévention, communication immédiate avec le coach, rendez-vous kiné et respect des protocoles.'],
                    'rating_lifestyle' => ['Hygiène de vie d\'athlète', 'Qualité et volume de sommeil, alimentation adaptée, gestion des sorties et de la récupération.'],
                    'rating_mental_stability' => ['Stabilité mentale en compétition', 'Gestion des émotions, confiance, combativité sous pression et routine pré-compétition.'],
                    'rating_dual_career' => ['Double projet (études / travail et sport)', 'Organisation du planning, anticipation des examens et équilibre de charge mentale.']
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
                                        <span class="w-9 h-9 rounded-xl flex items-center justify-center text-xs font-bold border transition-all peer-checked:bg-rose-600 peer-checked:text-white peer-checked:border-rose-600 peer-checked:shadow-md peer-checked:scale-110 bg-white border-slate-300 text-slate-700 hover:bg-slate-200">
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
                    Commentaire libre sur ton investissement global
                </label>
                <textarea 
                    name="lifestyle_notes" 
                    id="lifestyle_notes" 
                    rows="2" 
                    <?= $is_locked ? 'disabled' : '' ?>
                    placeholder="Précisions sur ton hygiène de vie, tes contraintes scolaires ou personnelles..." 
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-rose-500 text-sm bg-slate-50 focus:bg-white"
                ><?= htmlspecialchars($answers['lifestyle_notes'] ?? '') ?></textarea>
            </div>
        </section>

        <!-- VOLET 3 : Relation d'entraînement -->
        <section id="volet-3" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8 space-y-6">
            <div class="border-b border-slate-100 pb-4">
                <h2 class="text-xl font-bold font-heading text-slate-900 flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center text-sm font-black">3</span>
                    Volet 3 : Relation d'entraînement
                </h2>
                <p class="text-xs text-slate-500 mt-1">
                    Communication transparente avec le staff pour optimiser l'accompagnement et la confiance mutuelle.
                </p>
            </div>

            <div class="space-y-4">
                <div>
                    <label for="coach_positives_to_keep" class="block text-sm font-semibold text-slate-800 mb-1">
                        Ce qui a très bien fonctionné dans l'encadrement et à préserver absolument
                    </label>
                    <textarea 
                        name="coach_positives_to_keep" 
                        id="coach_positives_to_keep" 
                        rows="2" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="Points forts des séances, ambiance, dynamique de groupe..." 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-rose-500 text-sm bg-slate-50 focus:bg-white"
                    ><?= htmlspecialchars($answers['coach_positives_to_keep'] ?? '') ?></textarea>
                </div>

                <div>
                    <label for="coach_friction_points" class="block text-sm font-semibold text-slate-800 mb-1">
                        Situations où le coaching n'a pas convenu ou t'a freiné
                    </label>
                    <textarea 
                        name="coach_friction_points" 
                        id="coach_friction_points" 
                        rows="2" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="Incompréhensions, retours négatifs, manque de clarté..." 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-rose-500 text-sm bg-slate-50 focus:bg-white"
                    ><?= htmlspecialchars($answers['coach_friction_points'] ?? '') ?></textarea>
                </div>

                <div>
                    <label for="coach_needs_next_season" class="block text-sm font-semibold text-slate-800 mb-1">
                        Besoins spécifiques de ta part pour la saison à venir
                    </label>
                    <p class="text-xs text-slate-500 mb-1.5">Fermeté, calme, retours vidéo, explications biomécaniques, soutien moral.</p>
                    <textarea 
                        name="coach_needs_next_season" 
                        id="coach_needs_next_season" 
                        rows="2" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="De quoi as-tu prioritairement besoin pour progresser ?" 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-rose-500 text-sm bg-slate-50 focus:bg-white"
                    ><?= htmlspecialchars($answers['coach_needs_next_season'] ?? '') ?></textarea>
                </div>
            </div>
        </section>

        <!-- VOLET 4 : Projection et engagement N+1 -->
        <section id="volet-4" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8 space-y-6">
            <div class="border-b border-slate-100 pb-4">
                <h2 class="text-xl font-bold font-heading text-slate-900 flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center text-sm font-black">4</span>
                    Volet 4 : Projection et engagement N+1
                </h2>
                <p class="text-xs text-slate-500 mt-1">
                    Ambitions chiffrées, volume d'entraînement souhaité et contrats d'attitude.
                </p>
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
                    <label for="chosen_discipline_1" class="block text-sm font-semibold text-slate-800 mb-1">
                        Discipline prioritaire souhaitée
                    </label>
                    <input 
                        type="text" 
                        name="chosen_discipline_1" 
                        id="chosen_discipline_1" 
                        list="disciplines-suggestions"
                        value="<?= htmlspecialchars($answers['chosen_discipline_1'] ?? '') ?>" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="Ex : 100m / 200m, Haies, Perche, Longueur..." 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-rose-500 text-sm bg-slate-50 focus:bg-white font-medium"
                    >
                </div>
                <div>
                    <label for="chosen_discipline_2" class="block text-sm font-semibold text-slate-800 mb-1">
                        Discipline secondaire (optionnelle)
                    </label>
                    <input 
                        type="text" 
                        name="chosen_discipline_2" 
                        id="chosen_discipline_2" 
                        list="disciplines-suggestions"
                        value="<?= htmlspecialchars($answers['chosen_discipline_2'] ?? '') ?>" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="Ex : Relais 4x100m, Poids, Longueur..." 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-rose-500 text-sm bg-slate-50 focus:bg-white font-medium"
                    >
                </div>
            </div>

            <!-- Message d'incompatibilité des disciplines -->
            <div id="discipline-compatibility-warning" class="hidden p-3.5 rounded-xl border border-amber-300 bg-amber-50 text-amber-900 text-xs flex items-start gap-2.5 animate-fade-in shadow-sm">
                <span class="font-bold text-amber-700 text-base leading-none flex-shrink-0">⚠️</span>
                <div id="discipline-warning-text" class="font-medium leading-relaxed"></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="target_performance" class="block text-sm font-semibold text-slate-800 mb-1">
                        Performance chiffrée visée
                    </label>
                    <input 
                        type="text" 
                        name="target_performance" 
                        id="target_performance" 
                        value="<?= htmlspecialchars($answers['target_performance'] ?? '') ?>" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="Ex : 10.95s au 100m, 6.80m en longueur..." 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-rose-500 text-sm bg-slate-50 focus:bg-white font-medium"
                    >
                </div>
                <div>
                    <label for="target_competitions" class="block text-sm font-semibold text-slate-800 mb-1">
                        Championnats cibles ou sélections visées
                    </label>
                    <input 
                        type="text" 
                        name="target_competitions" 
                        id="target_competitions" 
                        value="<?= htmlspecialchars($answers['target_competitions'] ?? '') ?>" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="Ex : Médaille CS, qualification CS Élite..." 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-rose-500 text-sm bg-slate-50 focus:bg-white font-medium"
                    >
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="commitment_1" class="block text-sm font-semibold text-slate-800 mb-1">
                        Engagement d'attitude 1 (Non négociable)
                    </label>
                    <input 
                        type="text" 
                        name="commitment_1" 
                        id="commitment_1" 
                        value="<?= htmlspecialchars($answers['commitment_1'] ?? '') ?>" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="Ex : Rigueur sans faille sur la récupération" 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-rose-500 text-sm bg-slate-50 focus:bg-white"
                    >
                </div>
                <div>
                    <label for="commitment_2" class="block text-sm font-semibold text-slate-800 mb-1">
                        Engagement d'attitude 2 (Non négociable)
                    </label>
                    <input 
                        type="text" 
                        name="commitment_2" 
                        id="commitment_2" 
                        value="<?= htmlspecialchars($answers['commitment_2'] ?? '') ?>" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="Ex : Communication proactive des signaux de fatigue" 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-rose-500 text-sm bg-slate-50 focus:bg-white"
                    >
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="study_work_situation" class="block text-sm font-semibold text-slate-800 mb-1">
                        Situation scolaire ou professionnelle à la rentrée
                    </label>
                    <input 
                        type="text" 
                        name="study_work_situation" 
                        id="study_work_situation" 
                        value="<?= htmlspecialchars($answers['study_work_situation'] ?? '') ?>" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        placeholder="Ex : Collège de la Planta 3e, Apprentissage..." 
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-rose-500 text-sm bg-slate-50 focus:bg-white"
                    >
                </div>
                <div>
                    <label for="target_sessions_count" class="block text-sm font-semibold text-slate-800 mb-1">
                        Volume hebdomadaire souhaité (séances / semaine)
                    </label>
                    <input 
                        type="number" 
                        name="target_sessions_count" 
                        id="target_sessions_count" 
                        min="2" 
                        max="8" 
                        value="<?= htmlspecialchars((string)($answers['target_sessions_count'] ?? '4')) ?>" 
                        <?= $is_locked ? 'disabled' : '' ?>
                        class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-rose-500 text-sm bg-slate-50 focus:bg-white font-medium"
                    >
                </div>
            </div>

            <!-- Jours disponibles -->
            <div>
                <label class="block text-sm font-semibold text-slate-800 mb-2">
                    Disponibilités d'entraînement hebdomadaires :
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
                        <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer text-sm font-medium text-slate-700 has-[:checked]:bg-rose-50/80 has-[:checked]:border-rose-400 has-[:checked]:font-bold has-[:checked]:text-rose-950 shadow-sm transition-all">
                            <input 
                                type="checkbox" 
                                name="available_days[]" 
                                value="<?= $key ?>" 
                                <?= $checked ? 'checked' : '' ?> 
                                <?= $is_locked ? 'disabled' : '' ?>
                                class="w-4 h-4 text-rose-600 rounded focus:ring-rose-500"
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
                    class="w-full sm:w-auto px-8 py-4 bg-gradient-to-r from-brand-600 to-rose-600 hover:from-brand-700 hover:to-rose-700 text-white font-bold rounded-xl shadow-lg shadow-rose-600/20 hover:shadow-xl transition-all text-sm flex items-center justify-center gap-2"
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
