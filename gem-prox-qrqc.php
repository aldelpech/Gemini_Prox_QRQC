<?php
/**
 * Plugin Name: Gemini QRQC Problem Solver
 * Plugin URI:  https://parcours-performance.com/
 * Description: Une application interactive pour la résolution de problèmes QRQC, intégrant l'IA Gemini.
 * Version:     1.3-1
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
 * Vérifie si l'application est en mode maintenance
 */
function gem_prox_qrqc_is_maintenance_mode() {
    $maintenance_mode = get_option('gem_prox_qrqc_maintenance_mode', false);
    $maintenance_until = get_option('gem_prox_qrqc_maintenance_until', 0);
    
    // Si pas en mode maintenance
    if (!$maintenance_mode) {
        return false;
    }
    
    // Si la maintenance est expirée, la désactiver automatiquement
    if ($maintenance_until && time() > $maintenance_until) {
        update_option('gem_prox_qrqc_maintenance_mode', false);
        delete_option('gem_prox_qrqc_maintenance_until');
        gem_prox_qrqc_log_error('maintenance', 'Mode maintenance désactivé automatiquement', array(
            'maintenance_until' => date('Y-m-d H:i:s', $maintenance_until),
            'current_time' => date('Y-m-d H:i:s')
        ));
        return false;
    }
    
    return true;
}

/**
 * Active le mode maintenance automatiquement
 */
function gem_prox_qrqc_activate_maintenance_mode($reason = 'quota_exceeded') {
    $now = time();
    
    // Calculer 1h du matin le lendemain
    $tomorrow_1am = strtotime('tomorrow 01:00:00');
    
    // Si on est déjà après 1h du matin aujourd'hui, programmer pour demain
    if (date('H') >= 1) {
        $maintenance_until = $tomorrow_1am;
    } else {
        // Si on est avant 1h du matin, programmer pour 1h du matin aujourd'hui
        $maintenance_until = strtotime('today 01:00:00');
    }
    
    update_option('gem_prox_qrqc_maintenance_mode', true);
    update_option('gem_prox_qrqc_maintenance_until', $maintenance_until);
    update_option('gem_prox_qrqc_maintenance_reason', $reason);
    
    gem_prox_qrqc_log_error('maintenance', 'Mode maintenance activé automatiquement', array(
        'reason' => $reason,
        'maintenance_until' => date('Y-m-d H:i:s', $maintenance_until),
        'activated_at' => date('Y-m-d H:i:s')
    ));
    
    // Envoyer un email à l'admin
    gem_prox_qrqc_send_maintenance_email($reason, $maintenance_until);
}

/**
 * Envoie un email d'information sur le mode maintenance
 */
function gem_prox_qrqc_send_maintenance_email($reason, $until_timestamp) {
    $admin_email = get_option('gem_prox_qrqc_admin_email', get_option('admin_email'));
    
    if (empty($admin_email)) {
        return;
    }
    
    $site_name = get_bloginfo('name');
    $site_url = get_bloginfo('url');
    $until_date = date('d/m/Y à H:i', $until_timestamp);
    
    $subject = "[{$site_name}] Application QRQC en maintenance automatique";
    
    $message = "L'application QRQC est passée en mode maintenance automatique :\n\n";
    $message .= "Site : {$site_url}\n";
    $message .= "Raison : ";
    
    switch ($reason) {
        case 'quota_exceeded':
            $message .= "Quota API Gemini dépassé (erreur 429)\n";
            break;
        case 'manual':
            $message .= "Activation manuelle par l'administrateur\n";
            break;
        default:
            $message .= "Raison technique : {$reason}\n";
            break;
    }
    
    $message .= "Fin prévue : {$until_date}\n\n";
    $message .= "L'application se remettra automatiquement en service à cette heure.\n";
    $message .= "En tant qu'administrateur, vous pouvez toujours accéder à l'application via l'administration WordPress.\n\n";
    $message .= "Administration : {$site_url}/wp-admin/admin.php?page=gem-prox-qrqc\n\n";
    $message .= "Cordialement,\nSystème de monitoring QRQC";
    
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    
    wp_mail($admin_email, $subject, $message, $headers);
}

