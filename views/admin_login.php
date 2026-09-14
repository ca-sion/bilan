<?php
$page_title = "Connexion Entraîneur — CA Sion";
ob_start();
?>

<div class="max-w-md mx-auto my-8 sm:my-16">
    <!-- Bento Card de Connexion Entraîneur -->
    <div class="bg-white rounded-2xl border border-zinc-200/80 shadow-sm overflow-hidden p-6 sm:p-8 space-y-6">
        
        <!-- En-tête -->
        <div class="space-y-1.5 text-center pb-4 border-b border-zinc-100">
            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs font-semibold bg-zinc-100 text-zinc-700 border border-zinc-200 mb-1">
                <span>Accès sécurisé</span>
            </div>
            <h1 class="text-2xl font-bold font-heading text-zinc-900 tracking-tight">
                Espace Entraîneur
            </h1>
            <p class="text-xs text-zinc-500">
                Arbitrage, bilatéral et validation des projets sportifs.
            </p>
        </div>

        <!-- Formulaire -->
        <form action="<?= url('/admin/login') ?>" method="POST" class="space-y-5">
            <div>
                <label for="password" class="block text-xs font-semibold text-zinc-700 uppercase tracking-wider mb-1.5">
                    Mot de passe coach
                </label>
                <input 
                    type="password" 
                    name="password" 
                    id="password" 
                    required 
                    autofocus
                    placeholder="••••••••" 
                    class="w-full px-4 py-2.5 rounded-xl border border-zinc-300 focus:ring-2 focus:ring-zinc-900 focus:border-zinc-900 text-sm bg-zinc-50/50 focus:bg-white transition-all"
                >
            </div>

            <button 
                type="submit" 
                class="w-full py-3 px-4 bg-zinc-900 hover:bg-zinc-800 text-white font-semibold rounded-xl text-xs transition-all shadow-sm flex items-center justify-center gap-2"
            >
                <span>Accéder au tableau de bord</span>
                <span>→</span>
            </button>
        </form>

        <div class="pt-4 border-t border-zinc-100 text-center">
            <a href="<?= url('/') ?>" class="text-xs font-medium text-zinc-500 hover:text-zinc-900 transition-colors">
                ← Retour à l'espace athlète
            </a>
        </div>

    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
