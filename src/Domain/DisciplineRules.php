<?php
declare(strict_types=1);

namespace App\Domain;

/**
 * Règles d'incompatibilité, regroupements physiologiques et abréviations d'épreuves
 */
class DisciplineRules {

    /**
     * Classe une discipline saisie ou slug dans sa famille physiologique
     */
    public static function get_discipline_family(string $discipline): string {
        $d = mb_strtolower(trim($discipline), 'UTF-8');
        if ($d === '' || $d === 'none') {
            return 'none';
        }

        // 1. Épreuves combinées / Polyvalence
        if (preg_match('/(combin[ée]|d[ée]cath|heptath|pentath|polyval)/i', $d)) {
            return 'combined';
        }

        // 2. Demi-fond / Fond / Filière aérobie (ex. 600m, 800m, 1000m, 1500m, 3000m, cross, steeple)
        if (preg_match('/(demi-fond|demifond|fond|600\s*m|800\s*m|1000\s*m|1500\s*m|2000\s*m|3000\s*m|5000\s*m|10000\s*m|cross|steeple|route|trail|marche|endurance|middle_distance)/i', $d)) {
            return 'endurance';
        }

        // 3. Sauts / Sprint court / Haies (Filière explosive / anaérobie)
        if (preg_match('/(longueur|triple|hauteur|perche|saut|high_jump|long_jump|triple_jump|pole_vault|100\s*m|200\s*m|400\s*m|sprint|haie|hurdles|60\s*m|110\s*m|relais)/i', $d)) {
            return 'explosive_sprint_jump';
        }

        // 4. Lancers (Force et puissance pure)
        if (preg_match('/(poids|disque|javelot|marteau|lancer|shot_put|discus|javelin|hammer)/i', $d)) {
            return 'throws';
        }

        return 'other';
    }

    /**
     * Valide la compatibilité physiologique et réglementaire de 2 disciplines choisies
     * @return array{is_compatible: bool, warning: string|null}
     */
    public static function check_disciplines_compatibility(?string $d1, ?string $d2): array {
        $d1 = trim((string)$d1);
        $d2 = trim((string)$d2);

        if ($d1 === '' || $d2 === '' || mb_strtolower($d1, 'UTF-8') === mb_strtolower($d2, 'UTF-8')) {
            $fam = self::get_discipline_family($d1 !== '' ? $d1 : $d2);
            if ($fam === 'combined' && $d1 !== '' && $d2 !== '') {
                return [
                    'is_compatible' => false,
                    'warning' => 'Les épreuves combinées constituent déjà un programme complet. Aucune deuxième discipline isolée n\'est autorisée.'
                ];
            }
            return ['is_compatible' => true, 'warning' => null];
        }

        $fam1 = self::get_discipline_family($d1);
        $fam2 = self::get_discipline_family($d2);

        // Règle 1 : Épreuves combinées
        if ($fam1 === 'combined' || $fam2 === 'combined') {
            return [
                'is_compatible' => false,
                'warning' => 'Les épreuves combinées constituent déjà un programme complet polyvalent. Elles ne se cumulent pas avec une deuxième discipline isolée.'
            ];
        }

        // Règle 2 : Demi-fond / Endurance vs Sauts / Sprint / Lancers
        $opposed_fams = ['explosive_sprint_jump', 'throws'];
        if (($fam1 === 'endurance' && in_array($fam2, $opposed_fams, true)) ||
            ($fam2 === 'endurance' && in_array($fam1, $opposed_fams, true))) {
            return [
                'is_compatible' => false,
                'warning' => 'Attention : cette combinaison associe des filières physiologiques opposées (endurance / demi-fond vs vitesse / sauts / lancers). Elle nécessite un arbitrage strict du coach.'
            ];
        }

        return ['is_compatible' => true, 'warning' => null];
    }

    /**
     * Abréviation standard pour la grille d'export du club (ex: DF, Sprint, Saut, Lanc, Haies, Force, End.)
     */
    public static function get_short_code(?string $discipline): string {
        if ($discipline === null || $discipline === '' || $discipline === 'none') {
            return '';
        }

        $d = mb_strtolower(trim($discipline), 'UTF-8');

        // Cas particuliers explicites
        if (preg_match('/(demi-fond|demifond|800\s*m|1500\s*m|3000\s*m|1000\s*m)/i', $d)) {
            return 'DF';
        }
        if (preg_match('/(endurance|cross|trail|route)/i', $d)) {
            return 'End.';
        }
        if (preg_match('/(haie|hurdle)/i', $d)) {
            return 'Haies';
        }
        if (preg_match('/(perche|pole_vault)/i', $d)) {
            return 'Perche';
        }
        if (preg_match('/(longueur|triple|hauteur|saut|jump)/i', $d)) {
            return 'Saut';
        }
        if (preg_match('/(poids|disque|javelot|marteau|lanc|throw|shot_put|discus|javelin)/i', $d)) {
            return 'Lanc';
        }
        if (preg_match('/(sprint|100\s*m|200\s*m|400\s*m|60\s*m)/i', $d)) {
            return 'Sprint';
        }
        if (preg_match('/(force|muscu|gainage|renfo)/i', $d)) {
            return 'Force';
        }
        if (preg_match('/(combin[ée]|d[ée]cath|heptath)/i', $d)) {
            return 'Comb.';
        }

        return ucfirst($discipline);
    }
}
