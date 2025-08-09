<?php
/**
 * Plugin Name: Gemini QRQC Problem Solver
 * Plugin URI:  https://parcours-performance.com/
 * Description: Une application interactive pour la résolution de problèmes QRQC, intégrant l'IA Gemini.
 * Version:     1.0.0
 * Author:      Votre Nom
 * Author URI:  https://parcours-performance.com/
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gem-prox-qrqc
 * Domain Path: /languages
 */

// Sécurité : Empêcher l'accès direct au fichier
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Définir le chemin de base du plugin
if ( ! defined( 'GEM_PROX_QRQC_PLUGIN_DIR' ) ) {
    define( 'GEM_PROX_QRQC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

/**
 * Crée le dossier de stockage des rapports QRQC si il n'existe pas.
 */
function gem_prox_qrqc_create_storage_folder() {
    $upload_dir = wp_upload_dir();
    $storage_path = $upload_dir['basedir'] . '/gem_qrqc_reports';
    if ( ! file_exists( $storage_path ) ) {
        wp_mkdir_p( $storage_path );
    }
}
register_activation_hook( __FILE__, 'gem_prox_qrqc_create_storage_folder' );

/**
 * Enfile les scripts et styles nécessaires à l'application QRQC.
 */
function gem_prox_qrqc_enqueue_scripts() {
    // Enfiler Tailwind CSS depuis CDN
    wp_enqueue_style( 'tailwind-css', 'https://cdn.tailwindcss.com' );
    // Enfiler le CSS personnalisé
    wp_enqueue_style( 'qrqc-app-styles', plugin_dir_url( __FILE__ ) . 'assets/css/styles.css', array(), '1.0.0' );

    // Enfiler jsPDF et jspdf-autotable depuis CDN
    wp_enqueue_script( 'jspdf', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', array(), '2.5.1', true );
    wp_enqueue_script( 'jspdf-autotable', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.16/jspdf.plugin.autotable.min.js', array('jspdf'), '3.5.16', true );

    // Enfiler le script de votre application SPA
    wp_enqueue_script( 'qrqc-app-js', plugin_dir_url( __FILE__ ) . 'assets/js/qrqc-app.js', array('jspdf', 'jspdf-autotable'), '1.0.0', true );

    // Passer des variables PHP au JavaScript
    wp_localize_script( 'qrqc-app-js', 'geminiProxConfig', array(
        'proxy_url' => admin_url('admin-ajax.php'),
        'config_json_url' => plugin_dir_url( __FILE__ ) . 'assets/json/qrqc_config.json',
        'template_json_url' => plugin_dir_url( __FILE__ ) . 'assets/json/qrqc_report_template.json',
        'nonce' => wp_create_nonce('gem-prox-qrqc-nonce'),
    ));
}
add_action( 'wp_enqueue_scripts', 'gem_prox_qrqc_enqueue_scripts' );

/**
 * Enregistre un shortcode pour afficher l'application sur une page.
 */
function gem_prox_qrqc_app_shortcode() {
    ob_start();
    ?>
    <div class="gemini-qrqc-app-container">
        <header class="text-center mb-10">
            <h1 class="text-3xl md:text-4xl font-bold text-[#1a202c] mb-2">Résolution de Problème QRQC avec IA</h1>
            <p class="text-lg text-[#666]">Décrivez votre problème, et laissez l'IA vous guider vers l'analyse des causes racines et un plan d'action.</p>
        </header>

        <section id="problem-input-section" class="bg-white p-6 rounded-xl shadow-md mb-8">
            <h2 class="text-2xl font-bold mb-4 text-center text-[#1a202c]">Décrivez votre problème</h2>
            <p class="text-sm text-center text-red-500 mb-4">
                ⚠️ Attention : N'insérez aucune information confidentielle dans cette application.
            </p>
            <textarea id="problem-description" class="w-full p-3 border border-[#ccc] rounded-lg focus:ring-2 focus:ring-[#d72c4b] focus:border-transparent resize-y min-h-[120px]" placeholder="Ex: 'La machine X s'arrête fréquemment.'"></textarea>
            <div class="mt-4">
                <input type="checkbox" id="consent-store-report" class="mr-2">
                <label for="consent-store-report" class="text-sm text-[#666]">
                    Je consens à ce que ce rapport, une fois anonymisé, soit stocké sur le site pour des usages futurs (ex: formation).
                </label>
            </div>
            <button id="start-analysis-btn" class="mt-4 w-full bg-[#d72c4b] hover:bg-[#a9223e] text-white font-bold py-3 px-6 rounded-lg transition duration-300 ease-in-out">
                Démarrer l'analyse
            </button>
        </section>

        <section id="analysis-section" class="hidden bg-white p-6 rounded-xl shadow-md mb-8">
            <h2 class="text-2xl font-bold mb-4 text-center text-[#1a202c]">Analyse du Problème (QRQC)</h2>
            <div id="chat-log" class="h-96 overflow-y-auto border border-[#ccc] rounded-lg p-4 mb-4 flex flex-col space-y-3">
            </div>
            <div id="loading-indicator" class="hidden text-center text-[#999] mb-4">
                <span class="loading-dots">.</span><span class="loading-dots">.</span><span class="loading-dots">.</span> L'IA réfléchit...
            </div>
            <div class="flex space-x-3">
                <input type="text" id="user-response-input" class="flex-grow p-3 border border-[#ccc] rounded-lg focus:ring-2 focus:ring-[#d72c4b] focus:border-transparent" placeholder="Votre réponse...">
                <button id="send-response-btn" class="bg-[#d72c4b] hover:bg-[#a9223e] text-white font-bold py-3 px-6 rounded-lg transition duration-300 ease-in-out">
                    Envoyer
                </button>
            </div>
            <button id="generate-report-btn" class="mt-4 w-full bg-[#239e9a] hover:bg-[#1a7f7b] text-white font-bold py-3 px-6 rounded-lg transition duration-300 ease-in-out opacity-50 cursor-not-allowed" disabled>
                Générer le rapport PDF
            </button>
            <div id="pdf-progress-bar-container" class="hidden">
                <div id="pdf-progress-bar"></div>
            </div>
        </section>

        <section id="report-section" class="hidden bg-white p-6 rounded-xl shadow-md mb-8 text-center">
            <h2 class="text-2xl font-bold mb-4 text-[#1a202c]">Rapport QRQC Généré !</h2>
            <p class="text-lg mb-6 text-[#666]">Votre rapport d'analyse de problème est prêt.</p>
            <a id="download-report-link" href="#" class="inline-block bg-[#239e9a] hover:bg-[#1a7f7b] text-white font-bold py-3 px-6 rounded-lg transition duration-300 ease-in-out">
                Télécharger le PDF
            </a>
        </section>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'gemini_qrqc_app', 'gem_prox_qrqc_app_shortcode' );

// Le contenu du proxy a été déplacé ici pour une meilleure organisation
/**
 * Gère les requêtes du proxy via l'API AJAX de WordPress.
 *
 * @return void
 */
function gem_prox_qrqc_handle_proxy_request() {
    // Sécurité : Vérifier le nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'gem-prox-qrqc-nonce' ) ) {
        wp_send_json_error( 'Nonce de sécurité invalide.', 403 );
    }

    // Sécurité : Vérifier le type d'action
    $action = isset( $_POST['action'] ) ? sanitize_text_field( $_POST['action'] ) : '';
    if ( $action !== 'gemini_proxy_request' ) {
        wp_send_json_error( 'Action non autorisée.', 403 );
    }

    // Récupérer les données JSON qui ont été encodées en base64
    $data_b64 = isset( $_POST['payload'] ) ? base64_decode( $_POST['payload'] ) : null;
    $data     = json_decode( $data_b64, true );

    if ( json_last_error() !== JSON_ERROR_NONE ) {
        wp_send_json_error( 'Requête JSON invalide.', 400 );
    }

    // Récupérer la clé API depuis un fichier distinct
    $api_key_file = 'api-key.php';
    $api_key_path = GEM_PROX_QRQC_PLUGIN_DIR . 'includes/' . $api_key_file;

    if ( ! file_exists( $api_key_path ) || ! is_readable( $api_key_path ) ) {
        wp_send_json_error( array( 'error' => 'Le fichier de clé API est manquant ou non lisible.' ), 500 );
    }

    require_once( $api_key_path );
    if ( empty( GEMINI_API_KEY ) ) {
        wp_send_json_error( array( 'error' => 'La clé API est vide.' ), 500 );
    }

    // Construire l'URL de l'API Gemini
    $gemini_api_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . GEMINI_API_KEY;

    // Préparer les options de la requête cURL
    $ch = curl_init( $gemini_api_url );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_POST, true );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $data ) );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    // Exécuter la requête cURL
    $response = curl_exec( $ch );
    $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    $curl_error = curl_error( $ch );
    curl_close( $ch );

    // Gérer les erreurs cURL
    if ( $curl_error ) {
        wp_send_json_error( array( 'error' => 'Erreur cURL: ' . $curl_error ), 500 );
    }

    // Renvoyer la réponse de l'API Gemini au client
    $response_data = json_decode( $response, true );
    if ( $response_data ) {
        wp_send_json_success( $response_data );
    } else {
        wp_send_json_error( array( 'error' => 'Réponse API invalide.' ), $http_code );
    }
}
add_action( 'wp_ajax_gemini_proxy_request', 'gem_prox_qrqc_handle_proxy_request' );
add_action( 'wp_ajax_nopriv_gemini_proxy_request', 'gem_prox_qrqc_handle_proxy_request' );
