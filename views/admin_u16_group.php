<?php
$page_title = "Entretien groupé U16 express (" . count($athletes) . " athlètes)";
$col_count = count($athletes);
$grid_class = match ($col_count) {
    1 => 'grid-cols-1 max-w-2xl mx-auto',
    2 => 'grid-cols-1 md:grid-cols-2',
    3 => 'grid-cols-1 md:grid-cols-3',
    default => 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4'
};
ob_start();
?>

<!-- Datalist universelle pour suggestions d'épreuves d'athlétisme -->
<datalist id="disciplines-suggestions">
    <?php foreach (CategoryHelper::get_disciplines() as $k => $lbl): ?>
        <option value="<?= htmlspecialchars($lbl) ?>">
    <?php endforeach; ?>
</datalist>

<div class="space-y-4 max-w-7xl mx-auto">

    <!-- En-tête de navigation -->
    <div class="bg-white px-4 py-3 rounded-2xl border border-zinc-200/80 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="<?= url('/admin') ?>" class="w-8 h-8 rounded-xl bg-zinc-100 hover:bg-zinc-200 text-zinc-600 flex items-center justify-center text-xs font-bold transition-colors" title="Retour au tableau de bord">
                ←
            </a>
            <div>
                <div class="flex items-center gap-2 text-[11px] font-medium text-zinc-500">
                    <span>Espace entraîneur</span>
                    <span class="text-zinc-300">/</span>
                    <span class="font-semibold text-zinc-700">Entretien groupé U16 express</span>
                </div>
                <h1 class="text-lg font-bold font-heading text-zinc-900 leading-tight">
                    Arbitrage groupé (<?= count($athletes) ?> athlètes)
                </h1>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="<?= url('/admin') ?>" class="px-3 py-1.5 bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-xs font-semibold rounded-xl border border-zinc-200 transition-colors">
                Retour au tableau de bord
            </a>
        </div>
    </div>

    <!-- Grille des colonnes côte à côte -->
    <div class="grid <?= $grid_class ?> gap-4 items-start">
        <?php foreach ($athletes as $a): ?>
            <?php
                $ath_answers = $a['answers_arr'];
                $trainer_ans = $a['trainer_arr'];
                $decisions = $a['decisions_arr'];
                $is_u16_1 = ($a['age'] <= 14);
                $is_validated = (int)($a['is_validated'] ?? 0) === 1;
                $status = $a['interview_status'] ?? 'waiting';

                $attitude = $trainer_ans['attitude_status'] ?? 'ok';
                $verdict = $decisions['coach_verdict'] ?? 'approved';
                $approved_days = $decisions['approved_training_days'] ?? ($ath_answers['available_days'] ?? ['monday', 'wednesday', 'friday']);
                if (!is_array($approved_days)) $approved_days = [$approved_days];
            ?>
            <div class="bg-white rounded-2xl border border-zinc-200/80 shadow-sm overflow-hidden flex flex-col text-xs">
                
                <!-- En-tête de colonne athlète -->
                <div class="px-3.5 py-2.5 bg-zinc-50/80 border-b border-zinc-200/80 flex items-center justify-between">
                    <div>
                        <h2 class="font-bold font-heading text-sm text-zinc-900 leading-tight">
                            <?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?>
                        </h2>
                        <span class="text-[11px] text-zinc-400">
                            <?= htmlspecialchars($a['category_label']) ?> (<?= $a['birth_year'] ?>)
                        </span>
                    </div>
                    <?php if ($is_validated): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Validé
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-50 text-rose-800 border border-rose-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> <?= ucfirst($status) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Formulaire englobant la fiche athlète et les arbitrages du coach -->
                <form action="<?= url('/admin/save-trainer') ?>" method="POST" class="flex-1 flex flex-col justify-between">
                    <input type="hidden" name="interview_id" value="<?= (int)$a['interview_id'] ?>">
                    <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/admin') ?>">

                    <!-- Réponses de l'athlète (éditables) -->
                    <div class="p-3 bg-zinc-50/40 border-b border-zinc-200/80 space-y-2.5">
                        <div class="flex items-center justify-between font-semibold text-zinc-500 uppercase tracking-wider text-[10px] pb-1 border-b border-zinc-200/60">
                            <span>Fiche athlète (éditable)</span>
                        </div>

                        <!-- Fierté -->
                        <div>
                            <label class="font-semibold text-zinc-700 block mb-0.5 text-[11px]">Satisfaction majeure de la saison :</label>
                            <textarea 
                                name="athlete_answers[pride_highlight]" 
                                rows="2" 
                                placeholder="Moment marquant..." 
                                class="w-full px-2.5 py-1 bg-white border border-zinc-200 rounded-lg text-xs focus:ring-1 focus:ring-zinc-900"
                            ><?= htmlspecialchars($ath_answers['pride_highlight'] ?? '') ?></textarea>
                        </div>

                        <!-- Frein -->
                        <div>
                            <label class="font-semibold text-zinc-700 block mb-0.5 text-[11px]">Principal frein constaté :</label>
                            <input 
                                type="text" 
                                name="athlete_answers[obstacle_notes]" 
                                value="<?= htmlspecialchars($ath_answers['obstacle_notes'] ?? ($ath_answers['main_obstacle'] ?? '')) ?>" 
                                placeholder="Frein constaté..." 
                                class="w-full px-2.5 py-1 bg-white border border-zinc-200 rounded-lg text-xs focus:ring-1 focus:ring-zinc-900"
                            >
                        </div>

                        <!-- Souhaits U16 1ère année ou 2ème année -->
                        <?php if ($is_u16_1): ?>
                            <div>
                                <label class="font-semibold text-zinc-700 block mb-0.5 text-[11px]">Option vendredi souhaitée :</label>
                                <input 
                                    type="text" 
                                    name="athlete_answers[friday_discipline]" 
                                    value="<?= htmlspecialchars($ath_answers['friday_discipline'] ?? '') ?>" 
                                    placeholder="Ex : Hauteur, Demi-fond, Sprint..." 
                                    class="w-full px-2.5 py-1 bg-white border border-zinc-200 rounded-lg text-xs"
                                >
                            </div>
                        <?php else: ?>
                            <div class="space-y-1">
                                <label class="font-semibold text-zinc-700 block text-[11px]">Disciplines souhaitées :</label>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <input 
                                        type="text" 
                                        name="athlete_answers[chosen_discipline_1]" 
                                        list="disciplines-suggestions"
                                        value="<?= htmlspecialchars($ath_answers['chosen_discipline_1'] ?? '') ?>" 
                                        placeholder="1. Prioritaire" 
                                        class="w-full px-2 py-1 bg-white border border-zinc-200 rounded-lg text-xs font-medium"
                                    >
                                    <input 
                                        type="text" 
                                        name="athlete_answers[chosen_discipline_2]" 
                                        list="disciplines-suggestions"
                                        value="<?= htmlspecialchars($ath_answers['chosen_discipline_2'] ?? '') ?>" 
                                        placeholder="2. Secondaire" 
                                        class="w-full px-2 py-1 bg-white border border-zinc-200 rounded-lg text-xs font-medium"
                                    >
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Contrat d'attitude -->
                        <div>
                            <label class="font-semibold text-zinc-700 block mb-0.5 text-[11px]">Engagement d'attitude :</label>
                            <input 
                                type="text" 
                                name="athlete_answers[attitude_contract]" 
                                value="<?= htmlspecialchars($ath_answers['attitude_contract'] ?? '') ?>" 
                                placeholder="Engagement clé..." 
                                class="w-full px-2.5 py-1 bg-white border border-zinc-200 rounded-lg text-xs font-medium text-zinc-900"
                            >
                        </div>
                    </div>

                    <!-- Arbitrages entraîneur -->
                    <div class="p-3 space-y-2.5">
                        <div class="font-semibold text-zinc-500 uppercase tracking-wider text-[10px] pb-1 border-b border-zinc-200/60">
                            Arbitrage entraîneur
                        </div>

                        <!-- Attitude terrain -->
                        <div>
                            <label class="block font-semibold text-zinc-700 mb-0.5 text-[11px]">Attitude terrain :</label>
                            <div class="grid grid-cols-2 gap-1.5">
                                <label class="flex items-center gap-1.5 p-1.5 rounded-lg border cursor-pointer transition-all has-[:checked]:bg-emerald-50 has-[:checked]:border-emerald-300 has-[:checked]:font-bold has-[:checked]:text-emerald-900 border-zinc-200 bg-white hover:bg-zinc-50">
                                    <input type="radio" name="trainer_answers[attitude_status]" value="ok" <?= $attitude === 'ok' ? 'checked' : '' ?> class="text-emerald-600 focus:ring-emerald-500">
                                    <span>Positif / Validé</span>
                                </label>
                                <label class="flex items-center gap-1.5 p-1.5 rounded-lg border cursor-pointer transition-all has-[:checked]:bg-rose-50 has-[:checked]:border-rose-300 has-[:checked]:font-bold has-[:checked]:text-rose-900 border-zinc-200 bg-white hover:bg-zinc-50">
                                    <input type="radio" name="trainer_answers[attitude_status]" value="fragile" <?= $attitude === 'fragile' ? 'checked' : '' ?> class="text-rose-600 focus:ring-rose-500">
                                    <span>À recadrer</span>
                                </label>
                            </div>
                        </div>

                        <!-- Décisions U16 1ère année ou 2ème année -->
                        <?php if ($is_u16_1): ?>
                            <div class="bg-zinc-50 p-2 rounded-xl border border-zinc-200 space-y-1.5">
                                <label class="flex items-center gap-2 cursor-pointer font-semibold text-zinc-800 text-[11px]">
                                    <input 
                                        type="checkbox" 
                                        name="decisions[friday_option_granted]" 
                                        value="1" 
                                        <?= !empty($decisions['friday_option_granted']) ? 'checked' : '' ?>
                                        class="w-3.5 h-3.5 text-zinc-900 rounded"
                                    >
                                    <span>Option vendredi accordée</span>
                                </label>
                                <div>
                                    <label class="block text-[10px] text-zinc-600 mb-0.5">Discipline validée vendredi :</label>
                                    <input 
                                        type="text" 
                                        name="decisions[friday_discipline_approved]" 
                                        value="<?= htmlspecialchars($decisions['friday_discipline_approved'] ?? ($ath_answers['friday_discipline'] ?? '')) ?>" 
                                        placeholder="Ex : Hauteur, Demi-fond, Sprint" 
                                        class="w-full px-2 py-1 border border-zinc-300 rounded-lg text-xs bg-white"
                                    >
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="bg-zinc-50 p-2 rounded-xl border border-zinc-200 space-y-1">
                                <label class="block font-semibold text-zinc-800 text-[11px]">Disciplines validées :</label>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <input 
                                        type="text" 
                                        list="disciplines-suggestions"
                                        name="decisions[primary_discipline]" 
                                        value="<?= htmlspecialchars($decisions['primary_discipline'] ?? ($ath_answers['chosen_discipline_1'] ?? 'Sprint')) ?>" 
                                        placeholder="1. Prioritaire" 
                                        class="w-full px-2 py-1 border border-zinc-300 rounded-lg text-xs bg-white font-bold text-zinc-900"
                                    >
                                    <input 
                                        type="text" 
                                        list="disciplines-suggestions"
                                        name="decisions[secondary_discipline]" 
                                        value="<?= htmlspecialchars($decisions['secondary_discipline'] ?? ($ath_answers['chosen_discipline_2'] ?? '')) ?>" 
                                        placeholder="2. Secondaire" 
                                        class="w-full px-2 py-1 border border-zinc-300 rounded-lg text-xs bg-white font-medium"
                                    >
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Jours retenus -->
                        <div>
                            <label class="block font-semibold text-zinc-700 mb-1 text-[11px]">Jours d'entraînement retenus :</label>
                            <div class="grid grid-cols-6 gap-1">
                                <?php 
                                    $days_opts = ['monday' => 'Lu', 'tuesday' => 'Ma', 'wednesday' => 'Me', 'thursday' => 'Je', 'friday' => 'Ve', 'saturday_morning' => 'Sa'];
                                    foreach ($days_opts as $d_k => $d_l):
                                        $d_checked = in_array($d_k, $approved_days, true);
                                ?>
                                    <label class="flex items-center justify-center p-1.5 rounded-lg border text-center cursor-pointer text-xs transition-all has-[:checked]:bg-zinc-900 has-[:checked]:border-zinc-900 has-[:checked]:text-white has-[:checked]:font-bold border-zinc-200 hover:bg-zinc-100 text-zinc-700 bg-white select-none">
                                        <input type="checkbox" name="decisions[approved_training_days][]" value="<?= $d_k ?>" <?= $d_checked ? 'checked' : '' ?> class="sr-only">
                                        <span><?= $d_l ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Remarque coach -->
                        <div>
                            <label class="block font-semibold text-zinc-700 mb-0.5 text-[11px]">Remarque entraîneur :</label>
                            <input 
                                type="text" 
                                name="trainer_notes" 
                                value="<?= htmlspecialchars($a['trainer_notes'] ?? '') ?>" 
                                placeholder="Consignes particulières..." 
                                class="w-full px-2 py-1 border border-zinc-300 rounded-lg text-xs bg-white"
                            >
                        </div>
                    </div>

                    <!-- Actions pour cette colonne -->
                    <div class="p-3 bg-zinc-50 border-t border-zinc-200/80 flex items-center justify-between gap-2">
                        <button 
                            type="submit" 
                            name="action" 
                            value="save" 
                            class="flex-1 py-1.5 px-2.5 bg-zinc-100 hover:bg-zinc-200 text-zinc-800 font-bold rounded-lg border border-zinc-200 transition-colors text-center"
                        >
                            Enregistrer
                        </button>
                        <button 
                            type="submit" 
                            name="action" 
                            value="validate" 
                            class="flex-1 py-1.5 px-2.5 bg-zinc-900 hover:bg-zinc-800 text-white font-bold rounded-lg shadow-sm transition-all text-center"
                            onclick="return confirm('Valider officiellement cet arbitrage ?');"
                        >
                            Valider
                        </button>
                    </div>

                </form>

            </div>
        <?php endforeach; ?>
    </div>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
