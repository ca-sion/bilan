<?php
declare(strict_types=1);

namespace App\Domain;

use SettingsService;

/**
 * Calculateur pur des âges de compétition et des catégories Swiss Athletics
 */
class CategoryCalculator {

    /**
     * Calcule l'âge de l'athlète selon l'année de référence de compétition Swiss Athletics
     */
    public static function get_athlete_age(int $birth_year): int {
        if ($birth_year <= 0) {
            return 0;
        }
        $ref_year = SettingsService::get_reference_competition_year();
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
     * - <= 14 ans        => 'u16_1' (U16 1ère année - Express + option vendredi)
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
}
