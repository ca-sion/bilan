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
                $selected_cadres = $decisions['cadres'] ?? ($ath_answers['cadres'] ?? []);
                if (!is_array($selected_cadres)) $selected_cadres = [$selected_cadres];
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
                    <div class="flex items-center gap-1.5">
                        <?php if (!empty($a['history'])): ?>
                            <button 
                                type="button" 
                                onclick="openModal('modal-history-<?= (int)$a['id'] ?>')" 
                                class="px-2 py-0.5 bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-[10px] font-bold rounded-lg border border-zinc-200 transition-colors"
                                title="Consulter l'historique <?= htmlspecialchars($a['history']['season_name'] ?? 'N-1') ?>"
                            >
                                📜 N-1
                            </button>
                        <?php endif; ?>
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
                            <label class="font-semibold text-zinc-700 block mb-0.5 text-[11px]">Ma plus grande fierté cette année :</label>
                            <textarea 
                                name="athlete_answers[pride_highlight]" 
                                rows="2" 
                                placeholder="" 
                                class="w-full px-2.5 py-1 bg-white border border-zinc-200 rounded-lg text-xs focus:ring-1 focus:ring-zinc-900"
                            ><?= htmlspecialchars($ath_answers['pride_highlight'] ?? '') ?></textarea>
                        </div>

                        <!-- Frein -->
                        <div>
                            <label class="font-semibold text-zinc-700 block mb-0.5 text-[11px]">Principal frein :</label>
                            <input 
                                type="text" 
                                name="athlete_answers[obstacle_notes]" 
                                value="<?= htmlspecialchars($ath_answers['obstacle_notes'] ?? ($ath_answers['main_obstacle'] ?? '')) ?>" 
                                placeholder="" 
                                class="w-full px-2.5 py-1 bg-white border border-zinc-200 rounded-lg text-xs focus:ring-1 focus:ring-zinc-900"
                            >
                        </div>

                        <!-- Souhaits U16 1ère année ou 2ème année -->
                        <?php if ($is_u16_1): ?>
                            <div>
                                <label class="font-semibold text-zinc-700 block mb-0.5 text-[11px]">Option vendredi :</label>
                                <input 
                                    type="text" 
                                    name="athlete_answers[friday_discipline]" 
                                    value="<?= htmlspecialchars($ath_answers['friday_discipline'] ?? '') ?>" 
                                    placeholder="" 
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
                                        placeholder="" 
                                        class="w-full px-2 py-1 bg-white border border-zinc-200 rounded-lg text-xs font-medium"
                                    >
                                    <input 
                                        type="text" 
                                        name="athlete_answers[chosen_discipline_2]" 
                                        list="disciplines-suggestions"
                                        value="<?= htmlspecialchars($ath_answers['chosen_discipline_2'] ?? '') ?>" 
                                        placeholder="" 
                                        class="w-full px-2 py-1 bg-white border border-zinc-200 rounded-lg text-xs font-medium"
                                    >
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Contrat d'attitude -->
                        <div>
                            <label class="font-semibold text-zinc-700 block mb-0.5 text-[11px]">Mon engagement :</label>
                            <input 
                                type="text" 
                                name="athlete_answers[attitude_contract]" 
                                value="<?= htmlspecialchars($ath_answers['attitude_contract'] ?? '') ?>" 
                                placeholder="" 
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
                                        placeholder="" 
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
                                        value="<?= htmlspecialchars($decisions['primary_discipline'] ?? ($ath_answers['chosen_discipline_1'] ?? '')) ?>" 
                                        placeholder="" 
                                        class="w-full px-2 py-1 border border-zinc-300 rounded-lg text-xs bg-white font-bold text-zinc-900"
                                    >
                                    <input 
                                        type="text" 
                                        list="disciplines-suggestions"
                                        name="decisions[secondary_discipline]" 
                                        value="<?= htmlspecialchars($decisions['secondary_discipline'] ?? ($ath_answers['chosen_discipline_2'] ?? '')) ?>" 
                                        placeholder="" 
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

                        <!-- Cadres retenus -->
                        <div>
                            <label class="block font-semibold text-zinc-700 mb-1 text-[11px]">Cadres validés :</label>
                            <div class="grid grid-cols-2 gap-1">
                                <?php foreach (CategoryHelper::get_cadres() as $ck => $cl): ?>
                                    <?php $c_checked = in_array($ck, $selected_cadres, true); ?>
                                    <label class="flex items-center gap-1 p-1 rounded-lg border text-center cursor-pointer text-[10px] transition-all has-[:checked]:bg-zinc-900 has-[:checked]:border-zinc-900 has-[:checked]:text-white has-[:checked]:font-bold border-zinc-200 hover:bg-zinc-100 text-zinc-700 bg-white select-none">
                                        <input type="checkbox" name="decisions[cadres][]" value="<?= $ck ?>" <?= $c_checked ? 'checked' : '' ?> class="sr-only">
                                        <span><?= htmlspecialchars($cl) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Remarque coach -->
                        <div>
                            <label class="block font-semibold text-zinc-700 mb-0.5 text-[11px]">Remarques et note :</label>
                            <input 
                                type="text" 
                                name="trainer_notes" 
                                value="<?= htmlspecialchars($a['trainer_notes'] ?? '') ?>" 
                                placeholder="" 
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

<!-- Modales d'historique N-1 pour les athlètes du groupe -->
<?php foreach ($athletes as $a): ?>
    <?php if (!empty($a['history'])): ?>
        <?php
            $history = $a['history'];
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
        <div id="modal-history-<?= (int)$a['id'] ?>" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4">
            <div class="modal-content-card rounded-2xl shadow-2xl border border-zinc-200 max-w-lg w-full overflow-hidden max-h-[85vh] flex flex-col relative z-10 bg-white">
                <div class="p-4 border-b border-zinc-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-bold font-heading text-sm text-zinc-900">Historique saison <?= htmlspecialchars($history['season_name'] ?? 'N-1') ?></h3>
                        <p class="text-xs text-zinc-400">Fiche archivée de <?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></p>
                    </div>
                    <button onclick="closeModal('modal-history-<?= (int)$a['id'] ?>')" class="text-zinc-400 hover:text-zinc-700 text-sm">✕</button>
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
                        <div class="p-3 bg-zinc-50 rounded-xl border border-zinc-200 space-y-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-500 block">3. Contrat moral acté l'an passé</span>
                            <?php if ($h_rule1): ?>
                                <p><strong>1.</strong> <?= htmlspecialchars($h_rule1) ?></p>
                            <?php endif; ?>
                            <?php if ($h_rule2): ?>
                                <p><strong>2.</strong> <?= htmlspecialchars($h_rule2) ?></p>
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
<?php endforeach; ?>

<script>
function openModal(id) {
    document.getElementById(id)?.classList.remove('hidden');
}

function closeModal(id) {
    document.getElementById(id)?.classList.add('hidden');
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
