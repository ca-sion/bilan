<?php
$page_title = "Tableau de bord des entretiens";
$search = $_GET['q'] ?? '';
$status_filter = $_GET['status'] ?? '';
ob_start();
?>

<div class="space-y-6">

    <!-- BENTO 1 : En-tête avec métriques globales claires -->
    <div class="bg-white p-6 rounded-2xl border border-zinc-200/80 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <form method="GET" action="<?= url('/admin') ?>" class="inline-flex items-center gap-1.5">
                    <label for="header_season_select" class="text-xs text-zinc-500 font-medium">Saison :</label>
                    <select 
                        id="header_season_select"
                        name="season_id" 
                        onchange="this.form.submit()" 
                        class="px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200/80 cursor-pointer focus:outline-none focus:ring-1 focus:ring-rose-500"
                    >
                        <?php foreach ($all_seasons as $s): ?>
                            <option value="<?= (int)$s['id'] ?>" <?= ((int)$s['id'] === (int)$season['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['name']) ?> <?= ((int)$s['is_active'] === 1) ? '(active)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <button 
                    type="button" 
                    onclick="openModal('modal-manage-seasons')"
                    class="text-xs text-zinc-500 hover:text-zinc-800 underline decoration-zinc-300 transition-colors"
                    title="Gérer les saisons"
                >
                    Gérer
                </button>
            </div>
            <h1 class="text-2xl font-bold font-heading text-zinc-900 tracking-tight">
                Tableau de bord des bilans
            </h1>
            <p class="text-xs text-zinc-500 mt-0.5">
                Pilotage des entretiens de saison, arbitrages techniques et synthèses officielles.
            </p>
        </div>

        <!-- Actions rapides de gestion -->
        <div class="flex flex-wrap items-center gap-2">
            <button 
                type="button" 
                onclick="openModal('modal-add-athlete')" 
                class="px-3.5 py-2 bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-semibold rounded-xl transition-all shadow-sm flex items-center gap-1.5"
            >
                <span>+</span>
                <span>Ajouter un athlète</span>
            </button>
            <button 
                type="button" 
                onclick="openModal('modal-import-csv')" 
                class="px-3.5 py-2 bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-xs font-semibold rounded-xl border border-zinc-200 transition-colors flex items-center gap-1.5"
            >
                <span>📥</span>
                <span>Importer CSV</span>
            </button>
            <a 
                href="<?= url('/admin/export-grid?season_id=' . (int)$season['id']) ?>" 
                class="px-3.5 py-2 bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-xs font-semibold rounded-xl border border-zinc-200 transition-colors flex items-center gap-1.5"
                title="Exporter la grille de saison au format CSV conforme"
            >
                <span>📊</span>
                <span>Export grille</span>
            </a>
            <button 
                type="button" 
                onclick="openModal('modal-backup-restore')" 
                class="px-3.5 py-2 bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-xs font-semibold rounded-xl border border-zinc-200 transition-colors flex items-center gap-1.5"
                title="Sauvegarder ou restaurer la base de données"
            >
                <span>💾</span>
                <span>Sauvegarde</span>
            </button>
            <button 
                type="button" 
                onclick="openModal('modal-settings')" 
                class="px-3.5 py-2 bg-zinc-100 hover:bg-zinc-200 text-zinc-700 text-xs font-semibold rounded-xl border border-zinc-200 transition-colors flex items-center gap-1.5"
                title="Modifier les paramètres du club et le mot de passe"
            >
                <span>⚙️</span>
                <span>Paramètres</span>
            </button>
        </div>
    </div>

    <!-- BENTO 2 : Grille de 4 Métriques Clés -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- Total Athlètes -->
        <div class="bg-white p-5 rounded-2xl border border-zinc-200/80 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wider block">Effectif total</span>
                <span class="text-2xl font-bold font-heading text-zinc-900 mt-1 block">
                    <?= (int)$stats['total_athletes'] ?>
                </span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-zinc-100 text-zinc-700 flex items-center justify-center font-bold text-sm">
                👥
            </div>
        </div>

        <!-- Validés & Clôturés -->
        <div class="bg-white p-5 rounded-2xl border border-zinc-200/80 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-emerald-700 uppercase tracking-wider block">Validés et clôturés</span>
                <span class="text-2xl font-bold font-heading text-emerald-950 mt-1 block">
                    <?= (int)$stats['count_completed'] ?>
                </span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold text-sm border border-emerald-200/60">
                ✓
            </div>
        </div>

        <!-- Soumis (À arbitrer) -->
        <div class="bg-white p-5 rounded-2xl border border-zinc-200/80 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-rose-700 uppercase tracking-wider block">Soumis à traiter</span>
                <span class="text-2xl font-bold font-heading text-rose-950 mt-1 block">
                    <?= (int)$stats['count_submitted'] ?>
                </span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-700 flex items-center justify-center font-bold text-sm border border-rose-200/60">
                ⚡
            </div>
        </div>

        <!-- En attente -->
        <div class="bg-white p-5 rounded-2xl border border-zinc-200/80 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wider block">En attente</span>
                <span class="text-2xl font-bold font-heading text-zinc-700 mt-1 block">
                    <?= (int)$stats['count_waiting'] + (int)$stats['count_draft'] ?>
                </span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-zinc-100 text-zinc-500 flex items-center justify-center font-bold text-sm">
                ⏳
            </div>
        </div>

    </div>

    <!-- BARRE FLOTTANTE U16 GROUPÉ -->
    <div id="u16-group-bar" class="hidden bg-zinc-900 text-white p-4 rounded-2xl shadow-xl border border-zinc-800 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <span class="w-7 h-7 rounded-lg bg-rose-600 text-white font-bold flex items-center justify-center text-xs" id="selected-count">0</span>
            <div>
                <strong class="text-xs font-semibold uppercase tracking-wider">Athlètes U16 sélectionnés</strong>
                <p class="text-[11px] text-zinc-400">Lancez l'entretien groupé express pour comparer leurs fiches côte-à-côte.</p>
            </div>
        </div>
        <button 
            id="btn-launch-u16-group" 
            class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-xl transition-colors shadow"
        >
            Lancer l'entretien groupé →
        </button>
    </div>

    <!-- BENTO 3 : Barre de Recherche & Filtres -->
    <div class="bg-white p-4 rounded-2xl border border-zinc-200/80 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        
        <!-- Recherche instantanée en temps réel -->
        <div class="flex-1 flex items-center gap-2">
            <div class="relative flex-1">
                <input 
                    type="text" 
                    id="table-search-input"
                    value="<?= htmlspecialchars($search) ?>" 
                    placeholder="Rechercher par prénom, nom, catégorie ou contact..." 
                    class="w-full px-3.5 py-2 rounded-xl border border-zinc-200 focus:ring-2 focus:ring-zinc-900 text-xs bg-zinc-50/50 focus:bg-white transition-all"
                >
                <span class="absolute right-3 top-2 text-zinc-400 text-xs pointer-events-none">🔍</span>
            </div>
        </div>

        <!-- Filtres statut sous forme de pilules -->
        <div class="flex flex-wrap items-center gap-1.5 text-xs">
            <?php
                $statuses = [
                    '' => 'Tous',
                    'submitted' => 'Soumis',
                    'completed' => 'Validés',
                    'draft' => 'Brouillons',
                    'waiting' => 'En attente'
                ];
                foreach ($statuses as $st_key => $st_lbl):
                    $is_active = ($status_filter === $st_key);
            ?>
                <a 
                    href="<?= url('/admin?' . http_build_query(array_merge($_GET, ['status' => $st_key]))) ?>" 
                    class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors <?= $is_active ? 'bg-zinc-900 text-white font-semibold shadow-sm' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 hover:text-zinc-900' ?>"
                >
                    <?= $st_lbl ?>
                </a>
            <?php endforeach; ?>
        </div>

    </div>

    <!-- BENTO 4 : Tableau épuré des athlètes -->
    <div class="bg-white rounded-2xl border border-zinc-200/80 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-zinc-700" id="athletes-table">
                <thead class="bg-zinc-50/80 border-b border-zinc-200/80 text-zinc-500 uppercase font-semibold text-[11px] tracking-wider">
                    <tr>
                        <th class="p-4 text-center w-10">
                            <span class="sr-only">Sélection</span>
                        </th>
                        <th class="p-4">Athlète</th>
                        <th class="p-4">Catégorie</th>
                        <th class="p-4">Statut fiche</th>
                        <th class="p-4">Code PIN</th>
                        <th class="p-4">Lien direct</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    <?php if (empty($athletes)): ?>
                        <tr id="empty-state-row">
                            <td colspan="7" class="p-8 text-center text-zinc-400">
                                Aucun athlète trouvé pour ces critères de recherche.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($athletes as $a): ?>
                            <?php
                                $birth_year = (int)$a['birth_year'];
                                $cat_label = CategoryHelper::get_category_label($birth_year, $a['category']);
                                $form_type = CategoryHelper::get_form_type($birth_year, $a['category']);
                                $is_u16 = in_array($form_type, ['u16_1', 'u16_2'], true);

                                $status = $a['interview_status'] ?? 'waiting';
                                $is_val = (int)($a['is_validated'] ?? 0) === 1;

                                $status_badge = match ($status) {
                                    'completed' => '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200/80"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Validé</span>',
                                    'submitted' => '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-800 border border-rose-200/80"><span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Soumis</span>',
                                    'draft' => '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200/80"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Brouillon</span>',
                                    default => '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-zinc-100 text-zinc-600 border border-zinc-200"><span class="w-1.5 h-1.5 rounded-full bg-zinc-400"></span> En attente</span>'
                                };

                                $direct_url = url('/?token=' . urlencode($a['access_token']));
                                $direct_full_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $direct_url;
                                
                                $meta_arr = safe_json_decode($a['meta'] ?? null);
                                $notes_str = $meta_arr['notes'] ?? '';
                                $search_haystack = mb_strtolower($a['first_name'] . ' ' . $a['last_name'] . ' ' . $cat_label . ' ' . $a['phone'] . ' ' . $a['email'] . ' ' . $notes_str, 'UTF-8');
                            ?>
                            <tr class="athlete-row hover:bg-zinc-50/60 transition-colors" data-search="<?= htmlspecialchars($search_haystack) ?>">
                                <td class="p-4 text-center">
                                    <?php if ($is_u16): ?>
                                        <input 
                                            type="checkbox" 
                                            class="u16-checkbox w-4 h-4 text-rose-600 rounded border-zinc-300 focus:ring-rose-500 cursor-pointer" 
                                            value="<?= (int)$a['id'] ?>"
                                        >
                                    <?php else: ?>
                                        <span class="text-zinc-300">&bull;</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-4 font-medium text-zinc-900">
                                    <div class="font-bold text-sm text-zinc-900">
                                        <?= htmlspecialchars($a['last_name'] . ' ' . $a['first_name']) ?>
                                    </div>
                                    <div class="text-[11px] text-zinc-400 mt-0.5">
                                        <?= htmlspecialchars($a['phone'] ?: ($a['email'] ?: 'Pas de contact')) ?>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <span class="font-semibold text-zinc-800"><?= htmlspecialchars($cat_label) ?></span>
                                    <span class="text-zinc-400 text-[11px] block"><?= $birth_year > 0 ? "Né en {$birth_year}" : "Année manquante" ?></span>
                                </td>
                                <td class="p-4">
                                    <?= $status_badge ?>
                                </td>
                                <td class="p-4 font-mono text-zinc-800 font-bold">
                                    <span><?= htmlspecialchars($a['access_pin']) ?></span>
                                    <form action="<?= url('/admin/reset-pin') ?>" method="POST" class="inline ml-1.5" onsubmit="return confirm('Réinitialiser le code PIN de cet athlète ?');">
                                        <input type="hidden" name="athlete_id" value="<?= (int)$a['id'] ?>">
                                        <button type="submit" class="text-[10px] text-zinc-400 hover:text-rose-600 underline font-sans font-normal" title="Réinitialiser à JJMM ou 0000">
                                            reset
                                        </button>
                                    </form>
                                </td>
                                <td class="p-4">
                                    <button 
                                        type="button" 
                                        onclick="copyToClipboard('<?= addslashes($direct_full_url) ?>', 'Lien direct copié !')"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-zinc-700 font-medium border border-zinc-200 transition-colors text-xs"
                                        title="Copier le lien direct sans code PIN"
                                    >
                                        <span>Copier lien</span>
                                    </button>
                                </td>
                                <td class="p-4 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center justify-end gap-1.5">
                                        <!-- 1. Bouton Principal d'entretien (uniforme pour toutes les catégories) -->
                                        <?php if ($is_u16): ?>
                                            <a 
                                                href="<?= url('/admin/entretien/u16?ids=' . (int)$a['id']) ?>" 
                                                class="inline-flex items-center justify-center gap-1.5 px-3 h-7 bg-zinc-900 hover:bg-zinc-800 text-white font-semibold rounded-lg text-xs transition-colors shadow-xs"
                                                title="Ouvrir l'entretien de cadrage U16"
                                            >
                                                <span>Entretien</span>
                                                <span class="text-[10px] text-zinc-400">→</span>
                                            </a>
                                        <?php else: ?>
                                            <a 
                                                href="<?= url('/admin/entretien/u18?' . (!empty($a['interview_id']) ? 'interview_id=' . (int)$a['interview_id'] : 'athlete_id=' . (int)$a['id'])) ?>" 
                                                class="inline-flex items-center justify-center gap-1.5 px-3 h-7 bg-zinc-900 hover:bg-zinc-800 text-white font-semibold rounded-lg text-xs transition-colors shadow-xs"
                                                title="Ouvrir l'entretien individuel"
                                            >
                                                <span>Entretien</span>
                                                <span class="text-[10px] text-zinc-400">→</span>
                                            </a>
                                        <?php endif; ?>

                                        <!-- Séparateur 1 -->
                                        <div class="h-4 w-px bg-zinc-200 mx-0.5"></div>

                                        <!-- 2. Actions liées à l'entretien (WhatsApp, PDF, Déverrouiller) -->
                                        <?php if (!empty($a['interview_id'])): ?>
                                            <button 
                                                type="button" 
                                                onclick="copyWhatsAppSynthesis(<?= (int)$a['interview_id'] ?>, this)" 
                                                class="inline-flex items-center justify-center w-7 h-7 bg-zinc-100 hover:bg-emerald-50 text-zinc-500 hover:text-emerald-700 rounded-lg border border-zinc-200/80 hover:border-emerald-200 transition-colors"
                                                title="Copier la synthèse WhatsApp"
                                            >
                                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                                </svg>
                                            </button>
                                            <a 
                                                href="<?= url('/admin/print-summary?interview_id=' . (int)$a['interview_id']) ?>" 
                                                target="_blank"
                                                class="inline-flex items-center justify-center w-7 h-7 bg-zinc-100 hover:bg-zinc-200 text-zinc-500 hover:text-zinc-900 rounded-lg border border-zinc-200/80 transition-colors"
                                                title="Imprimer / Enregistrer la fiche bilan en PDF"
                                            >
                                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                                    <rect x="6" y="14" width="12" height="8"></rect>
                                                </svg>
                                            </a>
                                        <?php else: ?>
                                            <span class="inline-flex items-center justify-center w-7 h-7 bg-zinc-50 text-zinc-300 rounded-lg border border-zinc-100 opacity-40 cursor-not-allowed" title="Aucun entretien actif">
                                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                                </svg>
                                            </span>
                                            <span class="inline-flex items-center justify-center w-7 h-7 bg-zinc-50 text-zinc-300 rounded-lg border border-zinc-100 opacity-40 cursor-not-allowed" title="Aucun entretien actif">
                                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                                                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                                                    <rect x="6" y="14" width="12" height="8"></rect>
                                                </svg>
                                            </span>
                                        <?php endif; ?>

                                        <?php if (in_array($status, ['submitted', 'completed'], true) && !empty($a['interview_id'])): ?>
                                            <form action="<?= url('/admin/reopen') ?>" method="POST" class="inline" onsubmit="return confirm('Déverrouiller la fiche pour permettre les modifications ?');">
                                                <input type="hidden" name="interview_id" value="<?= (int)$a['interview_id'] ?>">
                                                <button type="submit" class="inline-flex items-center justify-center w-7 h-7 bg-zinc-100 hover:bg-amber-50 text-zinc-500 hover:text-amber-700 rounded-lg border border-zinc-200/80 hover:border-amber-200 transition-colors" title="Déverrouiller l'entretien pour modifications">
                                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                        <path d="M7 11V7a5 5 0 0 1 9.9-1"></path>
                                                    </svg>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="inline-flex items-center justify-center w-7 h-7 bg-zinc-50 text-zinc-200 rounded-lg border border-zinc-100 opacity-20 cursor-not-allowed">
                                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                                </svg>
                                            </span>
                                        <?php endif; ?>

                                        <!-- Séparateur 2 -->
                                        <div class="h-4 w-px bg-zinc-200 mx-0.5"></div>

                                        <!-- 3. Gestion athlète (Modifier, Supprimer) -->
                                        <button 
                                            type="button" 
                                            data-id="<?= (int)$a['id'] ?>"
                                            data-firstname="<?= htmlspecialchars($a['first_name'], ENT_QUOTES) ?>"
                                            data-lastname="<?= htmlspecialchars($a['last_name'], ENT_QUOTES) ?>"
                                            data-birthdate="<?= htmlspecialchars($a['birth_date'] ?? '', ENT_QUOTES) ?>"
                                            data-category="<?= htmlspecialchars($a['category'] ?? 'U16', ENT_QUOTES) ?>"
                                            data-phone="<?= htmlspecialchars($a['phone'] ?? '', ENT_QUOTES) ?>"
                                            data-email="<?= htmlspecialchars($a['email'] ?? '', ENT_QUOTES) ?>"
                                            data-notes="<?= htmlspecialchars($notes_str, ENT_QUOTES) ?>"
                                            onclick="handleEditAthleteBtn(this)"
                                            class="inline-flex items-center justify-center w-7 h-7 bg-zinc-100 hover:bg-zinc-200 text-zinc-500 hover:text-zinc-900 rounded-lg border border-zinc-200/80 transition-colors"
                                            title="Modifier l'athlète"
                                        >
                                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
                                            </svg>
                                        </button>

                                        <form action="<?= url('/admin/delete-athlete') ?>" method="POST" class="inline" onsubmit="return confirm('Supprimer <?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name'], ENT_QUOTES) ?> et l\'ensemble de ses bilans ?');">
                                            <input type="hidden" name="athlete_id" value="<?= (int)$a['id'] ?>">
                                            <button 
                                                type="submit" 
                                                class="inline-flex items-center justify-center w-7 h-7 bg-zinc-100 hover:bg-rose-50 text-zinc-400 hover:text-rose-600 rounded-lg border border-zinc-200/80 hover:border-rose-200 transition-colors"
                                                title="Supprimer l'athlète"
                                            >
                                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                    <line x1="10" y1="11" x2="10" y2="17"></line>
                                                    <line x1="14" y1="11" x2="14" y2="17"></line>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal Ajout Manuel d'Athlète -->
<div id="modal-add-athlete" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="modal-content-card rounded-2xl shadow-2xl border border-zinc-200 max-w-md w-full overflow-hidden relative z-10">
        <div class="p-5 border-b border-zinc-100 flex items-center justify-between">
            <h3 class="font-bold font-heading text-sm text-zinc-900">Ajouter un athlète manuellement</h3>
            <button onclick="closeModal('modal-add-athlete')" class="text-zinc-400 hover:text-zinc-700 text-sm">✕</button>
        </div>
        <form action="<?= url('/admin/add-athlete') ?>" method="POST" class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Prénom *</label>
                    <input type="text" name="first_name" required class="w-full px-3 py-2 border border-zinc-300 rounded-xl text-xs bg-zinc-50/50 focus:bg-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Nom *</label>
                    <input type="text" name="last_name" required class="w-full px-3 py-2 border border-zinc-300 rounded-xl text-xs bg-zinc-50/50 focus:bg-white">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Date de naissance</label>
                    <input type="date" name="birth_date" class="w-full px-3 py-2 border border-zinc-300 rounded-xl text-xs bg-zinc-50/50 focus:bg-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Catégorie</label>
                    <select name="category" class="w-full px-3 py-2 border border-zinc-300 rounded-xl text-xs bg-zinc-50/50 focus:bg-white">
                        <option value="U16">U16</option>
                        <option value="U18">U18</option>
                        <option value="U20">U20</option>
                        <option value="U23">U23</option>
                        <option value="Elite">Élite / Actifs</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Téléphone</label>
                    <input type="text" name="phone" placeholder="+41..." class="w-full px-3 py-2 border border-zinc-300 rounded-xl text-xs bg-zinc-50/50 focus:bg-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Email</label>
                    <input type="email" name="email" placeholder="nom@example.ch" class="w-full px-3 py-2 border border-zinc-300 rounded-xl text-xs bg-zinc-50/50 focus:bg-white">
                </div>
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-zinc-100">
                <button type="button" onclick="closeModal('modal-add-athlete')" class="px-4 py-2 rounded-xl text-xs font-semibold text-zinc-600 hover:bg-zinc-100">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-white rounded-xl text-xs font-bold shadow-sm">
                    Enregistrer l'athlète
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Modification d'Athlète -->
<div id="modal-edit-athlete" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="modal-content-card rounded-2xl shadow-2xl border border-zinc-200 max-w-md w-full overflow-hidden relative z-10">
        <div class="p-5 border-b border-zinc-100 flex items-center justify-between">
            <h3 class="font-bold font-heading text-sm text-zinc-900">Modifier les coordonnées de l'athlète</h3>
            <button onclick="closeModal('modal-edit-athlete')" class="text-zinc-400 hover:text-zinc-700 text-sm">✕</button>
        </div>
        <form action="<?= url('/admin/edit-athlete') ?>" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="athlete_id" id="edit-athlete-id" value="">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Prénom *</label>
                    <input type="text" name="first_name" id="edit-first-name" required class="w-full px-3 py-2 border border-zinc-300 rounded-xl text-xs bg-zinc-50/50 focus:bg-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Nom *</label>
                    <input type="text" name="last_name" id="edit-last-name" required class="w-full px-3 py-2 border border-zinc-300 rounded-xl text-xs bg-zinc-50/50 focus:bg-white">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Date de naissance</label>
                    <input type="date" name="birth_date" id="edit-birth-date" class="w-full px-3 py-2 border border-zinc-300 rounded-xl text-xs bg-zinc-50/50 focus:bg-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Catégorie</label>
                    <select name="category" id="edit-category" class="w-full px-3 py-2 border border-zinc-300 rounded-xl text-xs bg-zinc-50/50 focus:bg-white">
                        <option value="U16">U16</option>
                        <option value="U18">U18</option>
                        <option value="U20">U20</option>
                        <option value="U23">U23</option>
                        <option value="Elite">Élite / Actifs</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Téléphone</label>
                    <input type="text" name="phone" id="edit-phone" placeholder="+41..." class="w-full px-3 py-2 border border-zinc-300 rounded-xl text-xs bg-zinc-50/50 focus:bg-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Email</label>
                    <input type="email" name="email" id="edit-email" placeholder="nom@example.ch" class="w-full px-3 py-2 border border-zinc-300 rounded-xl text-xs bg-zinc-50/50 focus:bg-white">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-700 mb-1">Notes internes (facultatif)</label>
                <input type="text" name="notes" id="edit-notes" placeholder="Remarques staff..." class="w-full px-3 py-2 border border-zinc-300 rounded-xl text-xs bg-zinc-50/50 focus:bg-white">
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-zinc-100">
                <button type="button" onclick="closeModal('modal-edit-athlete')" class="px-4 py-2 rounded-xl text-xs font-semibold text-zinc-600 hover:bg-zinc-100">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-white rounded-xl text-xs font-bold shadow-sm">
                    Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Importation CSV -->
<div id="modal-import-csv" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="modal-content-card rounded-2xl shadow-2xl border border-zinc-200 max-w-md w-full overflow-hidden relative z-10">
        <div class="p-5 border-b border-zinc-100 flex items-center justify-between">
            <h3 class="font-bold font-heading text-sm text-zinc-900">Importer des athlètes (CSV)</h3>
            <button onclick="closeModal('modal-import-csv')" class="text-zinc-400 hover:text-zinc-700 text-sm">✕</button>
        </div>
        <form action="<?= url('/admin/import-csv') ?>" method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
            <div class="bg-zinc-50 border border-zinc-200 rounded-xl p-3 text-xs text-zinc-600">
                <p><strong>Mise à jour intelligente (Upsert) :</strong> Si le couple Nom + Prénom existe déjà, ses coordonnées seront mises à jour sans effacer les bilans existants.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-zinc-700 mb-1">Fichier CSV (UTF-8, séparateur , ou ;)</label>
                <input type="file" name="csv_file" accept=".csv,text/csv" required class="w-full text-xs text-zinc-600 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-zinc-100 file:text-zinc-800 hover:file:bg-zinc-200">
            </div>

            <div class="pt-3 flex items-center justify-between border-t border-zinc-100">
                <a href="<?= url('/admin/download-template') ?>" class="text-xs text-zinc-600 hover:text-zinc-900 underline font-medium">
                    Modèle CSV type
                </a>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="closeModal('modal-import-csv')" class="px-4 py-2 rounded-xl text-xs font-semibold text-zinc-600 hover:bg-zinc-100">
                        Annuler
                    </button>
                    <button type="submit" class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-white rounded-xl text-xs font-bold shadow-sm">
                        Lancer l'import
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Gestion des Saisons -->
<div id="modal-manage-seasons" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="modal-content-card rounded-2xl shadow-2xl border border-zinc-200 max-w-xl w-full overflow-hidden relative z-10">
        <div class="p-5 border-b border-zinc-100 flex items-center justify-between">
            <h3 class="font-bold font-heading text-sm text-zinc-900">Gestion des saisons et périodes</h3>
            <button onclick="closeModal('modal-manage-seasons')" class="text-zinc-400 hover:text-zinc-700 text-sm">✕</button>
        </div>
        <div class="p-6 space-y-6">
            <!-- 1. Liste des saisons avec actions directes (Renommer, Activer, Supprimer) -->
            <div>
                <h4 class="text-xs font-bold text-zinc-900 uppercase tracking-wider mb-2">Saisons enregistrées</h4>
                <div class="space-y-2.5 max-h-60 overflow-y-auto pr-1">
                    <?php foreach ($all_seasons as $s): ?>
                        <div class="p-3 bg-zinc-50 border border-zinc-200/80 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                <form action="<?= url('/admin/rename-season') ?>" method="POST" class="flex items-center gap-1.5 flex-1 min-w-0">
                                    <input type="hidden" name="season_id" value="<?= (int)$s['id'] ?>">
                                    <input 
                                        type="text" 
                                        name="season_name" 
                                        value="<?= htmlspecialchars($s['name']) ?>" 
                                        required 
                                        class="text-xs font-semibold text-zinc-900 bg-white border border-zinc-200 rounded-lg px-2.5 py-1 focus:ring-1 focus:ring-zinc-900 flex-1 min-w-[120px]"
                                        title="Modifier le nom et appuyer sur Renommer"
                                    >
                                    <button 
                                        type="submit" 
                                        class="px-2.5 py-1 bg-zinc-200 hover:bg-zinc-300 text-zinc-800 text-[11px] font-bold rounded-lg transition-colors whitespace-nowrap"
                                        title="Enregistrer le nouveau nom"
                                    >
                                        Renommer
                                    </button>
                                </form>
                                <?php if ((int)$s['is_active'] === 1): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300/60 whitespace-nowrap">
                                        Active
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="flex items-center gap-1.5 self-end sm:self-center">
                                <?php if ((int)$s['is_active'] === 0): ?>
                                    <form action="<?= url('/admin/set-active-season') ?>" method="POST" class="inline">
                                        <input type="hidden" name="season_id" value="<?= (int)$s['id'] ?>">
                                        <button 
                                            type="submit" 
                                            class="px-2.5 py-1 bg-zinc-900 hover:bg-zinc-800 text-white text-[11px] font-semibold rounded-lg transition-colors whitespace-nowrap shadow-sm"
                                        >
                                            Activer
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (count($all_seasons) > 1): ?>
                                    <form 
                                        action="<?= url('/admin/delete-season') ?>" 
                                        method="POST" 
                                        class="inline"
                                        onsubmit="return confirm('Attention : Êtes-vous sûr de vouloir supprimer la saison « <?= htmlspecialchars(addslashes($s['name'])) ?> » ? Tous les entretiens et données de cette saison seront supprimés.');"
                                    >
                                        <input type="hidden" name="season_id" value="<?= (int)$s['id'] ?>">
                                        <button 
                                            type="submit" 
                                            class="px-2 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 text-[11px] font-semibold rounded-lg border border-rose-200/80 transition-colors"
                                            title="Supprimer cette saison et ses entretiens"
                                        >
                                            Supprimer
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <hr class="border-zinc-100">

            <!-- 2. Créer une nouvelle saison -->
            <div>
                <h4 class="text-xs font-bold text-zinc-900 uppercase tracking-wider mb-2">Créer une nouvelle saison</h4>
                <p class="text-xs text-zinc-500 mb-3">
                    Crée une nouvelle saison et initialise automatiquement une fiche d'entretien pour chaque athlète existant.
                </p>
                <form action="<?= url('/admin/create-season') ?>" method="POST" class="flex items-center gap-2">
                    <input 
                        type="text" 
                        name="season_name" 
                        placeholder="ex: 2027-2028" 
                        required 
                        class="flex-1 text-xs border border-zinc-200 rounded-xl px-3 py-2 bg-white text-zinc-800 focus:outline-none focus:ring-1 focus:ring-zinc-900"
                    >
                    <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold whitespace-nowrap shadow-sm">
                        Créer la saison
                    </button>
                </form>
            </div>

            <div class="pt-2 flex justify-end border-t border-zinc-100">
                <button type="button" onclick="closeModal('modal-manage-seasons')" class="px-4 py-2 rounded-xl text-xs font-semibold text-zinc-600 hover:bg-zinc-100">
                    Fermer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Sauvegarde et Restauration -->
<div id="modal-backup-restore" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="modal-content-card rounded-2xl shadow-2xl border border-zinc-200 max-w-lg w-full overflow-hidden relative z-10">
        <div class="p-5 border-b border-zinc-100 flex items-center justify-between">
            <h3 class="font-bold font-heading text-sm text-zinc-900">Sauvegarde et restauration de la base</h3>
            <button onclick="closeModal('modal-backup-restore')" class="text-zinc-400 hover:text-zinc-700 text-sm">✕</button>
        </div>
        <div class="p-6 space-y-6">
            <!-- Sauvegarde -->
            <div>
                <h4 class="text-xs font-bold text-zinc-900 uppercase tracking-wider mb-2">1. Télécharger une sauvegarde</h4>
                <p class="text-xs text-zinc-500 mb-3">Exportez l'intégralité des saisons, athlètes et bilans pour archivage ou transfert.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <a 
                        href="<?= url('/admin/backup-json') ?>" 
                        class="p-3 bg-zinc-50 hover:bg-zinc-100 border border-zinc-200 rounded-xl text-left block transition-colors"
                    >
                        <strong class="text-xs text-zinc-900 block font-semibold mb-0.5">Format universel (JSON)</strong>
                        <span class="text-[11px] text-zinc-500 block">Recommandé pour migration ou consultation lisible.</span>
                    </a>
                    <a 
                        href="<?= url('/admin/backup-sqlite') ?>" 
                        class="p-3 bg-zinc-50 hover:bg-zinc-100 border border-zinc-200 rounded-xl text-left block transition-colors"
                    >
                        <strong class="text-xs text-zinc-900 block font-semibold mb-0.5">Base brute (.sqlite)</strong>
                        <span class="text-[11px] text-zinc-500 block">Copie binaire intégrale du fichier SQLite.</span>
                    </a>
                </div>
            </div>

            <hr class="border-zinc-100">

            <!-- Restauration -->
            <div>
                <h4 class="text-xs font-bold text-rose-700 uppercase tracking-wider mb-2">2. Restaurer une sauvegarde</h4>
                <p class="text-xs text-zinc-500 mb-3">Sélectionnez un fichier JSON ou SQLite précédemment sauvegardé.</p>
                <form 
                    action="<?= url('/admin/restore-database') ?>" 
                    method="POST" 
                    enctype="multipart/form-data" 
                    onsubmit="return confirm('Attention : Cette action va remplacer les données actuelles par le contenu du fichier de sauvegarde. Voulez-vous continuer ?');"
                    class="space-y-3"
                >
                    <input 
                        type="file" 
                        name="backup_file" 
                        accept=".json,.sqlite,.db" 
                        required 
                        class="w-full text-xs text-zinc-600 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-zinc-100 file:text-zinc-800 hover:file:bg-zinc-200"
                    >
                    <div class="flex items-center justify-between pt-2">
                        <span class="text-[11px] text-zinc-400">Restauration transactionnelle sécurisée</span>
                        <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold shadow-sm">
                            Restaurer la base
                        </button>
                    </div>
                </form>
            </div>

            <div class="pt-2 flex justify-end border-t border-zinc-100">
                <button type="button" onclick="closeModal('modal-backup-restore')" class="px-4 py-2 rounded-xl text-xs font-semibold text-zinc-600 hover:bg-zinc-100">
                    Fermer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Paramètres du Club et Configuration -->
<div id="modal-settings" class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div class="modal-content-card rounded-2xl shadow-2xl border border-zinc-200 max-w-lg w-full overflow-hidden relative z-10">
        <div class="p-5 border-b border-zinc-100 flex items-center justify-between">
            <h3 class="font-bold font-heading text-sm text-zinc-900">Paramètres du club et configuration</h3>
            <button onclick="closeModal('modal-settings')" class="text-zinc-400 hover:text-zinc-700 text-sm">✕</button>
        </div>
        <form action="<?= url('/admin/settings') ?>" method="POST" class="p-6 space-y-5">
            <!-- 1. Informations du Club -->
            <div class="space-y-3">
                <h4 class="text-xs font-bold text-zinc-900 uppercase tracking-wider">Identité et contacts</h4>
                
                <div>
                    <label class="block text-xs font-semibold text-zinc-700 mb-1">Nom du club</label>
                    <input 
                        type="text" 
                        name="club_name" 
                        value="<?= htmlspecialchars($settings['club_name'] ?? 'CA Sion') ?>" 
                        required 
                        class="w-full text-xs border border-zinc-200 rounded-xl px-3 py-2 bg-white text-zinc-800 focus:outline-none focus:ring-1 focus:ring-zinc-900"
                    >
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 mb-1">Téléphone de contact WhatsApp</label>
                        <input 
                            type="text" 
                            name="coach_phone" 
                            value="<?= htmlspecialchars($settings['coach_phone'] ?? '+41791234567') ?>" 
                            placeholder="+41791234567" 
                            class="w-full text-xs border border-zinc-200 rounded-xl px-3 py-2 bg-white text-zinc-800 focus:outline-none focus:ring-1 focus:ring-zinc-900"
                        >
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-zinc-700 mb-1">Année de compétition de référence</label>
                        <input 
                            type="number" 
                            name="reference_competition_year" 
                            value="<?= (int)($settings['reference_competition_year'] ?? 2027) ?>" 
                            min="2020" 
                            max="2040" 
                            required 
                            class="w-full text-xs border border-zinc-200 rounded-xl px-3 py-2 bg-white text-zinc-800 focus:outline-none focus:ring-1 focus:ring-zinc-900"
                        >
                    </div>
                </div>
            </div>

            <hr class="border-zinc-100">

            <!-- 2. Mot de passe Administrateur -->
            <div class="space-y-2">
                <h4 class="text-xs font-bold text-zinc-900 uppercase tracking-wider">Mot de passe de l'espace entraîneur</h4>
                <p class="text-[11px] text-zinc-500">
                    Laissez vide pour conserver le mot de passe actuel.
                </p>
                <input 
                    type="password" 
                    name="new_admin_password" 
                    placeholder="Nouveau mot de passe (min. 4 caractères)" 
                    autocomplete="new-password"
                    class="w-full text-xs border border-zinc-200 rounded-xl px-3 py-2 bg-white text-zinc-800 focus:outline-none focus:ring-1 focus:ring-zinc-900"
                >
                <div class="bg-amber-50 border border-amber-200/80 rounded-xl p-2.5 text-[11px] text-amber-800 mt-2">
                    <strong>Sécurité anti-blocage :</strong> La variable <code>ADMIN_PASSWORD</code> de votre fichier <code>.env</code> conserve toujours un rôle de clé maîtresse de secours.
                </div>
            </div>

            <div class="pt-3 flex items-center justify-end gap-2 border-t border-zinc-100">
                <button type="button" onclick="closeModal('modal-settings')" class="px-4 py-2 rounded-xl text-xs font-semibold text-zinc-600 hover:bg-zinc-100">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-white rounded-xl text-xs font-bold shadow-sm">
                    Enregistrer les paramètres
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.remove('hidden');
    }
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.add('hidden');
    }
}

