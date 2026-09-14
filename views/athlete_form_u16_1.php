<?php
$page_title = "Bilan U16 (1ère année) — " . htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']);
$is_locked = in_array($interview['status'], ['submitted', 'completed'], true);
$needs_birth_date = empty($athlete['birth_date']) || $athlete['access_pin'] === '0000';
ob_start();
?>

<div class="max-w-3xl mx-auto my-4 space-y-6">

    <!-- Modal d'obligation de renseignement de date de naissance si PIN 0000 -->
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

    <!-- En-tête de la fiche athlète avec état de synchronisation -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200 mb-2">
                <span>Groupe U16 — 1ère année</span>
            </div>
            <h1 class="text-2xl font-bold font-heading text-slate-900">
                <?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?>
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">
                Bilan de fin de saison et souhaits pour l'année prochaine
            </p>
        </div>

        <div class="flex items-center gap-3">
            <?php if ($is_locked): ?>
                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
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
                    <strong>Ton bilan a été transmis à tes entraîneurs.</strong>
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

    <!-- Formulaire U16 1ère année -->
    <form id="athlete-u16-form" class="space-y-6">

        <!-- 1. Bilan de la saison écoulée -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-5">
            <h2 class="text-lg font-bold font-heading text-slate-900 flex items-center gap-2">
                <span class="w-6 h-6 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center text-xs font-black">1</span>
                Bilan de la saison écoulée
            </h2>

            <!-- Fierté -->
            <div>
                <label for="pride_highlight" class="block text-sm font-semibold text-slate-800 mb-1">
                    Ma plus grande fierté cette année
                </label>
                <p class="text-xs text-slate-500 mb-2">Un concours réussi, un chrono, un geste technique maîtrisé ou une victoire personnelle.</p>
                <textarea 
                    name="pride_highlight" 
                    id="pride_highlight" 
                    rows="3" 
                    <?= $is_locked ? 'disabled' : '' ?>
                    placeholder="Raconte ton meilleur moment de la saison..." 
                    class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-rose-500 focus:border-rose-500 text-sm bg-slate-50 focus:bg-white transition-all"
                ><?= htmlspecialchars($answers['pride_highlight'] ?? '') ?></textarea>
            </div>

            <!-- Principal frein -->
            <div>
                <label class="block text-sm font-semibold text-slate-800 mb-2">
                    Mon principal frein cette année
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <?php foreach (CategoryHelper::get_obstacles() as $key => $label): ?>
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer text-sm font-medium text-slate-700 transition-colors has-[:checked]:bg-rose-50/80 has-[:checked]:border-rose-400 has-[:checked]:text-rose-950 has-[:checked]:font-semibold shadow-sm">
                            <input 
                                type="radio" 
                                name="main_obstacle" 
                                value="<?= $key ?>" 
                                <?= ($answers['main_obstacle'] ?? '') === $key ? 'checked' : '' ?> 
                                <?= $is_locked ? 'disabled' : '' ?>
                                class="w-4 h-4 text-rose-600 focus:ring-rose-500"
                            >
                            <span><?= htmlspecialchars($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Précision obstacle -->
            <div>
                <label for="obstacle_notes" class="block text-sm font-semibold text-slate-800 mb-1">
                    Précision sur mon frein ou détail
                </label>
                <input 
                    type="text" 
                    name="obstacle_notes" 
                    id="obstacle_notes" 
                    value="<?= htmlspecialchars($answers['obstacle_notes'] ?? '') ?>" 
                    <?= $is_locked ? 'disabled' : '' ?>
                    placeholder="Explique brièvement..." 
                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-rose-500 focus:border-rose-500 text-sm bg-slate-50 focus:bg-white transition-all"
                >
            </div>
        </div>

        <!-- 2. Auto-évaluation de l'attitude -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-5">
            <h2 class="text-lg font-bold font-heading text-slate-900 flex items-center gap-2">
                <span class="w-6 h-6 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center text-xs font-black">2</span>
                Mon auto-évaluation
            </h2>

            <!-- Sérieux et énergie -->
            <div>
                <label class="block text-sm font-semibold text-slate-800 mb-2">
                    Mon sérieux et mon énergie à l'entraînement
                </label>
                <div class="grid grid-cols-3 gap-3">
                    <?php 
                        $evals = [
                            'improve' => ['À améliorer', '🌱'],
                            'fair' => ['Correct', '👍'],
                            'exemplary' => ['Exemplaire', '⭐']
                        ];
                        foreach ($evals as $val => [$lbl, $emoji]):
                            $checked = ($answers['self_eval_attitude'] ?? '') === $val;
                    ?>
                        <label class="flex flex-col items-center justify-center p-3.5 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer text-center transition-all has-[:checked]:ring-2 has-[:checked]:ring-rose-500 has-[:checked]:border-rose-500 has-[:checked]:bg-rose-50/70 has-[:checked]:text-rose-950 has-[:checked]:font-bold text-slate-700 shadow-sm">
                            <span class="text-2xl mb-1"><?= $emoji ?></span>
                            <span class="text-xs mb-1.5"><?= $lbl ?></span>
                            <input 
                                type="radio" 
                                name="self_eval_attitude" 
                                value="<?= $val ?>" 
                                <?= $checked ? 'checked' : '' ?> 
                                <?= $is_locked ? 'disabled' : '' ?>
                                class="w-4 h-4 text-rose-600 focus:ring-rose-500"
                            >
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Respect camarades et matériel -->
            <div>
                <label class="block text-sm font-semibold text-slate-800 mb-2">
                    Mon respect des camarades et du matériel
                </label>
                <div class="grid grid-cols-3 gap-3">
                    <?php 
                        foreach ($evals as $val => [$lbl, $emoji]):
                            $checked = ($answers['self_eval_respect'] ?? '') === $val;
                    ?>
                        <label class="flex flex-col items-center justify-center p-3.5 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer text-center transition-all has-[:checked]:ring-2 has-[:checked]:ring-rose-500 has-[:checked]:border-rose-500 has-[:checked]:bg-rose-50/70 has-[:checked]:text-rose-950 has-[:checked]:font-bold text-slate-700 shadow-sm">
                            <span class="text-2xl mb-1"><?= $emoji ?></span>
                            <span class="text-xs mb-1.5"><?= $lbl ?></span>
                            <input 
                                type="radio" 
                                name="self_eval_respect" 
                                value="<?= $val ?>" 
                                <?= $checked ? 'checked' : '' ?> 
                                <?= $is_locked ? 'disabled' : '' ?>
                                class="w-4 h-4 text-rose-600 focus:ring-rose-500"
                            >
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 3. Option compétition du vendredi (Spécifique U16 1ère année) -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-5">
            <h2 class="text-lg font-bold font-heading text-slate-900 flex items-center gap-2">
                <span class="w-6 h-6 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center text-xs font-black">3</span>
                Option compétition du vendredi
            </h2>
            <p class="text-xs text-slate-500">
                Réservée aux athlètes motivés participant régulièrement aux compétitions hors canton.
            </p>

            <div>
                <label class="flex items-center gap-3 p-4 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 cursor-pointer transition-colors has-[:checked]:bg-rose-50 has-[:checked]:border-rose-400 has-[:checked]:text-rose-950 shadow-sm">
                    <input 
                        type="checkbox" 
                        name="friday_option_requested" 
                        value="1" 
                        id="friday_option_requested"
                        <?= !empty($answers['friday_option_requested']) ? 'checked' : '' ?> 
                        <?= $is_locked ? 'disabled' : '' ?>
                        class="w-5 h-5 text-rose-600 rounded focus:ring-rose-500"
                    >
                    <span class="text-sm font-semibold text-slate-800">
                        Je souhaite intégrer l'option compétition du vendredi
                    </span>
                </label>
            </div>

            <div id="friday-discipline-container" class="space-y-2 <?= empty($answers['friday_option_requested']) ? 'hidden' : '' ?>">
                <label class="block text-sm font-semibold text-slate-800">
                    Discipline demandée pour le vendredi :
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <?php foreach (CategoryHelper::get_friday_options() as $key => $label): ?>
                        <label class="flex items-center gap-2.5 p-3 rounded-xl border border-slate-200 hover:bg-slate-50 cursor-pointer text-sm font-medium text-slate-700 has-[:checked]:bg-rose-50/80 has-[:checked]:border-rose-400 has-[:checked]:font-bold has-[:checked]:text-rose-950 shadow-sm">
                            <input 
                                type="radio" 
                                name="friday_discipline" 
                                value="<?= $key ?>" 
                                <?= ($answers['friday_discipline'] ?? '') === $key ? 'checked' : '' ?> 
                                <?= $is_locked ? 'disabled' : '' ?>
                                class="w-4 h-4 text-rose-600 focus:ring-rose-500"
                            >
                            <span><?= htmlspecialchars($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 4. Contrat d'attitude pour la saison prochaine -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
            <h2 class="text-lg font-bold font-heading text-slate-900 flex items-center gap-2">
                <span class="w-6 h-6 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center text-xs font-black">4</span>
                Mon engagement prioritaire
            </h2>

            <div>
                <label for="attitude_contract" class="block text-sm font-semibold text-slate-800 mb-1">
                    Pour la saison prochaine, je m'engage sur ce comportement prioritaire à chaque séance :
                </label>
                <input 
                    type="text" 
                    name="attitude_contract" 
                    id="attitude_contract" 
                    value="<?= htmlspecialchars($answers['attitude_contract'] ?? '') ?>" 
                    <?= $is_locked ? 'disabled' : '' ?>
                    placeholder="Ex : Écoute immédiate dès le coup de sifflet, échauffement complet sans bavardage..." 
                    class="w-full px-4 py-3 rounded-xl border border-slate-300 focus:ring-2 focus:ring-rose-500 focus:border-rose-500 text-sm bg-slate-50 focus:bg-white transition-all font-medium"
                >
            </div>
        </div>

        <!-- Actions de validation -->
        <?php if (!$is_locked): ?>
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4">
                <p class="text-xs text-slate-500">
                    💾 Tes réponses sont sauvegardées automatiquement en temps réel.
                </p>
                <button 
                    type="button" 
                    id="btn-final-submit"
                    class="w-full sm:w-auto px-6 py-3.5 bg-gradient-to-r from-brand-600 to-rose-600 hover:from-brand-700 hover:to-rose-700 text-white font-bold rounded-xl shadow-lg shadow-rose-600/20 hover:shadow-xl transition-all text-sm flex items-center justify-center gap-2"
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
    const formId = 'athlete-u16-form';
    const interviewId = <?= (int)$interview['id'] ?>;
    const athleteId = <?= (int)$athlete['id'] ?>;
    const isLocked = <?= $is_locked ? 'true' : 'false' ?>;

    if (!isLocked) {
        const autosave = new FormAutosave(formId, interviewId, athleteId);

        // Affichage dynamique de la discipline du vendredi
        const fridayCheckbox = document.getElementById('friday_option_requested');
        const fridayContainer = document.getElementById('friday-discipline-container');
        if (fridayCheckbox && fridayContainer) {
            fridayCheckbox.addEventListener('change', () => {
                if (fridayCheckbox.checked) {
                    fridayContainer.classList.remove('hidden');
                } else {
                    fridayContainer.classList.add('hidden');
                }
            });
        }

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
