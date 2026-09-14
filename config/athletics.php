<?php
declare(strict_types=1);

/**
 * Catalogue et configuration athlétisme CA Sion & Swiss Athletics
 */
return [
    'disciplines' => [
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
    ],

    'friday_options_u16' => [
        'high_jump'        => 'Hauteur',
        'sprint'           => 'Sprint',
        'middle_distance'  => 'Demi-fond'
    ],

    'days_fr' => [
        'monday'           => 'Lundi',
        'tuesday'          => 'Mardi',
        'wednesday'        => 'Mercredi',
        'thursday'         => 'Jeudi',
        'friday'           => 'Vendredi',
        'saturday'         => 'Samedi',
        'saturday_morning' => 'Samedi matin',
        'sunday'           => 'Dimanche'
    ],

    'days_short' => [
        'monday'           => 'lu',
        'tuesday'          => 'ma',
        'wednesday'        => 'me',
        'thursday'         => 'je',
        'friday'           => 've',
        'saturday'         => 'sa',
        'saturday_morning' => 'sa',
        'sunday'           => 'di'
    ],

    'obstacles' => [
        'attendance'       => 'Mon assiduité / régularité',
        'stress'           => 'La peur de mal faire / le stress',
        'listening'        => 'Mon écoute des consignes',
        'fatigue_school'   => 'La fatigue ou l\'organisation avec l\'école',
        'other'            => 'Autre raison'
    ],

    'export_short_codes' => [
        'demi-fond' => 'DF',
        '800m'      => 'DF',
        '1500m'     => 'DF',
        '3000m'     => 'DF',
        'endurance' => 'End.',
        'sprint'    => 'Sprint',
        '100m'      => 'Sprint',
        '200m'      => 'Sprint',
        '400m'      => 'Sprint',
        'haies'     => 'Haies',
        'hurdles'   => 'Haies',
        'hauteur'   => 'Saut',
        'longueur'  => 'Saut',
        'triple'    => 'Saut',
        'perche'    => 'Perche',
        'saut'      => 'Saut',
        'poids'     => 'Lanc',
        'disque'    => 'Lanc',
        'javelot'   => 'Lanc',
        'marteau'   => 'Lanc',
        'lancer'    => 'Lanc',
        'force'     => 'Force',
        'muscu'     => 'Force',
        'combine'   => 'Comb.',
    ]
];
