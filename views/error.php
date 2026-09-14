<?php
$page_title = $page_title ?? "Page introuvable";
$error_message = $error_message ?? "La page que vous recherchez n'existe pas ou a été déplacée.";
ob_start();
?>

<div class="max-w-md mx-auto my-12 text-center">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 space-y-4">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-rose-50 text-rose-600 text-3xl">
            ⚠️
        </div>
        <h1 class="text-2xl font-bold font-heading text-slate-900"><?= htmlspecialchars($page_title) ?></h1>
        <p class="text-xs text-slate-500 leading-relaxed"><?= htmlspecialchars($error_message) ?></p>
        <div class="pt-4">
            <a href="<?= url('/') ?>" class="px-5 py-2.5 bg-zinc-900 hover:bg-zinc-800 text-white font-bold rounded-xl text-xs shadow transition-all inline-block">
                ← Retour à l'accueil
            </a>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
