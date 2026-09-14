<?php
$page_title = "Connexion Athlète — Bilan de Saison";
ob_start();
?>

<div class="max-w-md mx-auto my-4 sm:my-10">
    <!-- Bento Card de Connexion Athlète en 2 étapes -->
    <div class="bg-white rounded-2xl border border-zinc-200/80 shadow-sm overflow-hidden p-5 sm:p-7 space-y-5">
        
        <!-- En-tête avec progression d'étape -->
        <div class="text-center pb-3 border-b border-zinc-100 space-y-1.5">
            <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                <span>Saison <?= htmlspecialchars($active_season['name'] ?? '2026-2027') ?></span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold font-heading text-zinc-900 tracking-tight">
                Bilan individuel de saison
            </h1>
            
            <!-- Indicateur d'étape -->
            <div class="flex items-center justify-center gap-2 pt-1">
                <div id="step-dot-1" class="flex items-center gap-1 text-[11px] font-bold text-zinc-900">
                    <span class="w-4 h-4 rounded-full bg-zinc-900 text-white flex items-center justify-center text-[10px]">1</span>
                    <span>Athlète</span>
                </div>
                <span class="text-zinc-300 text-xs">→</span>
                <div id="step-dot-2" class="flex items-center gap-1 text-[11px] font-medium text-zinc-400">
                    <span class="w-4 h-4 rounded-full bg-zinc-200 text-zinc-600 flex items-center justify-center text-[10px]">2</span>
                    <span>Code PIN</span>
                </div>
            </div>
        </div>

        <!-- Formulaire de connexion -->
        <form action="<?= url('/login') ?>" method="POST" id="login-form">
            
            <!-- =========================================================================
                 ÉTAPE 1 : SÉLECTION DE L'ATHLÈTE
                 ========================================================================= -->
            <div id="step-1-container" class="space-y-4">
                <div>
                    <label for="athlete_search" class="block text-xs font-semibold text-zinc-700 uppercase tracking-wider mb-1.5">
                        1. Recherche ton prénom et nom
                    </label>
                    <div class="relative">
                        <input 
                            type="text" 
                            id="athlete_search" 
                            placeholder="Taper ton nom pour filtrer..." 
                            autocomplete="off"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-zinc-300 focus:ring-2 focus:ring-zinc-900 focus:border-zinc-900 text-sm bg-zinc-50/50 focus:bg-white transition-all"
                        >
                        <span class="absolute right-3.5 top-2.5 text-zinc-400 pointer-events-none text-xs">🔍</span>
                    </div>
                </div>

                <!-- Liste cliquable des athlètes -->
                <div class="space-y-1.5 max-h-60 overflow-y-auto pr-1 border border-zinc-200/80 rounded-xl p-1.5 bg-zinc-50/30" id="athlete-list-container">
                    <?php foreach ($athletes as $a): ?>
                        <?php 
                            $year_str = ($a['birth_year'] > 0) ? (string)$a['birth_year'] : 'Année non renseignée';
                            $display_name = htmlspecialchars($a['first_name'] . ' ' . $a['last_name']);
                            $is_default_pin = ($a['access_pin'] === '0000');
                            $cat = htmlspecialchars($a['category'] ?? '');
                        ?>
                        <div 
                            class="athlete-item flex items-center justify-between p-2.5 rounded-lg border border-transparent hover:border-zinc-200 hover:bg-white cursor-pointer transition-all text-xs"
                            data-id="<?= (int)$a['id'] ?>"
                            data-name="<?= $display_name ?>"
                            data-year="<?= $year_str ?>"
                            data-category="<?= $cat ?>"
                            data-default-pin="<?= $is_default_pin ? '1' : '0' ?>"
                        >
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-md bg-zinc-100 text-zinc-700 flex items-center justify-center font-bold text-[10px]">
                                    <?= mb_substr($a['first_name'], 0, 1) ?>
                                </span>
                                <div>
                                    <span class="font-bold text-zinc-900 block leading-tight"><?= $display_name ?></span>
                                    <span class="text-[10px] text-zinc-400"><?= $cat ?> &bull; <?= $year_str ?></span>
                                </div>
                            </div>
                            <span class="text-zinc-300 text-xs athlete-chevron">→</span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Sélecteur caché lié au formulaire -->
                <select name="athlete_id" id="athlete_id" class="hidden" required>
                    <option value="">Sélectionner un athlète</option>
                    <?php foreach ($athletes as $a): ?>
                        <option value="<?= (int)$a['id'] ?>" data-default-pin="<?= ($a['access_pin'] === '0000') ? '1' : '0' ?>">
                            <?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Bouton étape 1 -->
                <button 
                    type="button" 
                    id="btn-to-step-2" 
                    disabled
                    class="w-full py-2.5 px-4 bg-zinc-900 hover:bg-zinc-800 disabled:bg-zinc-200 disabled:text-zinc-400 disabled:cursor-not-allowed text-white font-semibold rounded-xl text-xs transition-all shadow-sm flex items-center justify-center gap-2"
                >
                    <span>Continuer vers la saisie du code PIN</span>
                    <span>→</span>
                </button>
            </div>

            <!-- =========================================================================
                 ÉTAPE 2 : EXPLICATION ET SAISIE DU CODE PIN
                 ========================================================================= -->
            <div id="step-2-container" class="space-y-4 hidden">
                
                <!-- Carte récapitulative de l'athlète sélectionné -->
                <div class="p-3 bg-zinc-50 rounded-xl border border-zinc-200 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-zinc-900 text-white flex items-center justify-center font-bold text-xs" id="selected-avatar">
                            A
                        </div>
                        <div>
                            <span class="text-[10px] text-zinc-400 block uppercase tracking-wider font-semibold">Athlète identifié</span>
                            <span class="font-bold text-zinc-900 text-sm leading-tight block" id="selected-athlete-name">
                                Nom de l'athlète
                            </span>
                        </div>
                    </div>
                    <button 
                        type="button" 
                        id="btn-back-to-step-1" 
                        class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 hover:underline px-2 py-1 rounded"
                    >
                        Changer
                    </button>
                </div>

                <!-- Guide explicatif très clair sur le code PIN -->
                <div id="pin-guide-standard" class="p-3.5 bg-indigo-50/60 rounded-xl border border-indigo-200/80 space-y-2 text-xs">
                    <div class="flex items-center gap-2 font-bold text-indigo-950">
                        <span class="w-4 h-4 rounded-full bg-indigo-600 text-white flex items-center justify-center text-[10px] font-bold">?</span>
                        <span>Comment composer ton code PIN ?</span>
                    </div>
                    <p class="text-zinc-700 leading-relaxed text-[11px]">
                        Ton code PIN personnel correspond à ton <strong>jour et mois de naissance</strong> (4 chiffres) :
                    </p>
                    <div class="grid grid-cols-2 gap-2 text-[11px] pt-0.5">
                        <div class="bg-white p-2 rounded-lg border border-indigo-200/60">
                            <span class="text-zinc-500 block text-[10px]">Exemple 1 :</span>
                            <span class="text-zinc-800 font-medium">Né le <strong>14 avril</strong></span>
                            <span class="block text-indigo-700 font-bold font-mono text-xs">→ Code 1404</span>
                        </div>
                        <div class="bg-white p-2 rounded-lg border border-indigo-200/60">
                            <span class="text-zinc-500 block text-[10px]">Exemple 2 :</span>
                            <span class="text-zinc-800 font-medium">Né le <strong>8 septembre</strong></span>
                            <span class="block text-indigo-700 font-bold font-mono text-xs">→ Code 0809</span>
                        </div>
                    </div>
                </div>

                <!-- Guide première connexion si PIN 0000 -->
                <div id="pin-guide-default" class="p-3.5 bg-amber-50 rounded-xl border border-amber-200 space-y-1.5 text-xs hidden">
                    <div class="flex items-center gap-2 font-bold text-amber-950">
                        <span class="w-4 h-4 rounded-full bg-amber-500 text-white flex items-center justify-center text-[10px] font-bold">!</span>
                        <span>Première connexion (date non renseignée)</span>
                    </div>
                    <p class="text-amber-900 leading-relaxed text-[11px]">
                        Ta date de naissance n'a pas encore été enregistrée. Utilise le code temporaire <strong>0000</strong> pour entrer. Tu pourras ensuite renseigner ta date de naissance.
                    </p>
                </div>

                <!-- Saisie du PIN -->
                <div>
                    <label for="pin" class="block text-xs font-semibold text-zinc-700 uppercase tracking-wider mb-1.5 text-center">
                        Saisis ton code PIN (4 chiffres)
                    </label>
                    <input 
                        type="password" 
                        name="pin" 
                        id="pin" 
                        maxlength="4" 
                        inputmode="numeric" 
                        pattern="[0-9]*" 
                        required 
                        placeholder="••••" 
                        class="w-full px-4 py-3 rounded-xl border border-zinc-300 focus:ring-2 focus:ring-zinc-900 focus:border-zinc-900 text-center text-2xl font-mono tracking-widest bg-zinc-50/50 focus:bg-white transition-all shadow-inner"
                    >
                </div>

                <!-- Bouton de connexion finale -->
                <button 
                    type="submit" 
                    class="w-full py-3 px-4 bg-zinc-900 hover:bg-zinc-800 text-white font-bold rounded-xl text-xs transition-all shadow-sm flex items-center justify-center gap-2"
                >
                    <span>Accéder à mon bilan de saison</span>
                    <span>→</span>
                </button>
            </div>

        </form>

        <!-- Assistance si difficulté de connexion -->
        <div class="pt-3 border-t border-zinc-100 text-center">
            <p class="text-[11px] text-zinc-500 mb-0.5">Tu ne trouves pas ton nom ou ton code PIN ne fonctionne pas ?</p>
            <p class="text-xs font-semibold text-zinc-700">Contacte ton entraîneur référent au club</p>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('athlete_search');
    const listContainer = document.getElementById('athlete-list-container');
    const athleteItems = document.querySelectorAll('.athlete-item');
    const select = document.getElementById('athlete_id');
    const btnToStep2 = document.getElementById('btn-to-step-2');
    const btnBackToStep1 = document.getElementById('btn-back-to-step-1');
    const step1Container = document.getElementById('step-1-container');
    const step2Container = document.getElementById('step-2-container');
    const stepDot1 = document.getElementById('step-dot-1');
    const stepDot2 = document.getElementById('step-dot-2');
    const selectedNameEl = document.getElementById('selected-athlete-name');
    const selectedAvatarEl = document.getElementById('selected-avatar');
    const pinGuideStandard = document.getElementById('pin-guide-standard');
    const pinGuideDefault = document.getElementById('pin-guide-default');
    const pinInput = document.getElementById('pin');

    let currentSelectedAthlete = null;

    // Filtrage dynamique des athlètes
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            let count = 0;
            athleteItems.forEach(item => {
                const name = item.dataset.name.toLowerCase();
                const year = item.dataset.year.toLowerCase();
                if (name.includes(query) || year.includes(query)) {
                    item.classList.remove('hidden');
                    count++;
                } else {
                    item.classList.add('hidden');
                }
            });
        });
    }

    // Sélection d'un athlète au clic
    athleteItems.forEach(item => {
        item.addEventListener('click', () => {
            athleteItems.forEach(i => {
                i.classList.remove('bg-zinc-900', 'text-white', 'border-zinc-900');
                i.classList.add('hover:bg-white');
                const nameSpan = i.querySelector('.font-bold');
                if (nameSpan) nameSpan.className = 'font-bold text-zinc-900 block leading-tight';
            });

            item.classList.add('bg-zinc-900', 'text-white', 'border-zinc-900');
            item.classList.remove('hover:bg-white');
            const nameSpan = item.querySelector('.font-bold');
            if (nameSpan) nameSpan.className = 'font-bold text-white block leading-tight';

            currentSelectedAthlete = {
                id: item.dataset.id,
                name: item.dataset.name,
                category: item.dataset.category,
                year: item.dataset.year,
                isDefaultPin: item.dataset.defaultPin === '1'
            };

            select.value = currentSelectedAthlete.id;
            btnToStep2.disabled = false;

            // Passer automatiquement à l'étape 2
            goToStep2();
        });
    });

    function goToStep2() {
        if (!currentSelectedAthlete) return;

        selectedNameEl.textContent = `${currentSelectedAthlete.name} (${currentSelectedAthlete.category})`;
        selectedAvatarEl.textContent = currentSelectedAthlete.name.charAt(0).toUpperCase();

        if (currentSelectedAthlete.isDefaultPin) {
            pinGuideStandard.classList.add('hidden');
            pinGuideDefault.classList.remove('hidden');
        } else {
            pinGuideStandard.classList.remove('hidden');
            pinGuideDefault.classList.add('hidden');
        }

        step1Container.classList.add('hidden');
        step2Container.classList.remove('hidden');

        // Mettre à jour l'indicateur d'étapes
        stepDot1.className = 'flex items-center gap-1 text-[11px] font-medium text-zinc-400';
        stepDot1.querySelector('span:first-child').className = 'w-4 h-4 rounded-full bg-zinc-200 text-zinc-600 flex items-center justify-center text-[10px]';

        stepDot2.className = 'flex items-center gap-1 text-[11px] font-bold text-zinc-900';
        stepDot2.querySelector('span:first-child').className = 'w-4 h-4 rounded-full bg-zinc-900 text-white flex items-center justify-center text-[10px]';

        pinInput.value = '';
        pinInput.focus();
    }

    function goToStep1() {
        step2Container.classList.add('hidden');
        step1Container.classList.remove('hidden');

        stepDot2.className = 'flex items-center gap-1 text-[11px] font-medium text-zinc-400';
        stepDot2.querySelector('span:first-child').className = 'w-4 h-4 rounded-full bg-zinc-200 text-zinc-600 flex items-center justify-center text-[10px]';

        stepDot1.className = 'flex items-center gap-1 text-[11px] font-bold text-zinc-900';
        stepDot1.querySelector('span:first-child').className = 'w-4 h-4 rounded-full bg-zinc-900 text-white flex items-center justify-center text-[10px]';

        searchInput?.focus();
    }

    btnToStep2?.addEventListener('click', goToStep2);
    btnBackToStep1?.addEventListener('click', goToStep1);
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
