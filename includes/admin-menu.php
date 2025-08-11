<?php
// includes/admin-menu.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ajoute les pages d'administration pour l'extension.
 */
function gem_prox_qrqc_add_admin_menu() {
    add_menu_page(
        'Gemini QRQC',
        'Gemini QRQC',
        'manage_options',
        'gem-prox-qrqc',
        'gem_prox_qrqc_settings_page',
        'dashicons-chart-bar',
        80
    );
    add_submenu_page(
        'gem-prox-qrqc',
        'Paramètres Gemini QRQC',
        'Paramètres',
        'manage_options',
        'gem-prox-qrqc',
        'gem_prox_qrqc_settings_page'
    );
    add_submenu_page(
        'gem-prox-qrqc',
        'Liste des Rapports QRQC',
        'Rapports',
        'manage_options',
        'gem-prox-qrqc-reports',
        'gem_prox_qrqc_reports_list_page'
    );
}
add_action('admin_menu', 'gem_prox_qrqc_add_admin_menu');

/**
 * Affiche la page de paramètres de l'extension.
 */
function gem_prox_qrqc_settings_page() {
    ?>
    <div class="wrap">
        <h1>Paramètres de l'extension Gemini QRQC</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('gem_prox_qrqc_settings_group');
            do_settings_sections('gem-prox-qrqc');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/**
 * Enregistre les paramètres de l'extension.
 */
function gem_prox_qrqc_register_settings() {
    register_setting('gem_prox_qrqc_settings_group', 'gem_prox_qrqc_api_key');
    add_settings_section(
        'gem_prox_qrqc_main_section',
        'Clé API Gemini',
        'gem_prox_qrqc_main_section_callback',
        'gem-prox-qrqc'
    );
    add_settings_field(
        'gem_prox_qrqc_api_key',
        'Clé API Gemini',
        'gem_prox_qrqc_api_key_callback',
        'gem-prox-qrqc',
        'gem_prox_qrqc_main_section'
    );
}
add_action('admin_init', 'gem_prox_qrqc_register_settings');

function gem_prox_qrqc_main_section_callback() {
    echo '<p>Veuillez entrer votre clé API Gemini. Elle sera stockée de manière sécurisée dans la base de données.</p>';
}

function gem_prox_qrqc_api_key_callback() {
    $api_key = get_option('gem_prox_qrqc_api_key');
    echo '<input type="text" id="gem_prox_qrqc_api_key" name="gem_prox_qrqc_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
}
