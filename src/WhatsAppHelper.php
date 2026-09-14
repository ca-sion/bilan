<?php
declare(strict_types=1);

/**
 * Générateur de compte-rendu WhatsApp et de liens directs
 */
class WhatsAppHelper {

    /**
     * Décodage JSON sécurisé interne
     */
    private static function parse_json(mixed $data): array {
        if (is_array($data)) {
            return $data;
        }
        if (empty($data) || !is_string($data)) {
            return [];
        }
        $decoded = json_decode($data, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Génère le texte de synthèse WhatsApp normalisé
     */
    public static function build_synthesis_text(array $athlete, array $interview): string {
        $club = function_exists('env') ? env('CLUB_NAME', 'CA SION') : 'CA SION';
        $full_name = trim(($athlete['first_name'] ?? '') . ' ' . ($athlete['last_name'] ?? ''));
        $birth_year = (int)($athlete['birth_year'] ?? 0);
        $category_label = CategoryHelper::get_category_label($birth_year, $athlete['category'] ?? '');

        // Date de validation ou date du jour
        $val_date_str = $interview['validated_at'] ?? date('Y-m-d H:i:s');
        $date_fr = date('d.m.Y', strtotime($val_date_str));

        // Décodage sécurisé des réponses et décisions
        $trainer_answers = self::parse_json($interview['trainer_answers'] ?? null);
        $decisions = self::parse_json($interview['decisions'] ?? null);
        $athlete_answers = self::parse_json($interview['athlete_answers'] ?? null);

        // Discipline prioritaire
        $d1_raw = $decisions['primary_discipline'] 
            ?? ($decisions['friday_discipline_approved'] 
            ?? ($decisions['approved_disciplines'][0] 
            ?? ($athlete_answers['chosen_discipline_1'] 
            ?? ($athlete_answers['friday_discipline'] 
            ?? 'Athlétisme général'))));

        // Discipline secondaire
        $d2_raw = $decisions['secondary_discipline'] 
            ?? ($decisions['approved_disciplines'][1] 
            ?? ($athlete_answers['chosen_discipline_2'] 
            ?? 'Aucune'));

        $d1 = CategoryHelper::get_discipline_label($d1_raw);
        $d2 = CategoryHelper::get_discipline_label($d2_raw);

        // Volume hebdomadaire
        $sessions = $decisions['approved_weekly_sessions'] 
            ?? ($athlete_answers['target_sessions_count'] 
            ?? '3');

        // Jours retenus
        $approved_days = $decisions['approved_training_days'] 
            ?? ($athlete_answers['available_days'] 
            ?? []);
            
        if (!is_array($approved_days)) {
            $approved_days = [$approved_days];
        }

        $days_short_map = [
            'monday'           => 'Lu',
            'tuesday'          => 'Ma',
            'wednesday'        => 'Me',
            'thursday'         => 'Je',
            'friday'           => 'Ve',
            'saturday'         => 'Sa',
            'saturday_morning' => 'Sa',
            'sunday'           => 'Di'
        ];

        $days_formatted_list = [];
        foreach ($approved_days as $day) {
            $day_clean = strtolower(trim((string)$day));
            if (isset($days_short_map[$day_clean])) {
                $days_formatted_list[] = $days_short_map[$day_clean];
            } elseif ($day_clean !== '') {
                $days_formatted_list[] = ucfirst($day_clean);
            }
        }
        $days_string = !empty($days_formatted_list) ? implode(', ', $days_formatted_list) : 'À convenir';

        // Objectif cible
        $target = $decisions['target_milestones'] 
            ?? ($athlete_answers['target_performance'] 
            ?? ($athlete_answers['target_competitions'] 
            ?? 'Progression technique et assiduité'));

        // Contrat moral
        $rule1 = $decisions['mandatory_rule_1'] 
            ?? ($athlete_answers['attitude_contract'] 
            ?? ($athlete_answers['commitment_1'] 
            ?? 'Présence régulière et ponctualité exemplaire'));
            
        $rule2 = $decisions['mandatory_rule_2'] 
            ?? ($athlete_answers['commitment_2'] 
            ?? 'Écoute active des consignes et respect du groupe');

        $text = "🔴 " . strtoupper($club) . " — BILAN ET PROJECTION DE SAISON ⚪\n";
        $text .= "Athlète : {$full_name} ({$category_label})\n";
        $text .= "Date d'entretien : {$date_fr}\n\n";
        $text .= "🎯 Projet sportif validé :\n";
        $text .= "- Discipline prioritaire : {$d1}\n";
        $text .= "- Discipline secondaire : {$d2}\n";
        $text .= "- Volume d'entraînement : {$sessions} séances / semaine\n";
        $text .= "- Jours retenus : {$days_string}\n";
        $text .= "- Objectif cible : {$target}\n\n";
        $text .= "🤝 Contrat moral (2 comportements non négociables) :\n";
        $text .= "1. {$rule1}\n";
        $text .= "2. {$rule2}\n\n";
        $text .= "✅ Statut : Entretien officiel validé d'un commun accord avec l'entraîneur.";

        return $text;
    }

    /**
     * Génère l'URL WhatsApp pour un athlète absent de la liste
     */
    public static function build_missing_athlete_whatsapp_url(): string {
        $phone_raw = function_exists('env') ? env('COACH_PHONE', '+41791234567') : '+41791234567';
        $phone_clean = preg_replace('/[^0-9]/', '', (string)$phone_raw);
        $message = "Bonjour coach, je ne trouve pas mon nom dans la liste du bilan de fin de saison. [Prénom] [Nom], né en [Année].";
        return "https://wa.me/{$phone_clean}?text=" . rawurlencode($message);
    }

    public static function generate_missing_athlete_link(): string {
        return self::build_missing_athlete_whatsapp_url();
    }
}
