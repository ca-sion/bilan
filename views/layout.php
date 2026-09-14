<?php
/**
 * Gabarit principal HTML5 responsive — Style Suisse Minimaliste et Bento Japonais
 */
$page_title = $page_title ?? 'Bilan et Débriefing de Saison';
$flashes = get_flashes();
$base_url_js = get_base_url();
$is_admin = Auth::is_logged_in();
$is_athlete = !empty($_SESSION['athlete_id']);
?>
<!DOCTYPE html>
<html lang="fr" class="h-full bg-[#f8f9fa] text-zinc-900 antialiased font-sans">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — <?= htmlspecialchars(env('CLUB_NAME', 'CA Sion')) ?></title>
    
    <meta name="description" content="Plateforme officielle de bilan individuel et projection de saison pour les athlètes et entraîneurs du <?= htmlspecialchars(env('CLUB_NAME', 'CA Sion')) ?>.">

    <!-- Polices modernes et épurées (Inter & Plus Jakarta Sans) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS (CDN autonome) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        zinc: {
                            50: '#fafafa',
                            100: '#f4f4f5',
                            200: '#e4e4e7',
                            300: '#d4d4d8',
                            400: '#a1a1aa',
                            500: '#71717a',
                            600: '#52525b',
                            700: '#3f3f46',
                            800: '#27272a',
                            900: '#18181b',
                            950: '#09090b',
                        },
                        brand: {
                            50: '#fff1f2',
                            100: '#ffe4e6',
                            200: '#fecdd3',
                            300: '#fda4af',
                            400: '#fb7185',
                            500: '#f43f5e',
                            600: '#e11d48',
                            700: '#be123c',
                            800: '#9f1239',
                            900: '#881337',
                        }
                    },
                    fontFamily: {
                        sans: ['"Inter"', '-apple-system', 'BlinkMacSystemFont', '"Segoe UI"', 'Roboto', 'sans-serif'],
                        heading: ['"Plus Jakarta Sans"', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <!-- Feuille de style personnalisée -->
    <link rel="stylesheet" href="<?= url('/assets/css/custom.css') ?>">
    <script>
        window.APP_BASE_URL = '<?= $base_url_js ?>';
    </script>
</head>
<body class="min-h-full flex flex-col justify-between text-zinc-800 bg-[#f8f9fa] selection:bg-rose-600 selection:text-white">

    <!-- En-tête de navigation épuré -->
    <header class="no-print sticky top-0 z-40 bg-white border-b border-zinc-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                
                <!-- Logo et Identité Club -->
                <a href="<?= url($is_admin ? '/admin' : '/bilan') ?>" class="flex items-center gap-2.5 group">
                    <span class="font-heading font-bold text-base text-zinc-900 tracking-tight group-hover:text-rose-600 transition-colors">
                        <?= htmlspecialchars(env('CLUB_NAME', 'CA Sion')) ?>
                    </span>
                    <span class="text-zinc-300">/</span>
                    <span class="text-xs font-semibold text-zinc-500">
                        Débriefing de saison
                    </span>
                </a>

                <!-- Navigation droite -->
                <div class="flex items-center gap-2.5">
                    <?php if ($is_athlete): ?>
                        <span class="hidden sm:inline-flex items-center text-xs font-medium text-zinc-600 px-3 py-1.5 rounded-lg bg-zinc-100 border border-zinc-200">
                            Athlète : <strong class="ml-1.5 text-zinc-900"><?= htmlspecialchars($_SESSION['athlete_name'] ?? '') ?></strong>
                        </span>
                        <a href="<?= url('/bilan/logout') ?>" class="text-xs font-semibold text-zinc-600 hover:text-zinc-900 px-3 py-1.5 rounded-lg border border-zinc-200 hover:bg-zinc-100 transition-colors">
                            Changer d'athlète
                        </a>
                    <?php elseif ($is_admin): ?>
                        <a href="<?= url('/admin') ?>" class="text-xs font-semibold text-zinc-700 hover:text-zinc-900 px-3 py-1.5 rounded-lg hover:bg-zinc-100 transition-colors">
                            Tableau de bord
                        </a>
                        <a href="<?= url('/admin/logout') ?>" class="text-xs font-semibold text-zinc-600 hover:text-rose-600 px-3 py-1.5 rounded-lg border border-zinc-200 hover:border-rose-200 hover:bg-rose-50/50 transition-colors">
                            Déconnexion coach
                        </a>
                    <?php else: ?>
                        <a href="<?= url('/admin/login') ?>" class="text-xs font-semibold text-zinc-600 hover:text-zinc-900 px-3 py-1.5 rounded-lg border border-zinc-200 hover:bg-zinc-100 transition-colors">
                            Espace entraîneur
                        </a>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </header>

    <!-- Notifications flash -->
    <?php if (!empty($flashes)): ?>
        <div class="no-print max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 space-y-2">
            <?php foreach ($flashes as $f): ?>
                <?php
                    $is_err = ($f['type'] === 'error');
                    $bg_cls = $is_err ? 'bg-rose-50 border-rose-200 text-rose-900' : 'bg-emerald-50 border-emerald-200 text-emerald-900';
                    $icon = $is_err ? '✕' : '✓';
                ?>
                <div class="flex items-center justify-between p-3.5 rounded-xl border <?= $bg_cls ?> text-xs font-medium animate-fade-in shadow-sm">
                    <div class="flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full flex items-center justify-center font-bold text-[10px] <?= $is_err ? 'bg-rose-200 text-rose-800' : 'bg-emerald-200 text-emerald-800' ?>">
                            <?= $icon ?>
                        </span>
                        <span><?= htmlspecialchars($f['message']) ?></span>
                    </div>
                    <button onclick="this.parentElement.remove()" class="text-zinc-400 hover:text-zinc-700 text-sm">✕</button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Contenu principal -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
        <?= $content ?>
    </main>

    <!-- Pied de page minimaliste -->
    <footer class="no-print bg-white border-t border-zinc-200/80 py-6 text-center text-xs text-zinc-500">
        <div class="max-w-7xl mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
            <div class="flex items-center gap-2 font-medium text-zinc-600">
                <span class="w-2 h-2 rounded-full bg-rose-600"></span>
                <span><?= htmlspecialchars(env('CLUB_NAME', 'CA Sion')) ?></span>
            </div>
            <div class="text-zinc-400 text-[11px]">
                Plateforme de débriefing
            </div>
        </div>
    </footer>

    <!-- Toast de notification asynchrone -->
    <div id="toast-container" class="no-print fixed bottom-6 right-6 z-50 flex flex-col gap-2 pointer-events-none"></div>

    <!-- Scripts de base -->
    <script src="<?= url('/assets/js/app.js') ?>"></script>
</body>
</html>
