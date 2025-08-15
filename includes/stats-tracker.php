<?php
// includes/stats-tracker.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Récupère les statistiques pour une période donnée
 */
function gem_prox_qrqc_get_stats($start_date = null, $end_date = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gem_qrqc_stats';
    
    if (!$start_date) {
        $start_date = date('Y-m-d', strtotime('-30 days'));
    }
    if (!$end_date) {
        $end_date = date('Y-m-d');
    }
    
    $stats = $wpdb->get_results($wpdb->prepare(
        "SELECT stat_name, SUM(stat_value) as total_value, stat_date 
         FROM $table_name 
         WHERE stat_date BETWEEN %s AND %s 
         GROUP BY stat_name, stat_date 
         ORDER BY stat_date DESC, stat_name",
        $start_date, $end_date
    ));
    
    return $stats;
}

/**
 * Récupère les statistiques agrégées par type avec valeurs par défaut
 */
function gem_prox_qrqc_get_aggregated_stats($period = 30) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gem_qrqc_stats';
    $start_date = date('Y-m-d', strtotime("-{$period} days"));
    
    $stats = $wpdb->get_results($wpdb->prepare(
        "SELECT stat_name, SUM(stat_value) as total_value 
         FROM $table_name 
         WHERE stat_date >= %s 
         GROUP BY stat_name 
         ORDER BY total_value DESC",
        $start_date
    ));
    
    // Initialiser avec des valeurs par défaut
    $result = array(
        'page_views' => 0,
        'conversations_started' => 0,
        'reports_generated' => 0,
        'reports_stored' => 0,
        'api_requests' => 0,
        'errors' => 0
    );
    
    // Remplir avec les valeurs réelles
    foreach ($stats as $stat) {
        $result[$stat->stat_name] = (int)$stat->total_value;
    }
    
    return $result;
}

/**
 * Récupère les statistiques quotidiennes pour les graphiques
 */
function gem_prox_qrqc_get_daily_stats($days = 30) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gem_qrqc_stats';
    $start_date = date('Y-m-d', strtotime("-{$days} days"));
    
    $stats = $wpdb->get_results($wpdb->prepare(
        "SELECT stat_date, stat_name, SUM(stat_value) as daily_value 
         FROM $table_name 
         WHERE stat_date >= %s 
         GROUP BY stat_date, stat_name 
         ORDER BY stat_date ASC",
        $start_date
    ));
    
    // Organiser les données par date avec valeurs par défaut
    $organized_stats = array();
    foreach ($stats as $stat) {
        if (!isset($organized_stats[$stat->stat_date])) {
            $organized_stats[$stat->stat_date] = array(
                'page_views' => 0,
                'conversations_started' => 0,
                'reports_generated' => 0,
                'reports_stored' => 0,
                'api_requests' => 0,
                'errors' => 0
            );
        }
        $organized_stats[$stat->stat_date][$stat->stat_name] = (int)$stat->daily_value;
    }
    
    return $organized_stats;
}

/**
 * Fonction sécurisée pour récupérer une valeur de statistique
 */
function gem_prox_qrqc_get_stat_value($stats_array, $key, $default = 0) {
    return isset($stats_array[$key]) ? (int)$stats_array[$key] : $default;
}

/**
 * Affiche la page des statistiques dans l'admin
 */
