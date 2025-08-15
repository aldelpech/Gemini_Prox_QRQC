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
        'Statistiques QRQC',
        'Statistiques',
        'manage_options',
        'gem-prox-qrqc-stats',
        'gem_prox_qrqc_stats_page'
    );
    
    add_submenu_page(
        'gem-prox-qrqc',
        'Liste des Rapports QRQC',
        'Rapports',
        'manage_options',
        'gem-prox-qrqc-reports',
        'gem_prox_qrqc_reports_list_page'
    );
    
    add_submenu_page(
        'gem-prox-qrqc',
        'Logs d\'Erreur QRQC',
        'Logs d\'Erreur',
        'manage_options',
        'gem-prox-qrqc-errors',
        'gem_prox_qrqc_error_logs_page'
    );
}
add_action('admin_menu', 'gem_prox_qrqc_add_admin_menu');

/**
 * Affiche la page de paramètres de l'extension.
 */
function gem_prox_qrqc_settings_page() {
    // Traitement du formulaire
    if (isset($_POST['submit'])) {
        if (wp_verify_nonce($_POST['_wpnonce'], 'gem_prox_qrqc_settings')) {
            // Sauvegarder la clé API
            if (isset($_POST['gem_prox_qrqc_api_key'])) {
                update_option('gem_prox_qrqc_api_key', sanitize_text_field($_POST['gem_prox_qrqc_api_key']));
            }
            
            // Sauvegarder l'email administrateur
            if (isset($_POST['gem_prox_qrqc_admin_email'])) {
                $admin_email = sanitize_email($_POST['gem_prox_qrqc_admin_email']);
                if (is_email($admin_email)) {
                    update_option('gem_prox_qrqc_admin_email', $admin_email);
                } else {
                    echo '<div class="notice notice-error"><p>L\'adresse email saisie n\'est pas valide.</p></div>';
                }
            }
            
            echo '<div class="notice notice-success"><p>Paramètres sauvegardés avec succès !</p></div>';
        }
    }
    
    // Traitement des actions de maintenance
    if (isset($_POST['maintenance_action'])) {
        if (wp_verify_nonce($_POST['_wpnonce'], 'gem_prox_qrqc_maintenance')) {
            $action = sanitize_text_field($_POST['maintenance_action']);
            
            if ($action === 'activate') {
                $hours = isset($_POST['maintenance_hours']) ? intval($_POST['maintenance_hours']) : 24;
                $maintenance_until = time() + ($hours * 3600);
                
                update_option('gem_prox_qrqc_maintenance_mode', true);
                update_option('gem_prox_qrqc_maintenance_until', $maintenance_until);
                update_option('gem_prox_qrqc_maintenance_reason', 'manual');
                
                gem_prox_qrqc_log_error('maintenance', 'Mode maintenance activé manuellement', array(
                    'maintenance_until' => date('Y-m-d H:i:s', $maintenance_until),
                    'duration_hours' => $hours
                ));
                
                echo '<div class="notice notice-warning"><p>Mode maintenance activé pour ' . $hours . ' heure(s).</p></div>';
                
            } elseif ($action === 'deactivate') {
                update_option('gem_prox_qrqc_maintenance_mode', false);
                delete_option('gem_prox_qrqc_maintenance_until');
                delete_option('gem_prox_qrqc_maintenance_reason');
                
                gem_prox_qrqc_log_error('maintenance', 'Mode maintenance désactivé manuellement', array());
                
                echo '<div class="notice notice-success"><p>Mode maintenance désactivé.</p></div>';
            }
        }
    }
    
    $api_key = get_option('gem_prox_qrqc_api_key', '');
    $admin_email = get_option('gem_prox_qrqc_admin_email', get_option('admin_email'));
    $maintenance_mode = gem_prox_qrqc_is_maintenance_mode();
    $maintenance_until = get_option('gem_prox_qrqc_maintenance_until', 0);
    $maintenance_reason = get_option('gem_prox_qrqc_maintenance_reason', '');
    
    ?>
    <div class="wrap">
        <h1>⚙️ Paramètres de l'extension Gemini QRQC</h1>
        
        <div class="notice notice-info">
            <p><strong>Version :</strong> 1.2.2 | <strong>Statut :</strong> Application en développement actif</p>
        </div>
        
        <?php if ($maintenance_mode) : ?>
            <div class="notice notice-warning">
                <p><strong>🔧 MODE MAINTENANCE ACTIF</strong></p>
                <p>
                    <strong>Raison :</strong> 
                    <?php 
                    switch ($maintenance_reason) {
                        case 'quota_exceeded':
                            echo 'Quota API Gemini dépassé (activation automatique)';
                            break;
                        case 'manual':
                            echo 'Activation manuelle par l\'administrateur';
                            break;
                        default:
                            echo 'Maintenance technique';
                            break;
                    }
                    ?>
                </p>
                <?php if ($maintenance_until) : ?>
                    <p><strong>Fin prévue :</strong> <?php echo date('d/m/Y à H:i', $maintenance_until); ?></p>
                    <p><strong>Temps restant :</strong> <span id="maintenance-countdown"></span></p>
                <?php endif; ?>
                <p><em>Les utilisateurs voient une page de maintenance. Vous pouvez utiliser l'application normalement en tant qu'administrateur.</em></p>
            </div>
        <?php endif; ?>
        
        <!-- Configuration principale -->
        <form method="post" action="">
            <?php wp_nonce_field('gem_prox_qrqc_settings'); ?>
            
            <h2>Configuration principale</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="gem_prox_qrqc_api_key">Clé API Gemini</label>
                    </th>
                    <td>
                        <input type="password" 
                               id="gem_prox_qrqc_api_key" 
                               name="gem_prox_qrqc_api_key" 
                               value="<?php echo esc_attr($api_key); ?>" 
                               class="regular-text" 
                               placeholder="Saisissez votre clé API Gemini">
                        <p class="description">
                            Cette clé est stockée de manière sécurisée dans la base de données. 
                            <a href="https://makersuite.google.com/app/apikey" target="_blank">Obtenir une clé API Gemini</a>
                        </p>
                        <?php if (empty($api_key)) : ?>
                            <p class="description" style="color: #d63638;">
                                ⚠️ <strong>Attention :</strong> L'application ne fonctionnera pas sans clé API.
                            </p>
                        <?php else : ?>
                            <p class="description" style="color: #00a32a;">
                                ✅ Clé API configurée
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="gem_prox_qrqc_admin_email">Email Administrateur</label>
                    </th>
                    <td>
                        <input type="email" 
                               id="gem_prox_qrqc_admin_email" 
                               name="gem_prox_qrqc_admin_email" 
                               value="<?php echo esc_attr($admin_email); ?>" 
                               class="regular-text" 
                               placeholder="admin@monsite.com">
                        <p class="description">
                            Adresse email qui recevra les notifications d'erreur et de maintenance. 
                            Par défaut, l'email administrateur de WordPress est utilisé.
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button('Sauvegarder les paramètres'); ?>
        </form>
        
        <!-- Gestion du mode maintenance -->
        <h2>🔧 Gestion du mode maintenance</h2>
        
        <div class="postbox">
            <div class="postbox-header"><h3>Mode maintenance</h3></div>
            <div class="inside" style="padding: 20px;">
                <p><strong>Fonction :</strong> Le mode maintenance affiche une page d'information aux utilisateurs en cas de problème technique ou de quota API dépassé.</p>
                
                <?php if ($maintenance_mode) : ?>
                    <div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #ffc107;">
                        <p><strong>État :</strong> Mode maintenance ACTIF</p>
                        <p><strong>Raison :</strong> 
                            <?php 
                            switch ($maintenance_reason) {
                                case 'quota_exceeded':
                                    echo 'Quota API Gemini dépassé';
                                    break;
                                case 'manual':
                                    echo 'Activation manuelle';
                                    break;
                                default:
                                    echo 'Maintenance technique';
                                    break;
                            }
                            ?>
                        </p>
                        <?php if ($maintenance_until) : ?>
                            <p><strong>Fin prévue :</strong> <?php echo date('d/m/Y à H:i', $maintenance_until); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <form method="post" action="" style="margin-top: 15px;">
                        <?php wp_nonce_field('gem_prox_qrqc_maintenance'); ?>
                        <input type="hidden" name="maintenance_action" value="deactivate">
                        <button type="submit" class="button button-secondary" 
                                onclick="return confirm('Êtes-vous sûr de vouloir désactiver le mode maintenance ?');">
                            ✅ Désactiver le mode maintenance
                        </button>
                    </form>
                    
                <?php else : ?>
                    <div style="background: #d1edff; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #0073aa;">
                        <p><strong>État :</strong> Application disponible pour tous les utilisateurs</p>
                        <p><strong>Fonctionnement :</strong></p>
                        <ul style="margin-left: 20px;">
                            <li>⚡ Activation automatique en cas d'erreur 429 (quota API dépassé)</li>
                            <li>🕐 Désactivation automatique à 1h du matin le lendemain</li>
                            <li>✋ Activation manuelle possible ci-dessous</li>
                            <li>👤 Les administrateurs ont toujours accès à l'application</li>
                        </ul>
                    </div>
                    
                    <form method="post" action="" style="margin-top: 15px;">
                        <?php wp_nonce_field('gem_prox_qrqc_maintenance'); ?>
                        <input type="hidden" name="maintenance_action" value="activate">
                        <p>
                            <label for="maintenance_hours">Durée de la maintenance :</label>
                            <select name="maintenance_hours" id="maintenance_hours">
                                <option value="1">1 heure</option>
                                <option value="2">2 heures</option>
                                <option value="6">6 heures</option>
                                <option value="12">12 heures</option>
                                <option value="24" selected>24 heures</option>
                                <option value="48">48 heures</option>
                            </select>
                        </p>
                        <button type="submit" class="button button-secondary">
                            🔧 Activer le mode maintenance manuellement
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Section d'information -->
        <div style="margin-top: 40px;">
            <h2>📊 Informations sur l'Extension</h2>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                
                <div class="postbox">
                    <div class="postbox-header"><h3>🎯 Utilisation</h3></div>
                    <div class="inside" style="padding: 15px;">
                        <p><strong>Shortcode :</strong> <code>[gemini_qrqc_app]</code></p>
                        <p>Insérez ce shortcode dans n'importe quelle page ou article pour afficher l'application.</p>
                    </div>
                </div>
                
                <div class="postbox">
                    <div class="postbox-header"><h3>🔧 Support Technique</h3></div>
                    <div class="inside" style="padding: 15px;">
                        <p>En cas de problème :</p>
                        <ul>
                            <li>Consultez les <a href="<?php echo admin_url('admin.php?page=gem-prox-qrqc-errors'); ?>">logs d'erreur</a></li>
                            <li>Vérifiez les <a href="<?php echo admin_url('admin.php?page=gem-prox-qrqc-stats'); ?>">statistiques</a></li>
                            <li>Assurez-vous que la clé API est valide</li>
                        </ul>
                    </div>
                </div>
                
                <div class="postbox">
                    <div class="postbox-header"><h3>📈 Monitoring</h3></div>
                    <div class="inside" style="padding: 15px;">
                        <?php
                        $stats = gem_prox_qrqc_get_aggregated_stats(7); // 7 derniers jours
                        $total_errors = $stats['errors'] ?? 0;
                        $total_conversations = $stats['conversations_started'] ?? 0;
                        ?>
                        <p><strong>7 derniers jours :</strong></p>
                        <ul>
                            <li>Conversations : <?php echo number_format($total_conversations); ?></li>
                            <li>Erreurs : <span style="color: <?php echo $total_errors > 0 ? '#d63638' : '#00a32a'; ?>;">
                                <?php echo number_format($total_errors); ?></span></li>
                            <li>Mode maintenance : <span style="color: <?php echo $maintenance_mode ? '#d63638' : '#00a32a'; ?>;">
                                <?php echo $maintenance_mode ? 'ACTIF' : 'Inactif'; ?></span></li>
                        </ul>
                    </div>
                </div>
                
            </div>
        </div>
        
        <!-- Test de l'API -->
        <div style="margin-top: 30px;">
            <h2>🧪 Test de Connexion API</h2>
            <button type="button" id="test-api-btn" class="button button-secondary">Tester la connexion à l'API Gemini</button>
            <div id="api-test-result" style="margin-top: 15px;"></div>
        </div>
    </div>
    
    <style>
        .postbox {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .postbox-header {
            border-bottom: 1px solid #ccd0d4;
            padding: 12px;
            background: #f6f7f7;
        }
        .postbox-header h3 {
            margin: 0;
            font-size: 14px;
        }
        .inside ul {
            margin: 10px 0;
            padding-left: 20px;
        }
    </style>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Test de l'API
            const testBtn = document.getElementById('test-api-btn');
            const resultDiv = document.getElementById('api-test-result');
            
            testBtn.addEventListener('click', function() {
                this.disabled = true;
                this.textContent = 'Test en cours...';
                resultDiv.innerHTML = '';
                
                setTimeout(() => {
                    const apiKey = document.getElementById('gem_prox_qrqc_api_key').value;
                    
                    if (!apiKey) {
                        resultDiv.innerHTML = '<div class="notice notice-error inline"><p>❌ Aucune clé API configurée.</p></div>';
                    } else {
                        resultDiv.innerHTML = '<div class="notice notice-success inline"><p>✅ Clé API présente. Test complet disponible prochainement.</p></div>';
                    }
                    
                    testBtn.disabled = false;
                    testBtn.textContent = 'Tester la connexion à l\'API Gemini';
                }, 2000);
            });
            
            // Compte à rebours pour la maintenance
            <?php if ($maintenance_mode && $maintenance_until) : ?>
            function updateMaintenanceCountdown() {
                const maintenanceUntil = <?php echo $maintenance_until * 1000; ?>;
                const now = new Date().getTime();
                const timeLeft = maintenanceUntil - now;
                
                const countdownElement = document.getElementById('maintenance-countdown');
                if (!countdownElement) return;
                
                if (timeLeft <= 0) {
                    countdownElement.textContent = 'Maintenance expirée - Actualisation...';
                    setTimeout(() => location.reload(), 2000);
                    return;
                }
                
                const hours = Math.floor(timeLeft / (1000 * 60 * 60));
                const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
                
                countdownElement.textContent = hours + 'h ' + minutes + 'min';
            }
            
            updateMaintenanceCountdown();
            setInterval(updateMaintenanceCountdown, 60000); // Mise à jour chaque minute
            <?php endif; ?>
        });
    </script>
    <?php
}

