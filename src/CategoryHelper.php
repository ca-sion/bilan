<?php
declare(strict_types=1);

/**
 * Assistant de calcul des catégories Swiss Athletics, des libellés et des règles de compatibilité d'épreuves.
 */
class CategoryHelper {

    /**
     * Catalogue officiel des disciplines d'athlétisme
     */
    public const DISCIPLINES = [
        '100m'                                      => '100m',
        '200m'                                      => '200m',
        '400m'                                      => '400m',
        '800m'                                      => '800m',
        '1500m'                                     => '1500m',
        '3000m'                                     => '3000m / 5000m',
        '100m_110m_hurdles'                         => '100m Haies / 110m Haies',
        '400m_hurdles'                              => '400m Haies',
        'high_jump'                                 => 'Saut en hauteur',
        'long_jump'                                 => 'Saut en longueur',
        'triple_jump'                               => 'Triple saut',
        'pole_vault'                                => 'Saut à la perche',
        'shot_put'                                  => 'Lancer du poids',
        'discus'                                    => 'Lancer du disque',
        'javelin'                                   => 'Lancer du javelot',
        'hammer'                                    => 'Lancer du marteau',
        'combined_events'                           => 'Épreuves combinées (Décathlon / Heptathlon)'
    ];

    /**
     * Options de disciplines pour le créneau compétition du vendredi (U16 1ère année)
     */
    public const FRIDAY_DISCIPLINES_U16 = [
        'high_jump'        => 'Hauteur',
        'sprint'           => 'Sprint',
        'middle_distance'  => 'Demi-fond'
    ];

    /**
     * Jours de la semaine en français
     */
    public const DAYS_FR = [
        'monday'           => 'Lundi',
        'tuesday'          => 'Mardi',
        'wednesday'        => 'Mercredi',
        'thursday'         => 'Jeudi',
        'friday'           => 'Vendredi',
        'saturday'         => 'Samedi',
        'saturday_morning' => 'Samedi matin',
        'sunday'           => 'Dimanche'
    ];

    /**
     * Catalogue des principaux freins exprimés par l'athlète
     */
    public const OBSTACLES = [
        'attendance'       => 'Mon assiduité / régularité',
        'stress'           => 'La peur de mal faire / le stress',
        'listening'        => 'Mon écoute des consignes',
        'fatigue_school'   => 'La fatigue ou l\'organisation avec l\'école',
        'other'            => 'Autre raison'
    ];

    /**
     * Disciplines explosives incompatibles avec une filière aérobie dominante (Demi-fond)
     */
    private const EXPLOSIVE_DISCIPLINES = [
        'sprint',
        'pole_vault',
        'shot_put',
        'discus',
        'javelin'
    ];

    /**
     * Calcule l'âge de l'athlète selon l'année de référence de compétition Swiss Athletics
     */
    public static function get_athlete_age(int $birth_year): int {
        if ($birth_year <= 0) {
            return 0;
        }
        $ref_year = function_exists('env') ? (int)env('REFERENCE_COMPETITION_YEAR', 2027) : 2027;
        return $ref_year - $birth_year;
    }

    /**
     * Calcule le code de catégorie (U16, U18, U20, U23, Elite) selon l'année de naissance
     */
    public static function calculate_category(int $birth_year): string {
        if ($birth_year <= 0) {
            return 'U16';
        }
        $age = self::get_athlete_age($birth_year);
        if ($age <= 15) {
            return 'U16';
        }
        if ($age <= 17) {
            return 'U18';
        }
        if ($age <= 19) {
            return 'U20';
        }
        if ($age <= 22) {
            return 'U23';
        }
        return 'Elite';
    }

    /**
     * Détermine le gabarit de formulaire adapté selon l'âge
     * - 14 ans           => 'u16_1' (U16 1ère année - Express + option vendredi)
     * - 15 ans           => 'u16_2' (U16 2ème année - Orientation de disciplines)
     * - 16 ans et plus   => 'u18_elite' (U18+ - Bilan complet en 4 volets)
     */
    public static function get_form_type(int $birth_year, string $fallback_category = 'U18'): string {
        $age = self::get_athlete_age($birth_year);
        
        if ($birth_year === 0) {
            return (strtoupper($fallback_category) === 'U16') ? 'u16_1' : 'u18_elite';
        }

        if ($age <= 14) {
            return 'u16_1';
        }
        if ($age === 15) {
            return 'u16_2';
        }
        return 'u18_elite';
    }

    /**
     * Retourne le libellé officiel de la catégorie d'âge Swiss Athletics
     */
    public static function get_category_label(int $birth_year, string $stored_category = ''): string {
        $age = self::get_athlete_age($birth_year);
        
        if ($birth_year === 0) {
            return $stored_category !== '' ? $stored_category : 'Athlète';
        }
        if ($age <= 14) {
            return 'U16 (1ère année)';
        }
        if ($age === 15) {
            return 'U16 (2ème année)';
        }
        if ($age <= 17) {
            return 'U18';
        }
        if ($age <= 19) {
            return 'U20';
        }
        if ($age <= 22) {
            return 'U23';
        }
        return 'Actif';
    }

    /**
     * Classe une discipline saisie librement dans sa famille physiologique
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
     * Récupère le nom en français d'une discipline à partir de son identifiant
     */
    public static function get_discipline_label(?string $slug): string {
        if ($slug === null || $slug === '' || $slug === 'none') {
            return 'Aucune';
        }
        return self::DISCIPLINES[$slug] ?? (self::FRIDAY_DISCIPLINES_U16[$slug] ?? $slug);
    }

    /**
     * Récupère le nom en français d'un jour
     */
    public static function get_day_label(?string $slug): string {
        if ($slug === null || $slug === '') {
            return '';
        }
        return self::DAYS_FR[$slug] ?? ucfirst($slug);
    }
}