function gem_prox_qrqc_stats_page() {
    $aggregated_stats = gem_prox_qrqc_get_aggregated_stats(30);
    $daily_stats = gem_prox_qrqc_get_daily_stats(30);
    
    // Calculs de ratios et métriques avancées avec gestion sécurisée
    $total_sessions = gem_prox_qrqc_get_stat_value($aggregated_stats, 'page_views');
    $total_conversations = gem_prox_qrqc_get_stat_value($aggregated_stats, 'conversations_started');
    $total_reports = gem_prox_qrqc_get_stat_value($aggregated_stats, 'reports_generated');
    $total_errors = gem_prox_qrqc_get_stat_value($aggregated_stats, 'errors');
    $total_api_requests = gem_prox_qrqc_get_stat_value($aggregated_stats, 'api_requests');
    $total_stored = gem_prox_qrqc_get_stat_value($aggregated_stats, 'reports_stored');
    
    $conversion_rate = $total_sessions > 0 ? round(($total_conversations / $total_sessions) * 100, 2) : 0;
    $completion_rate = $total_conversations > 0 ? round(($total_reports / $total_conversations) * 100, 2) : 0;
    $error_rate = $total_api_requests > 0 ? round(($total_errors / $total_api_requests) * 100, 2) : 0;
    
    ?>
    <div class="wrap">
        <h1>📊 Statistiques de l'Application QRQC</h1>
        
        <div class="notice notice-info">
            <p><strong>Période :</strong> 30 derniers jours | <strong>Dernière mise à jour :</strong> <?php echo date('d/m/Y à H:i'); ?></p>
        </div>
        
        <!-- Métriques principales -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
            
            <div class="postbox">
                <div class="postbox-header"><h3>👥 Visiteurs</h3></div>
                <div class="inside" style="text-align: center; padding: 20px;">
                    <div style="font-size: 2em; font-weight: bold; color: #0073aa;"><?php echo number_format($total_sessions); ?></div>
                    <div>Pages vues</div>
                </div>
            </div>
            
            <div class="postbox">
                <div class="postbox-header"><h3>💬 Conversations</h3></div>
                <div class="inside" style="text-align: center; padding: 20px;">
                    <div style="font-size: 2em; font-weight: bold; color: #00a32a;"><?php echo number_format($total_conversations); ?></div>
                    <div>Analyses démarrées</div>
                </div>
            </div>
            
            <div class="postbox">
                <div class="postbox-header"><h3>📋 Rapports</h3></div>
                <div class="inside" style="text-align: center; padding: 20px;">
                    <div style="font-size: 2em; font-weight: bold; color: #8f5700;"><?php echo number_format($total_reports); ?></div>
                    <div>Rapports générés</div>
                </div>
            </div>
            
            <div class="postbox">
                <div class="postbox-header"><h3>⚠️ Erreurs</h3></div>
                <div class="inside" style="text-align: center; padding: 20px;">
                    <div style="font-size: 2em; font-weight: bold; color: #d63638;"><?php echo number_format($total_errors); ?></div>
                    <div>Erreurs détectées</div>
                </div>
            </div>
            
        </div>
        
        <!-- Taux de conversion -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
            
            <div class="postbox">
                <div class="postbox-header"><h3>📈 Taux de Conversion</h3></div>
                <div class="inside" style="padding: 20px;">
                    <div><strong>Visiteurs → Conversations :</strong> <?php echo $conversion_rate; ?>%</div>
                    <div style="margin-top: 10px;"><strong>Conversations → Rapports :</strong> <?php echo $completion_rate; ?>%</div>
                </div>
            </div>
            
            <div class="postbox">
                <div class="postbox-header"><h3>🔧 Fiabilité</h3></div>
                <div class="inside" style="padding: 20px;">
                    <div><strong>Taux d'erreur :</strong> <span style="color: <?php echo $error_rate > 5 ? '#d63638' : '#00a32a'; ?>;"><?php echo $error_rate; ?>%</span></div>
                    <div style="margin-top: 10px;"><strong>Requêtes API :</strong> <?php echo number_format($total_api_requests); ?></div>
                </div>
            </div>
            
            <div class="postbox">
                <div class="postbox-header"><h3>💾 Stockage</h3></div>
                <div class="inside" style="padding: 20px;">
                    <div><strong>Rapports stockés :</strong> <?php echo number_format($total_stored); ?></div>
                    <div style="margin-top: 10px;"><strong>Taux de consentement :</strong> <?php echo $total_reports > 0 ? round(($total_stored / $total_reports) * 100, 1) : 0; ?>%</div>
                </div>
            </div>
            
        </div>
        
        <!-- Détail des statistiques -->
        <div class="postbox">
            <div class="postbox-header"><h3>📊 Détail des Statistiques</h3></div>
            <div class="inside">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Métrique</th>
                            <th>Valeur (30 derniers jours)</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stat_descriptions = array(
                            'page_views' => 'Nombre de fois où la page contenant l\'application a été visitée',
                            'conversations_started' => 'Nombre d\'analyses QRQC démarrées',
                            'reports_generated' => 'Nombre de rapports PDF générés avec succès',
                            'reports_stored' => 'Nombre de rapports stockés sur le serveur (avec consentement)',
                            'api_requests' => 'Nombre total de requêtes envoyées à l\'API Gemini',
                            'errors' => 'Nombre total d\'erreurs techniques détectées'
                        );
                        
                        foreach ($stat_descriptions as $stat_name => $description) {
                            $value = gem_prox_qrqc_get_stat_value($aggregated_stats, $stat_name);
                            echo "<tr>";
                            echo "<td><strong>" . ucfirst(str_replace('_', ' ', $stat_name)) . "</strong></td>";
                            echo "<td>" . number_format($value) . "</td>";
                            echo "<td>" . $description . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Graphique simple des tendances -->
        <div class="postbox">
            <div class="postbox-header"><h3>📈 Tendances (7 derniers jours)</h3></div>
            <div class="inside" style="padding: 20px;">
                <?php
                $recent_stats = gem_prox_qrqc_get_daily_stats(7);
                if (!empty($recent_stats)) {
                    echo "<table class='wp-list-table widefat'>";
                    echo "<thead><tr><th>Date</th><th>Visiteurs</th><th>Conversations</th><th>Rapports</th><th>Erreurs</th></tr></thead>";
                    echo "<tbody>";
                    
                    foreach ($recent_stats as $date => $stats) {
                        echo "<tr>";
                        echo "<td>" . date('d/m/Y', strtotime($date)) . "</td>";
                        echo "<td>" . gem_prox_qrqc_get_stat_value($stats, 'page_views') . "</td>";
                        echo "<td>" . gem_prox_qrqc_get_stat_value($stats, 'conversations_started') . "</td>";
                        echo "<td>" . gem_prox_qrqc_get_stat_value($stats, 'reports_generated') . "</td>";
                        echo "<td>" . gem_prox_qrqc_get_stat_value($stats, 'errors') . "</td>";
                        echo "</tr>";
                    }
                    
                    echo "</tbody></table>";
                } else {
                    echo "<p>Aucune donnée disponible pour les 7 derniers jours.</p>";
                }
                ?>
            </div>
        </div>
        
        <!-- Actions rapides -->
        <div class="postbox">
            <div class="postbox-header"><h3>⚡ Actions Rapides</h3></div>
            <div class="inside" style="padding: 20px;">
                <p style="margin-bottom: 15px;">
                    <a href="<?php echo admin_url('admin.php?page=gem-prox-qrqc-errors'); ?>" class="button button-secondary">
                        🚨 Consulter les logs d'erreur
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=gem-prox-qrqc-reports'); ?>" class="button button-secondary">
                        📋 Voir les rapports stockés
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=gem-prox-qrqc'); ?>" class="button button-secondary">
                        ⚙️ Paramètres
                    </a>
                </p>
                
                <?php if ($total_errors > 0) : ?>
                    <div class="notice notice-warning inline">
                        <p><strong>⚠️ Attention :</strong> <?php echo $total_errors; ?> erreur(s) détectée(s) ces 30 derniers jours. <a href="<?php echo admin_url('admin.php?page=gem-prox-qrqc-errors'); ?>">Consulter les détails</a></p>
                    </div>
                <?php endif; ?>
                
                <?php if ($total_api_requests == 0 && $total_sessions > 0) : ?>
                    <div class="notice notice-error inline">
                        <p><strong>🔧 Problème de configuration :</strong> Des visiteurs accèdent à l'application mais aucune requête API n'est enregistrée. Vérifiez la configuration de la clé API.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <p style="margin-top: 30px; color: #666;">
            <em>Les statistiques sont mises à jour en temps réel. Les données sont conservées pendant 6 mois pour le suivi des tendances.</em>
        </p>
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
        .notice.inline {
            margin: 15px 0;
            padding: 12px;
        }
    </style>
    <?php
}

/**
 * Nettoie les anciennes statistiques (garde les 6 derniers mois)
 */
function gem_prox_qrqc_cleanup_old_stats() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gem_qrqc_stats';
    $six_months_ago = date('Y-m-d', strtotime('-6 months'));
    
    $wpdb->query($wpdb->prepare(
        "DELETE FROM $table_name WHERE stat_date < %s",
        $six_months_ago
    ));
}

/**
 * Planifie le nettoyage automatique des stats
 */
function gem_prox_qrqc_schedule_stats_cleanup() {
    if (!wp_next_scheduled('gem_prox_qrqc_cleanup_stats')) {
        wp_schedule_event(time(), 'weekly', 'gem_prox_qrqc_cleanup_stats');
    }
}
add_action('init', 'gem_prox_qrqc_schedule_stats_cleanup');
add_action('gem_prox_qrqc_cleanup_stats', 'gem_prox_qrqc_cleanup_old_stats');