/**
 * Enregistre les paramètres de l'extension (fonction legacy conservée pour compatibilité).
 */
function gem_prox_qrqc_register_settings() {
    register_setting('gem_prox_qrqc_settings_group', 'gem_prox_qrqc_api_key');
    register_setting('gem_prox_qrqc_settings_group', 'gem_prox_qrqc_admin_email');
    
    add_settings_section(
        'gem_prox_qrqc_main_section',
        'Configuration principale',
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
    
    add_settings_field(
        'gem_prox_qrqc_admin_email',
        'Email Administrateur',
        'gem_prox_qrqc_admin_email_callback',
        'gem-prox-qrqc',
        'gem_prox_qrqc_main_section'
    );
}
add_action('admin_init', 'gem_prox_qrqc_register_settings');

function gem_prox_qrqc_main_section_callback() {
    echo '<p>Configuration de l\'extension Gemini QRQC.</p>';
}

function gem_prox_qrqc_api_key_callback() {
    $api_key = get_option('gem_prox_qrqc_api_key');
    echo '<input type="password" id="gem_prox_qrqc_api_key" name="gem_prox_qrqc_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
}

function gem_prox_qrqc_admin_email_callback() {
    $admin_email = get_option('gem_prox_qrqc_admin_email', get_option('admin_email'));
    echo '<input type="email" id="gem_prox_qrqc_admin_email" name="gem_prox_qrqc_admin_email" value="' . esc_attr($admin_email) . '" class="regular-text">';
}