/**
 * Fonctions d'activation et de désactivation du plugin.
 */
function gem_prox_qrqc_activate() {
    require_once GEM_PROX_QRQC_PLUGIN_DIR . 'includes/admin-functions.php';
    gem_prox_qrqc_create_reports_table();
    gem_prox_qrqc_create_stats_table();
    gem_prox_qrqc_create_error_logs_table();
    gem_prox_qrqc_create_reports_folder();
    
    // Programmer la vérification quotidienne du mode maintenance
    if (!wp_next_scheduled('gem_prox_qrqc_check_maintenance')) {
        wp_schedule_event(time(), 'hourly', 'gem_prox_qrqc_check_maintenance');
    }
}
register_activation_hook( __FILE__, 'gem_prox_qrqc_activate' );

function gem_prox_qrqc_deactivate() {
    add_action('admin_notices', 'gem_prox_qrqc_deactivation_notice');
}
register_deactivation_hook( __FILE__, 'gem_prox_qrqc_deactivate' );

/**
 * Vérification périodique du mode maintenance
 */
function gem_prox_qrqc_check_maintenance_mode() {
    gem_prox_qrqc_is_maintenance_mode(); // Cette fonction désactive automatiquement si expiré
}
add_action('gem_prox_qrqc_check_maintenance', 'gem_prox_qrqc_check_maintenance_mode');

/**
 * Notice de désactivation avec options de suppression des données
 */
function gem_prox_qrqc_deactivation_notice() {
    if (isset($_GET['gem_qrqc_cleanup'])) {
        $cleanup = sanitize_text_field($_GET['gem_qrqc_cleanup']);
        if ($cleanup === 'yes') {
            require_once GEM_PROX_QRQC_PLUGIN_DIR . 'includes/admin-functions.php';
            gem_prox_qrqc_delete_all_data();
            echo '<div class="notice notice-success"><p>Toutes les données de l\'extension QRQC ont été supprimées.</p></div>';
        } else {
            echo '<div class="notice notice-info"><p>Les données de l\'extension QRQC ont été conservées.</p></div>';
        }
        return;
    }
    
    $cleanup_url_yes = add_query_arg('gem_qrqc_cleanup', 'yes', admin_url('plugins.php'));
    $cleanup_url_no = add_query_arg('gem_qrqc_cleanup', 'no', admin_url('plugins.php'));
    
    echo '<div class="notice notice-warning">
        <p><strong>Extension Gemini QRQC désactivée</strong></p>
        <p>Voulez-vous supprimer toutes les données de l\'extension (rapports, statistiques, logs d\'erreur) ?</p>
        <p>
            <a href="' . esc_url($cleanup_url_yes) . '" class="button button-primary">Oui, supprimer toutes les données</a>
            <a href="' . esc_url($cleanup_url_no) . '" class="button">Non, conserver les données</a>
        </p>
    </div>';
}

/**
 * Enfile les scripts et styles nécessaires à l'application QRQC.
 */
