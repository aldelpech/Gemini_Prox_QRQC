<?php
/**
 * Plugin Name: Gemini QRQC Problem Solver
 * Plugin URI:  https://parcours-performance.com/
 * Description: Une application interactive pour la résolution de problèmes QRQC, intégrant l'IA Gemini.
 * Version:     1.0.0
 * Author:      Anne-Laure D
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
            <h1 class="text-4xl md:text-5xl font-bold mb-4">Résolution de Problème QRQC avec IA</h1>
            <p class="text-lg text-gray-600">Décrivez votre problème industriel et laissez l'IA vous guider dans une analyse structurée selon la méthodologie QRQC pour identifier les causes racines et établir un plan d'action efficace.</p>
        </header>

        <!-- Section de saisie du problème -->
        <section id="problem-input-section" class="qrqc-section">
            <h2 class="text-2xl font-bold mb-6 text-center" style="color: var(--color-black);">Décrivez votre problème</h2>
            
            <div class="alert-warning mb-6">
                ⚠️ <strong>Attention :</strong> N'insérez aucune information confidentielle ou sensible dans cette application.
            </div>
            
            <textarea 
                id="problem-description" 
                placeholder="Exemple : 'Le disjoncteur saute lorsque j'allume la machine de production X' ou 'Retards fréquents de livraison sur la ligne de montage'"
                aria-label="Description du problème"
            ></textarea>
            
            <button id="start-analysis-btn" class="btn-primary mt-6">
                🚀 Démarrer l'analyse QRQC
            </button>
        </section>

        <!-- Section d'analyse (cachée initialement) -->
        <section id="analysis-section" class="qrqc-section hidden">
            <h2 class="text-2xl font-bold mb-4 text-center" style="color: var(--color-black);">Analyse du Problème (QRQC)</h2>
            
            <!-- Indicateur de progression -->
            <div id="progress-indicator" class="progress-indicator mb-4"></div>
            
            <!-- Zone de chat -->
            <div id="chat-log" aria-live="polite" aria-label="Conversation avec l'IA"></div>
            
            <!-- Indicateur de chargement (caché par défaut) -->
            <div id="loading-indicator" class="hidden text-center mb-4" style="color: var(--color-secondary);">
                <span class="loading-dots">
                    <span></span><span></span><span></span>
                </span>
                L'IA analyse votre réponse...
            </div>
            
            <!-- Zone de réponse utilisateur (cachée initialement) -->
            <div id="response-area" class="response-area hidden">
                <input 
                    type="text" 
                    id="user-response-input" 
                    placeholder="Tapez votre réponse ici..."
                    aria-label="Votre réponse"
                />
                <button id="send-response-btn" class="btn-send">
                    📤 Envoyer ma réponse
                </button>
            </div>
            
            <!-- Case de consentement et bouton génération (cachés initialement) -->
            <div id="consent-container" class="consent-container hidden">
                <input type="checkbox" id="consent-store-report" />
                <label for="consent-store-report">
                    Je consens à ce que ce rapport, une fois <strong>anonymisé</strong>, soit stocké sur le site pour des usages futurs (formation, amélioration de l'IA).
                </label>
            </div>
            
            <button id="generate-report-btn" class="btn-secondary mt-4 hidden">
                📋 Générer le rapport PDF
            </button>
            
            <!-- Barre de progression PDF (cachée par défaut) -->
            <div id="pdf-progress-bar-container" class="hidden">
                <div id="pdf-progress-bar"></div>
            </div>
        </section>
        
        <!-- Message d'aide -->
        <div class="text-center mt-8 text-sm" style="color: var(--color-dark);">
            💡 <strong>Astuce :</strong> Soyez précis dans vos réponses pour obtenir une analyse plus pertinente. 
            L'IA vous posera des questions basées sur les méthodes QQOQPC, QCDSM et 5 Pourquoi.
        </div>
    </div>
    
    <style>
        /* Styles inline pour s'assurer de la compatibilité */
        .gemini-qrqc-app-container {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        .hidden {
            display: none !important;
        }
        
        /* Assurer que les variables CSS sont définies si le fichier CSS n'est pas chargé */
        .gemini-qrqc-app-container {
            --color-primary: #d72c4b;
            --color-secondary: #239e9a;
            --color-dark: #514e57;
            --color-light: #fefefe;
            --color-black: #550000;
            --color-accent: #ef665c;
            --color-primary-alpha: rgba(215, 44, 75, 0.3);
            --color-secondary-alpha: rgba(35, 158, 154, 0.3);
        }
    </style>
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

    // ✅ Récupérer les données JSON directement ou via base64 (compatibilité)
    $data = null;
    
    if ( isset( $_POST['payload_json'] ) ) {
        // Nouveau format : JSON direct
        $data = json_decode( stripslashes( $_POST['payload_json'] ), true );
    } elseif ( isset( $_POST['payload'] ) ) {
        // Ancien format : base64 (pour compatibilité)
        $data_b64 = base64_decode( $_POST['payload'] );
        if ( $data_b64 ) {
            $data = json_decode( $data_b64, true );
        }
    }

    if ( ! $data || json_last_error() !== JSON_ERROR_NONE ) {
        wp_send_json_error( 'Requête JSON invalide: ' . json_last_error_msg(), 400 );
    }

    // ✅ Validation de la structure des données
    if ( ! isset( $data['contents'] ) || ! is_array( $data['contents'] ) ) {
        wp_send_json_error( 'Structure de données invalide: contents manquant', 400 );
    }

    // Récupérer la clé API
    $api_key_file = 'api-key.php';
    $api_key_path = GEM_PROX_QRQC_PLUGIN_DIR . 'includes/' . $api_key_file;

    if ( ! file_exists( $api_key_path ) || ! is_readable( $api_key_path ) ) {
        wp_send_json_error( 'Le fichier de clé API est manquant ou non lisible.', 500 );
    }

    require_once( $api_key_path );
    if ( empty( GEMINI_API_KEY ) || GEMINI_API_KEY === 'VOTRE_CLE_API_GEMINI_ICI' ) {
        wp_send_json_error( 'La clé API n\'est pas configurée.', 500 );
    }

    // Construire l'URL de l'API Gemini
    $gemini_api_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key=" . GEMINI_API_KEY;

    // ✅ Préparer le payload pour l'API Gemini
    $json_payload = json_encode( $data );
    
    // Log pour debug (à supprimer en production)
    // error_log( 'Gemini API Request: ' . $json_payload );

    // Préparer les options de la requête cURL
    $ch = curl_init( $gemini_api_url );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_POST, true );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $json_payload );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );

    // Exécuter la requête cURL
    $response = curl_exec( $ch );
    $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    $curl_error = curl_error( $ch );
    curl_close( $ch );

    // Log de la réponse pour debug (à supprimer en production)
    error_log( 'Gemini API Response (' . $http_code . '): ' . $response );

    // Gérer les erreurs cURL
    if ( $curl_error ) {
        wp_send_json_error( 'Erreur cURL: ' . $curl_error, 500 );
    }

    // Vérifier le code de réponse HTTP
    if ( $http_code !== 200 ) {
        wp_send_json_error( 'Erreur API Gemini (HTTP ' . $http_code . '): ' . $response, $http_code );
    }

    // Décoder la réponse
    $response_data = json_decode( $response, true );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        wp_send_json_error( 'Réponse API invalide: ' . json_last_error_msg(), 500 );
    }

    // Vérifier la structure de la réponse
    if ( ! isset( $response_data['candidates'] ) ) {
        wp_send_json_error( 'Structure de réponse inattendue: ' . json_encode( $response_data ), 500 );
    }

    wp_send_json_success( $response_data );
}
add_action( 'wp_ajax_gemini_proxy_request', 'gem_prox_qrqc_handle_proxy_request' );
add_action( 'wp_ajax_nopriv_gemini_proxy_request', 'gem_prox_qrqc_handle_proxy_request' );

