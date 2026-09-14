<?php
declare(strict_types=1);

require_once __DIR__ . '/Domain/CategoryCalculator.php';
require_once __DIR__ . '/Domain/DisciplineRules.php';

use App\Domain\CategoryCalculator;
use App\Domain\DisciplineRules;

/**
 * Façade utilitaire pour l'athlétisme.
 * Toutes les données proviennent dynamiquement de config/athletics.php (Single Source of Truth).
 */
class CategoryHelper {

    public static function get_disciplines(): array {
        return (array)(athletics_config('disciplines') ?? []);
    }

    public static function get_friday_options(): array {
        return (array)(athletics_config('friday_options_u16') ?? []);
    }

    public static function get_days(): array {
        return (array)(athletics_config('days_fr') ?? []);
    }

    public static function get_obstacles(): array {
        return (array)(athletics_config('obstacles') ?? []);
    }

    public static function get_cadres(): array {
        return (array)(athletics_config('cadres') ?? []);
    }

    public static function get_cadre_label(string $key): string {
        $cadres = self::get_cadres();
        return $cadres[$key] ?? $key;
    }

    public static function get_athlete_age(int $birth_year): int {
        return CategoryCalculator::get_athlete_age($birth_year);
    }

    public static function calculate_category(int $birth_year): string {
        return CategoryCalculator::calculate_category($birth_year);
    }

    public static function get_form_type(int $birth_year, string $fallback_category = 'U18'): string {
        return CategoryCalculator::get_form_type($birth_year, $fallback_category);
    }

    public static function get_category_label(int $birth_year, string $stored_category = ''): string {
        return CategoryCalculator::get_category_label($birth_year, $stored_category);
    }

    public static function get_discipline_family(string $discipline): string {
        return DisciplineRules::get_discipline_family($discipline);
    }

    public static function check_disciplines_compatibility(?string $d1, ?string $d2): array {
        return DisciplineRules::check_disciplines_compatibility($d1, $d2);
    }

    public static function get_discipline_label(?string $slug): string {
        if ($slug === null || $slug === '' || $slug === 'none') {
            return 'Aucune';
        }
        $disciplines = self::get_disciplines();
        $friday_opts = self::get_friday_options();
        return $disciplines[$slug] ?? ($friday_opts[$slug] ?? $slug);
    }

    public static function get_day_label(?string $slug): string {
        if ($slug === null || $slug === '') {
            return '';
        }
        $days = self::get_days();
        return $days[$slug] ?? ucfirst($slug);
    }
}