function gem_prox_qrqc_enqueue_scripts() {
    global $post;
    if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'gemini_qrqc_app' ) ) {
        wp_enqueue_style( 'tailwind-css', 'https://cdn.tailwindcss.com' );
        wp_enqueue_style( 'qrqc-app-styles', plugin_dir_url( __FILE__ ) . 'assets/css/styles.css', array(), '1.2.2' );

        wp_enqueue_script( 'jspdf', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', array(), '2.5.1', true );
        wp_enqueue_script( 'jspdf-autotable', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.16/jspdf.plugin.autotable.min.js', array('jspdf'), '3.5.16', true );

        wp_enqueue_script( 'qrqc-app-js', plugin_dir_url( __FILE__ ) . 'assets/js/qrqc-app.js', array('jspdf', 'jspdf-autotable'), '1.2.2', true );

        wp_localize_script( 'qrqc-app-js', 'geminiProxConfig', array(
            'proxy_url' => admin_url('admin-ajax.php'),
            'config_json_url' => plugin_dir_url( __FILE__ ) . 'assets/json/qrqc_config.json',
            'template_json_url' => plugin_dir_url( __FILE__ ) . 'assets/json/qrqc_report_template.json',
            'nonce' => wp_create_nonce('gem-prox-qrqc-nonce'),
        ));
    }
}
add_action( 'wp_enqueue_scripts', 'gem_prox_qrqc_enqueue_scripts' );

/**
 * Enregistre un shortcode pour afficher l'application sur une page.
 */
function gem_prox_qrqc_app_shortcode() {
    // Vérifier si l'application est en maintenance (sauf pour les admins)
    if (gem_prox_qrqc_is_maintenance_mode() && !current_user_can('manage_options')) {
        return gem_prox_qrqc_maintenance_page();
    }
    
    // Incrémenter le compteur de pages vues
    gem_prox_qrqc_increment_stat('page_views');
    
    ob_start();
    ?>
    <div class="gemini-qrqc-app-container">
        <?php if (gem_prox_qrqc_is_maintenance_mode() && current_user_can('manage_options')) : ?>
            <div class="notice notice-warning" style="margin-bottom: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107;">
                <p><strong>🔧 Mode administrateur :</strong> L'application est en mode maintenance pour les utilisateurs normaux, mais vous pouvez l'utiliser en tant qu'administrateur.</p>
                <p><a href="<?php echo admin_url('admin.php?page=gem-prox-qrqc'); ?>">Gérer le mode maintenance</a></p>
            </div>
        <?php endif; ?>
        
        <header class="text-center mb-10">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">Résolution de Problème QRQC avec IA</h1>
            <p id="app-intro-text" class="text-lg text-gray-600">Décrivez votre problème industriel et laissez l'IA vous guider dans une analyse structurée selon la méthodologie QRQC pour identifier les causes racines et établir un plan d'action efficace.</p>
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
                <span id="loading-message">L'IA analyse votre réponse...</span>
            </div>
            
            <!-- Zone de réponse utilisateur -->
            <div id="response-area" class="response-area hidden">
                <textarea 
                    id="user-response-input" 
                    placeholder="Tapez votre réponse ici..."
                    aria-label="Votre réponse"
                    rows="1"
                ></textarea>
                <button id="send-response-btn" class="btn-send">
                    📤 Envoyer
                </button>
            </div>
            
            <!-- Boutons avec tooltips et organisation améliorée -->
            <div class="button-group">
                <div class="tooltip">
                    <button id="save-discussion-btn" class="btn-secondary-outline">
                        💾 Sauvegarder la discussion
                    </button>
                    <span class="tooltiptext">
                        Conservez la transcription de votre conversation pour reprendre l'analyse plus tard. 
                        Vous pourrez coller le contenu du fichier sauvegardé dans la zone de description pour continuer.
                    </span>
                </div>
                <button id="generate-report-btn" class="btn-secondary hidden">
                    📋 Générer le rapport PDF
                </button>
            </div>

            <!-- Case de consentement (cachée initialement) -->
            <div id="consent-container" class="consent-container hidden">
                <input type="checkbox" id="consent-store-report" />
                <label for="consent-store-report">
                    Je consens à ce que ce rapport, une fois <strong>anonymisé</strong>, soit stocké sur le site pour des usages futurs (formation, amélioration de l'IA).
                </label>
            </div>
            
            <!-- Barre de progression PDF (cachée par défaut) -->
            <div id="pdf-progress-bar-container" class="hidden">
                <div id="pdf-progress-bar"></div>
            </div>
        </section>
        
        <!-- Message de fin de rapport -->
        <section id="report-section" class="qrqc-section hidden">
            <h2 class="text-2xl font-bold mb-4 text-center" style="color: var(--color-black);">Rapport QRQC Généré !</h2>
            <p class="text-lg text-center" style="color: var(--color-dark);">Votre rapport d'analyse de problème est prêt. Vous pouvez le télécharger <a id="download-report-link" href="#">ici</a>.</p>
        </section>

        <!-- Message d'aide -->
        <div id="app-tip-text" class="text-center mt-8 text-sm" style="color: var(--color-dark);">
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
        
        .btn-secondary-outline {
            background-color: var(--color-light);
            color: var(--color-secondary);
            border: 2px solid var(--color-secondary);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: auto;
            min-width: 180px;
            max-width: 250px;
        }

        .btn-secondary-outline:hover:not(:disabled) {
            background-color: var(--color-secondary-alpha);
            box-shadow: 0 4px 15px var(--color-secondary-alpha);
        }
        
        .button-group {
            display: flex;
            gap: 1rem;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .button-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .button-group .btn-secondary-outline,
            .button-group .btn-secondary {
                width: 100%;
                max-width: none;
            }
        }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Affiche la page de maintenance
 */
function gem_prox_qrqc_maintenance_page() {
    $maintenance_until = get_option('gem_prox_qrqc_maintenance_until', 0);
    $maintenance_reason = get_option('gem_prox_qrqc_maintenance_reason', 'maintenance');
    
    $until_date = $maintenance_until ? date('d/m/Y', $maintenance_until) : 'bientôt';
    $until_time = $maintenance_until ? date('H:i', $maintenance_until) : '01:00';
    
    ob_start();
    ?>
    <div class="gemini-qrqc-maintenance-container">
        <div class="maintenance-content">
            <div class="maintenance-icon">🔧</div>
            <h1>Application en maintenance</h1>
            
            <?php if ($maintenance_reason === 'quota_exceeded') : ?>
                <p class="maintenance-reason">
                    Notre quota quotidien d'utilisation de l'intelligence artificielle a été atteint.
                </p>
                <p class="maintenance-message">
                    L'application sera automatiquement disponible demain à <strong><?php echo $until_time; ?></strong> 
                    (remise à zéro du quota).
                </p>
            <?php else : ?>
                <p class="maintenance-reason">
                    L'application est temporairement indisponible pour maintenance technique.
                </p>
                <p class="maintenance-message">
                    Retour en service prévu le <strong><?php echo $until_date; ?></strong> à <strong><?php echo $until_time; ?></strong>.
                </p>
            <?php endif; ?>
            
            <div class="maintenance-countdown" id="maintenance-countdown">
                <div class="countdown-item">
                    <span class="countdown-number" id="hours">--</span>
                    <span class="countdown-label">heures</span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-number" id="minutes">--</span>
                    <span class="countdown-label">minutes</span>
                </div>
            </div>
            
            <div class="maintenance-info">
                <h3>Que pouvez-vous faire en attendant ?</h3>
                <ul>
                    <li>📚 Préparez la description détaillée de votre problème</li>
                    <li>📋 Rassemblez les informations sur votre processus (qui, quoi, où, quand)</li>
                    <li>🔍 Réfléchissez aux causes potentielles et aux impacts</li>
                    <li>⏰ Revenez après <?php echo $until_time; ?> pour une analyse complète</li>
                </ul>
            </div>
            
            <div class="maintenance-contact">
                <p><strong>Besoin urgent d'aide ?</strong></p>
                <p>Contactez directement votre responsable qualité ou utilisez vos outils QRQC habituels.</p>
            </div>
        </div>
    </div>
    
    <style>
        .gemini-qrqc-maintenance-container {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
            text-align: center;
        }
        
        .maintenance-content {
            background: #fff;
            border-radius: 20px;
            padding: 60px 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            border: 3px solid #f0f0f0;
        }
        
        .maintenance-icon {
            font-size: 80px;
            margin-bottom: 30px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .maintenance-content h1 {
            font-size: 2.5em;
            color: #d72c4b;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .maintenance-reason {
            font-size: 1.2em;
            color: #514e57;
            margin-bottom: 15px;
            font-weight: 500;
        }
        
        .maintenance-message {
            font-size: 1.1em;
            color: #239e9a;
            margin-bottom: 40px;
            font-weight: 600;
        }
        
        .maintenance-countdown {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 50px;
            padding: 30px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            border: 2px solid #dee2e6;
        }
        
        .countdown-item {
            text-align: center;
        }
        
        .countdown-number {
            display: block;
            font-size: 3em;
            font-weight: bold;
            color: #d72c4b;
            line-height: 1;
        }
        
        .countdown-label {
            font-size: 0.9em;
            color: #6c757d;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .maintenance-info {
            background: #e8f5e8;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid #239e9a;
        }
        
        .maintenance-info h3 {
            color: #239e9a;
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        
        .maintenance-info ul {
            text-align: left;
            display: inline-block;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        
        .maintenance-info li {
            margin-bottom: 8px;
            font-size: 1.05em;
            color: #2d5a27;
        }
        
        .maintenance-contact {
            background: #fff3cd;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #ffc107;
        }
        
        .maintenance-contact p {
            margin-bottom: 10px;
            color: #856404;
        }
        
        @media (max-width: 768px) {
            .maintenance-content {
                padding: 40px 20px;
            }
            
            .maintenance-content h1 {
                font-size: 2em;
            }
            
            .maintenance-countdown {
                gap: 15px;
                padding: 20px;
            }
            
            .countdown-number {
                font-size: 2.5em;
            }
            
            .maintenance-icon {
                font-size: 60px;
            }
        }
    </style>
    
    <script>
        // Compte à rebours en temps réel
        function updateCountdown() {
            const maintenanceUntil = <?php echo $maintenance_until ? $maintenance_until * 1000 : 'null'; ?>;
            
            if (!maintenanceUntil) {
                document.getElementById('hours').textContent = '--';
                document.getElementById('minutes').textContent = '--';
                return;
            }
            
            const now = new Date().getTime();
            const timeLeft = maintenanceUntil - now;
            
            if (timeLeft <= 0) {
                // La maintenance est terminée, recharger la page
                location.reload();
                return;
            }
            
            const hours = Math.floor(timeLeft / (1000 * 60 * 60));
            const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
            
            document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
            document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
        }
        
        // Mettre à jour le compte à rebours toutes les minutes
        updateCountdown();
        setInterval(updateCountdown, 60000);
        
        // Vérifier toutes les 5 minutes si la maintenance est terminée
        setInterval(function() {
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    if (!html.includes('maintenance-container')) {
                        location.reload();
                    }
                })
                .catch(error => console.log('Vérification maintenance:', error));
        }, 300000); // 5 minutes
    </script>
    <?php
    return ob_get_clean();
}

add_shortcode( 'gemini_qrqc_app', 'gem_prox_qrqc_app_shortcode' );

// Inclusion des fichiers d'administration et de gestion des requêtes
require_once GEM_PROX_QRQC_PLUGIN_DIR . 'includes/admin-menu.php';
require_once GEM_PROX_QRQC_PLUGIN_DIR . 'includes/admin-functions.php';
require_once GEM_PROX_QRQC_PLUGIN_DIR . 'includes/error-handler.php';
require_once GEM_PROX_QRQC_PLUGIN_DIR . 'includes/stats-tracker.php';

/**
 * Gère les requêtes du proxy via l'API AJAX de WordPress.
 */
function gem_prox_qrqc_handle_proxy_request() {
    // Sécurité : Vérifier le nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'gem-prox-qrqc-nonce' ) ) {
        gem_prox_qrqc_log_error('security', 'Nonce de sécurité invalide', $_POST);
        wp_send_json_error( 'Une erreur de sécurité s\'est produite. Cette application est encore en développement. Veuillez recharger la page et réessayer. L\'administrateur a été informé.', 403 );
    }

    // Récupérer la clé API depuis la base de données
    $api_key = get_option('gem_prox_qrqc_api_key', '');
    if ( empty( $api_key ) ) {
        gem_prox_qrqc_log_error('configuration', 'Clé API manquante', array());
        wp_send_json_error( 'La configuration de l\'application est incomplète. Cette application est encore en développement. L\'administrateur a été informé.', 500 );
    }

    // Gérer la requête
    $action = isset( $_POST['action'] ) ? sanitize_text_field( $_POST['action'] ) : '';

    if ($action === 'gemini_proxy_request') {
        gem_prox_qrqc_increment_stat('api_requests');
        
        // Logique pour les requêtes Gemini
        $data = json_decode( stripslashes( $_POST['payload_json'] ), true );
        
        if ( ! $data || json_last_error() !== JSON_ERROR_NONE ) {
            gem_prox_qrqc_log_error('api', 'Requête JSON invalide: ' . json_last_error_msg(), $_POST);
            gem_prox_qrqc_increment_stat('errors');
            wp_send_json_error( 'Désolé, une erreur technique s\'est produite. Veuillez réessayer dans quelques secondes. Cette application est encore en développement et l\'administrateur a été informé.', 400 );
        }
    
        if ( ! isset( $data['contents'] ) || ! is_array( $data['contents'] ) ) {
            gem_prox_qrqc_log_error('api', 'Structure de données invalide: contents manquant', $data);
            gem_prox_qrqc_increment_stat('errors');
            wp_send_json_error( 'Désolé, une erreur technique s\'est produite. Veuillez réessayer dans quelques secondes. Cette application est encore en développement et l\'administrateur a été informé.', 400 );
        }

        $gemini_api_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $api_key;
        $json_payload = json_encode( $data );
        
        $ch = curl_init( $gemini_api_url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $json_payload );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );

        $response = curl_exec( $ch );
        $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $curl_error = curl_error( $ch );
        curl_close( $ch );

        if ( $curl_error ) {
            gem_prox_qrqc_log_error('api', 'Erreur cURL: ' . $curl_error, array('api_url' => $gemini_api_url));
            gem_prox_qrqc_increment_stat('errors');
            wp_send_json_error( 'Désolé, une erreur de connexion s\'est produite. Veuillez réessayer dans quelques secondes. Cette application est encore en développement et l\'administrateur a été informé.', 500 );
        }

        // Gestion spéciale de l'erreur 429 (quota dépassé)
        if ( $http_code === 429 ) {
            $response_data = json_decode( $response, true );
            
            // Vérifier si c'est bien une erreur de quota
            if (isset($response_data['error']['status']) && $response_data['error']['status'] === 'RESOURCE_EXHAUSTED') {
                // Activer le mode maintenance automatiquement
                gem_prox_qrqc_activate_maintenance_mode('quota_exceeded');
                
                gem_prox_qrqc_log_error('api', 'Quota API dépassé - Mode maintenance activé', array(
                    'http_code' => $http_code,
                    'response' => $response,
                    'maintenance_activated' => true
                ));
                gem_prox_qrqc_increment_stat('errors');
                
                wp_send_json_error( 'Le quota quotidien d\'utilisation de l\'IA a été atteint. L\'application sera automatiquement disponible demain à 1h du matin. L\'administrateur a été informé et la maintenance s\'activera automatiquement.', 429 );
            }
        }

        if ( $http_code !== 200 ) {
            gem_prox_qrqc_log_error('api', 'Erreur API Gemini (HTTP ' . $http_code . '): ' . $response, array('http_code' => $http_code));
            gem_prox_qrqc_increment_stat('errors');
            wp_send_json_error( 'Désolé, le service IA est temporairement indisponible. Veuillez réessayer dans quelques secondes. Cette application est encore en développement et l\'administrateur a été informé.', $http_code );
        }

        $response_data = json_decode( $response, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            gem_prox_qrqc_log_error('api', 'Réponse API invalide: ' . json_last_error_msg(), array('response' => $response));
            gem_prox_qrqc_increment_stat('errors');
            wp_send_json_error( 'Désolé, une erreur de traitement s\'est produite. Veuillez réessayer dans quelques secondes. Cette application est encore en développement et l\'administrateur a été informé.', 500 );
        }

        if ( ! isset( $response_data['candidates'] ) ) {
            gem_prox_qrqc_log_error('api', 'Structure de réponse inattendue', $response_data);
            gem_prox_qrqc_increment_stat('errors');
            wp_send_json_error( 'Désolé, une erreur de traitement s\'est produite. Veuillez réessayer dans quelques secondes. Cette application est encore en développement et l\'administrateur a été informé.', 500 );
        }

        // Incrémenter les statistiques de succès
        if (isset($data['generationConfig']['responseMimeType']) && $data['generationConfig']['responseMimeType'] === 'application/json') {
            gem_prox_qrqc_increment_stat('reports_generated');
        } else {
            gem_prox_qrqc_increment_stat('conversations_started');
        }

        wp_send_json_success( $response_data );

    } elseif ($action === 'store_report') {
        gem_prox_qrqc_increment_stat('reports_stored');
        
        // Logique pour le stockage du rapport
        if ( ! isset( $_POST['report_content'] ) || ! isset( $_POST['file_name'] ) || ! isset( $_POST['problem_statement'] ) ) {
            gem_prox_qrqc_log_error('storage', 'Données manquantes pour le stockage', $_POST);
            gem_prox_qrqc_increment_stat('errors');
            wp_send_json_error( 'Désolé, une erreur de sauvegarde s\'est produite. Votre rapport a tout de même été téléchargé. L\'administrateur a été informé.', 400 );
        }

        $report_content = sanitize_text_field( $_POST['report_content'] );
        $file_name = sanitize_file_name( $_POST['file_name'] );
        $problem_statement = sanitize_text_field( $_POST['problem_statement'] );

        $upload_dir = wp_upload_dir();
        $storage_path = $upload_dir['basedir'] . '/gem_qrqc_reports';
        
        if ( ! file_exists( $storage_path ) ) {
            wp_mkdir_p( $storage_path );
        }

        $file_path = $storage_path . '/' . $file_name;
        $pdf_data = base64_decode( $report_content );
        
        if ( file_put_contents( $file_path, $pdf_data ) ) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'gem_qrqc_reports';
            $result = $wpdb->insert(
                $table_name,
                array(
                    'problem_statement' => $problem_statement,
                    'file_name' => $file_name,
                    'report_date' => current_time('mysql'),
                )
            );
            
            if ($result === false) {
                gem_prox_qrqc_log_error('storage', 'Erreur BDD lors de la sauvegarde: ' . $wpdb->last_error, array('file_name' => $file_name));
                gem_prox_qrqc_increment_stat('errors');
                wp_send_json_error( 'Désolé, une erreur de sauvegarde s\'est produite. Votre rapport a tout de même été téléchargé. L\'administrateur a été informé.', 500 );
            }
            
            wp_send_json_success( array( 'message' => 'Rapport sauvegardé avec succès.' ) );
        } else {
            gem_prox_qrqc_log_error('storage', 'Erreur lors de l\'écriture du fichier PDF', array('file_path' => $file_path));
            gem_prox_qrqc_increment_stat('errors');
            wp_send_json_error( 'Désolé, une erreur de sauvegarde s\'est produite. Votre rapport a tout de même été téléchargé. L\'administrateur a été informé.', 500 );
        }
    } else {
        gem_prox_qrqc_log_error('security', 'Action non autorisée: ' . $action, $_POST);
        wp_send_json_error( 'Action non autorisée.', 403 );
    }
}
add_action( 'wp_ajax_gemini_proxy_request', 'gem_prox_qrqc_handle_proxy_request' );
add_action( 'wp_ajax_nopriv_gemini_proxy_request', 'gem_prox_qrqc_handle_proxy_request' );
add_action( 'wp_ajax_store_report', 'gem_prox_qrqc_handle_proxy_request' );
add_action( 'wp_ajax_nopriv_store_report', 'gem_prox_qrqc_handle_proxy_request' );

/**
 * Fonction utilitaire pour incrémenter les statistiques
 */
function gem_prox_qrqc_increment_stat($stat_name) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gem_qrqc_stats';
    $today = date('Y-m-d');
    
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE stat_name = %s AND stat_date = %s",
        $stat_name, $today
    ));
    
    if ($existing) {
        $wpdb->update(
            $table_name,
            array('stat_value' => $existing->stat_value + 1),
            array('id' => $existing->id)
        );
    } else {
        $wpdb->insert(
            $table_name,
            array(
                'stat_name' => $stat_name,
                'stat_value' => 1,
                'stat_date' => $today
            )
        );
    }
}