/**
 * Gère le stockage des rapports PDF.
 */
function gem_prox_qrqc_handle_store_report() {
    // Vérification de sécurité
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'gem-prox-qrqc-nonce' ) ) {
        wp_send_json_error( 'Nonce de sécurité invalide.', 403 );
    }

    // Vérifier que les données sont présentes
    if ( ! isset( $_POST['report_content'] ) || ! isset( $_POST['file_name'] ) ) {
        wp_send_json_error( 'Données manquantes pour le stockage.', 400 );
    }

    $report_content = sanitize_text_field( $_POST['report_content'] );
    $file_name = sanitize_file_name( $_POST['file_name'] );

    // Créer le dossier de stockage
    $upload_dir = wp_upload_dir();
    $storage_path = $upload_dir['basedir'] . '/gem_qrqc_reports';
    
    if ( ! file_exists( $storage_path ) ) {
        wp_mkdir_p( $storage_path );
    }

    // Sauvegarder le fichier
    $file_path = $storage_path . '/' . $file_name;
    $pdf_data = base64_decode( $report_content );
    
    if ( file_put_contents( $file_path, $pdf_data ) ) {
        wp_send_json_success( array( 'message' => 'Rapport sauvegardé avec succès.' ) );
    } else {
        wp_send_json_error( 'Erreur lors de la sauvegarde du rapport.', 500 );
    }
}
add_action( 'wp_ajax_store_report', 'gem_prox_qrqc_handle_store_report' );
add_action( 'wp_ajax_nopriv_store_report', 'gem_prox_qrqc_handle_store_report' );