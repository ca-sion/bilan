<?php
declare(strict_types=1);

/**
 * Utilitaires généraux de formatage, dates et données
 */
class Helper {

    /**
     * Génère la synthèse concise de disponibilité pour la colonne Dispo
     * Exemples de sortie : "Tous (3-4x)", "Tous s. ma (3-4x)", "lu-me-je (2x)", "lu (1x)"
     */
    public static function format_availability_summary(array|string|null $days, ?string $sessions_count = null): string {
        if (is_string($days)) {
            $days = safe_json_decode($days, []);
        }
        if (!is_array($days) || empty($days)) {
            return '';
        }

        $all_possible_days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $clean_days = [];
        foreach ($days as $d) {
            $key = str_replace('_morning', '', (string)$d);
            if (in_array($key, $all_possible_days, true) && !in_array($key, $clean_days, true)) {
                $clean_days[] = $key;
            }
        }

        if (empty($clean_days)) {
            return '';
        }

        $short_map = [
            'monday'    => 'lu',
            'tuesday'   => 'ma',
            'wednesday' => 'me',
            'thursday'  => 'je',
            'friday'    => 've',
            'saturday'  => 'sa',
        ];

        $count_suffix = '';
        if ($sessions_count !== null && trim($sessions_count) !== '') {
            $clean_sess = trim($sessions_count);
            $count_suffix = " ({$clean_sess}x)";
        }

        $total_selected = count($clean_days);

        // Tous les jours sélectionnés (6/6)
        if ($total_selected >= 6) {
            return "Tous" . ($count_suffix !== '' ? $count_suffix : ' (5-6x)');
        }

        // 5 jours sur 6 (ex: Tous sauf mardi -> "Tous s. ma")
        if ($total_selected === 5) {
            $missing = array_diff($all_possible_days, $clean_days);
            $missing_day = reset($missing);
            $missing_short = $short_map[$missing_day] ?? '';
            return "Tous s. {$missing_short}" . $count_suffix;
        }

        // Jours spécifiques dans l'ordre de la semaine (ex: lu-me-ve)
        $ordered_shorts = [];
        foreach ($all_possible_days as $p) {
            if (in_array($p, $clean_days, true)) {
                $ordered_shorts[] = $short_map[$p];
            }
        }

        return implode('-', $ordered_shorts) . $count_suffix;
    }

    /**
     * Décodage JSON sécurisé
     */
    public static function json_decode(mixed $json, mixed $default = []): mixed {
        if ($json === null || $json === '') {
            return $default;
        }
        if (is_array($json)) {
            return $json;
        }
        try {
            $decoded = json_decode((string)$json, true);
            return (json_last_error() === JSON_ERROR_NONE && $decoded !== null) ? $decoded : $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    /**
     * Analyse et extrait les composants d'une date de naissance
     * Supporte: YYYY-MM-DD, YYYY-MM-DD HH:MM:SS, DD.MM.YYYY, DD/MM/YYYY, DD-MM-YYYY, YYYY
     * @return array{birth_date: ?string, birth_year: int, pin: string}
     */
    public static function parse_birth_date(?string $raw): array {
        if ($raw === null || trim($raw) === '') {
            return ['birth_date' => null, 'birth_year' => 0, 'pin' => '0000'];
        }

        $raw = trim($raw);

        // Format ISO : 2011-11-07 ou 2011-11-07 00:00:00 ou 2011-11-07T00:00:00
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $raw, $m)) {
            $year = (int)$m[1];
            $month = (int)$m[2];
            $day = (int)$m[3];
            $birth_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $pin = sprintf('%02d%02d', $day, $month); // JJMM
            return ['birth_date' => $birth_date, 'birth_year' => $year, 'pin' => $pin];
        }

        // Format européen / suisse : 07.11.2011 ou 07/11/2011 ou 07-11-2011 (avec heure optionnelle)
        if (preg_match('/^(\d{1,2})[\.\/\-](\d{1,2})[\.\/\-](\d{4})/', $raw, $m)) {
            $day = (int)$m[1];
            $month = (int)$m[2];
            $year = (int)$m[3];
            $birth_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $pin = sprintf('%02d%02d', $day, $month); // JJMM
            return ['birth_date' => $birth_date, 'birth_year' => $year, 'pin' => $pin];
        }

        // Année seule : 2011
        if (preg_match('/^(\d{4})$/', $raw, $m)) {
            $year = (int)$m[1];
            return ['birth_date' => null, 'birth_year' => $year, 'pin' => '0000'];
        }

        return ['birth_date' => null, 'birth_year' => 0, 'pin' => '0000'];
    }

    /**
     * Échappement HTML sécurisé
     */
    public static function e(?string $str): string {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}