function handleEditAthleteBtn(btn) {
    if (!btn) return;
    document.getElementById('edit-athlete-id').value = btn.dataset.id || '';
    document.getElementById('edit-first-name').value = btn.dataset.firstname || '';
    document.getElementById('edit-last-name').value = btn.dataset.lastname || '';
    document.getElementById('edit-birth-date').value = btn.dataset.birthdate || '';
    document.getElementById('edit-category').value = btn.dataset.category || 'U16';
    document.getElementById('edit-phone').value = btn.dataset.phone || '';
    document.getElementById('edit-email').value = btn.dataset.email || '';
    document.getElementById('edit-notes').value = btn.dataset.notes || '';

    openModal('modal-edit-athlete');
}

// Recherche instantanée ultra-rapide (0ms) pour 80+ athlètes
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('table-search-input');
    const rows = document.querySelectorAll('.athlete-row');

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const q = e.target.value.toLowerCase().trim();
            rows.forEach(row => {
                const text = row.dataset.search || '';
                if (q === '' || text.includes(q)) {
                    row.classList.remove('hidden');
                } else {
                    row.classList.add('hidden');
                }
            });
        });
    }

    const checkboxes = document.querySelectorAll('.u16-checkbox');
    const groupBar = document.getElementById('u16-group-bar');
    const countDisplay = document.getElementById('selected-count');
    const launchBtn = document.getElementById('btn-launch-u16-group');

    function updateSelection() {
        const selected = Array.from(checkboxes).filter(cb => cb.checked).map(cb => cb.value);
        if (countDisplay) countDisplay.textContent = selected.length;

        if (selected.length >= 2 && selected.length <= 4) {
            groupBar?.classList.remove('hidden');
            if (launchBtn) {
                launchBtn.onclick = () => {
                    window.location.href = `${window.APP_BASE_URL || ''}/admin/entretien/u16?ids=${selected.join(',')}`;
                };
            }
        } else if (selected.length > 4) {
            alert('Vous pouvez sélectionner au maximum 4 athlètes simultanément pour l\'entretien groupé.');
            groupBar?.classList.add('hidden');
        } else {
            groupBar?.classList.add('hidden');
        }
    }

    checkboxes.forEach(cb => cb.addEventListener('change', updateSelection));
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
