<?php
// includes/admin-functions.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Crée la table pour stocker les rapports.
 */
function gem_prox_qrqc_create_reports_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gem_qrqc_reports';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        problem_statement text NOT NULL,
        file_name varchar(255) NOT NULL,
        report_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Crée la table pour stocker les statistiques.
 */
function gem_prox_qrqc_create_stats_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gem_qrqc_stats';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        stat_name varchar(100) NOT NULL,
        stat_value bigint(20) DEFAULT 0 NOT NULL,
        stat_date date NOT NULL,
        PRIMARY KEY (id),
        INDEX idx_stat_name_date (stat_name, stat_date)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Crée la table pour stocker les logs d'erreur.
 */
function gem_prox_qrqc_create_error_logs_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gem_qrqc_error_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        error_type varchar(50) NOT NULL,
        error_message text NOT NULL,
        context_data longtext,
        user_ip varchar(45),
        user_agent text,
        error_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY (id),
        INDEX idx_error_type_date (error_type, error_date)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Supprime toutes les tables et données de l'extension.
 */
function gem_prox_qrqc_delete_all_data() {
    global $wpdb;
    
    // Supprimer les tables
    $tables = array(
        $wpdb->prefix . 'gem_qrqc_reports',
        $wpdb->prefix . 'gem_qrqc_stats',
        $wpdb->prefix . 'gem_qrqc_error_logs'
    );
    
    foreach ($tables as $table_name) {
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }
    
    // Supprimer les options
    delete_option('gem_prox_qrqc_api_key');
    delete_option('gem_prox_qrqc_admin_email');
    
    // Supprimer le dossier des rapports
    $upload_dir = wp_upload_dir();
    $reports_dir = $upload_dir['basedir'] . '/gem_qrqc_reports';
    if (is_dir($reports_dir)) {
        gem_prox_qrqc_delete_directory($reports_dir);
    }
    
    // Supprimer les tâches cron
    wp_clear_scheduled_hook('gem_prox_qrqc_cleanup_logs');
    wp_clear_scheduled_hook('gem_prox_qrqc_cleanup_stats');
}

/**
 * Supprime un répertoire et tout son contenu de manière récursive.
 */
function gem_prox_qrqc_delete_directory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        is_dir($path) ? gem_prox_qrqc_delete_directory($path) : unlink($path);
    }
    
    return rmdir($dir);
}

/**
 * Supprime seulement la table des rapports (ancienne fonction conservée pour compatibilité).
 */
function gem_prox_qrqc_delete_reports_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gem_qrqc_reports';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

/**
 * Crée le dossier pour stocker les fichiers PDF.
 */
function gem_prox_qrqc_create_reports_folder() {
    $upload_dir = wp_upload_dir();
    $dir_path = $upload_dir['basedir'] . '/gem_qrqc_reports';
    if (!is_dir($dir_path)) {
        wp_mkdir_p($dir_path);
        
        // Ajouter un fichier .htaccess pour sécuriser le dossier
        $htaccess_content = "# Protection du dossier des rapports QRQC\n";
        $htaccess_content .= "Options -Indexes\n";
        $htaccess_content .= "<Files *.pdf>\n";
        $htaccess_content .= "    Order allow,deny\n";
        $htaccess_content .= "    Allow from all\n";
        $htaccess_content .= "</Files>\n";
        
        file_put_contents($dir_path . '/.htaccess', $htaccess_content);
    }
}

/**
 * Affiche la liste des rapports dans l'administration.
 */
function gem_prox_qrqc_reports_list_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gem_qrqc_reports';
    
    // Pagination
    $per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    // Récupérer le total pour la pagination
    $total_reports = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $total_pages = ceil($total_reports / $per_page);
    
    // Récupérer les rapports avec pagination
    $reports = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name ORDER BY report_date DESC LIMIT %d OFFSET %d",
        $per_page, $offset
    ));

    $upload_dir = wp_upload_dir();
    $base_url = $upload_dir['baseurl'] . '/gem_qrqc_reports/';
    
    ?>
    <div class="wrap">
        <h1>📋 Liste des rapports QRQC</h1>
        
        <div class="notice notice-info">
            <p><strong>Total :</strong> <?php echo number_format($total_reports); ?> rapport(s) stocké(s)</p>
        </div>
        
        <?php if ($reports) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column" style="width: 80px;">ID</th>
                        <th scope="col" class="manage-column">Énoncé du Problème</th>
                        <th scope="col" class="manage-column" style="width: 150px;">Date & Heure</th>
                        <th scope="col" class="manage-column" style="width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report) : ?>
                        <tr>
                            <td><?php echo esc_html($report->id); ?></td>
                            <td>
                                <?php 
                                $problem = esc_html($report->problem_statement);
                                echo strlen($problem) > 100 ? substr($problem, 0, 100) . '...' : $problem;
                                ?>
                            </td>
                            <td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($report->report_date))); ?></td>
                            <td>
                                <a href="<?php echo esc_url($base_url . $report->file_name); ?>" 
                                   target="_blank" 
                                   class="button button-small">📄 Voir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        $page_links = paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo; Précédent',
                            'next_text' => 'Suivant &raquo;',
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        echo $page_links;
                        ?>
                    </div>
                </div>
            <?php endif; ?>
            
        <?php else : ?>
            <div class="notice notice-warning">
                <p>Aucun rapport sauvegardé pour le moment.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Affiche la page des logs d'erreur dans l'administration.
 */
function gem_prox_qrqc_error_logs_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gem_qrqc_error_logs';
    
    // Gestion de la suppression des logs
    if (isset($_POST['clear_logs']) && wp_verify_nonce($_POST['_wpnonce'], 'clear_error_logs')) {
        $wpdb->query("TRUNCATE TABLE $table_name");
        echo '<div class="notice notice-success"><p>Tous les logs d\'erreur ont été supprimés.</p></div>';
    }
    
    // Pagination
    $per_page = 50;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    // Filtrage par type d'erreur
    $error_type_filter = isset($_GET['error_type']) ? sanitize_text_field($_GET['error_type']) : '';
    $where_clause = $error_type_filter ? $wpdb->prepare("WHERE error_type = %s", $error_type_filter) : '';
    
    // Récupérer le total pour la pagination
    $total_errors = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where_clause");
    $total_pages = ceil($total_errors / $per_page);
    
    // Récupérer les erreurs avec pagination et filtrage
    $query = "SELECT * FROM $table_name $where_clause ORDER BY error_date DESC LIMIT %d OFFSET %d";
    $errors = $wpdb->get_results($wpdb->prepare($query, $per_page, $offset));
    
    // Récupérer les types d'erreur pour le filtre
    $error_types = $wpdb->get_col("SELECT DISTINCT error_type FROM $table_name ORDER BY error_type");
    
    ?>
    <div class="wrap">
        <h1>🚨 Logs d'Erreur QRQC</h1>
        
        <div class="notice notice-info">
            <p><strong>Total :</strong> <?php echo number_format($total_errors); ?> erreur(s) enregistrée(s)</p>
        </div>
        
        <!-- Filtres et actions -->
        <div style="margin: 20px 0; display: flex; gap: 20px; align-items: center;">
            <!-- Filtre par type d'erreur -->
            <form method="get" style="display: flex; gap: 10px; align-items: center;">
                <input type="hidden" name="page" value="gem-prox-qrqc-errors">
                <label for="error_type">Filtrer par type :</label>
                <select name="error_type" id="error_type">
                    <option value="">Tous les types</option>
                    <?php foreach ($error_types as $type) : ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected($error_type_filter, $type); ?>>
                            <?php echo esc_html(ucfirst($type)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" class="button" value="Filtrer">
            </form>
            
            <!-- Bouton pour vider les logs -->
            <form method="post" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer tous les logs d\'erreur ?');">
                <?php wp_nonce_field('clear_error_logs'); ?>
                <input type="submit" name="clear_logs" class="button button-secondary" value="🗑️ Vider les logs">
            </form>
        </div>
        
        <?php if ($errors) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" style="width: 80px;">ID</th>
                        <th scope="col" style="width: 100px;">Type</th>
                        <th scope="col">Message d'erreur</th>
                        <th scope="col" style="width: 120px;">IP</th>
                        <th scope="col" style="width: 150px;">Date</th>
                        <th scope="col" style="width: 100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($errors as $error) : ?>
                        <tr>
                            <td><?php echo esc_html($error->id); ?></td>
                            <td>
                                <span class="error-type-badge error-type-<?php echo esc_attr($error->error_type); ?>">
                                    <?php echo esc_html(ucfirst($error->error_type)); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $message = esc_html($error->error_message);
                                echo strlen($message) > 100 ? '<span title="' . esc_attr($message) . '">' . substr($message, 0, 100) . '...</span>' : $message;
                                ?>
                            </td>
                            <td><?php echo esc_html($error->user_ip ?: 'N/A'); ?></td>
                            <td><?php echo esc_html(date_i18n('d/m/Y H:i:s', strtotime($error->error_date))); ?></td>
                            <td>
                                <button type="button" class="button button-small view-details" 
                                        data-error-id="<?php echo esc_attr($error->id); ?>"
                                        data-context="<?php echo esc_attr($error->context_data); ?>"
                                        data-user-agent="<?php echo esc_attr($error->user_agent); ?>">
                                    🔍 Détails
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        $page_links = paginate_links(array(
                            'base' => add_query_arg(array('paged' => '%#%', 'error_type' => $error_type_filter)),
                            'format' => '',
                            'prev_text' => '&laquo; Précédent',
                            'next_text' => 'Suivant &raquo;',
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        echo $page_links;
                        ?>
                    </div>
                </div>
            <?php endif; ?>
            
        <?php else : ?>
            <div class="notice notice-success">
                <p>🎉 Aucune erreur enregistrée ! L'application fonctionne parfaitement.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal pour les détails d'erreur -->
    <div id="error-details-modal" style="display: none;">
        <div class="error-details-content">
            <h3>Détails de l'erreur</h3>
            <div id="error-details-body"></div>
            <button type="button" id="close-modal" class="button">Fermer</button>
        </div>
    </div>
    
    <style>
        .error-type-badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            color: white;
        }
        .error-type-api { background-color: #d63638; }
        .error-type-security { background-color: #d68910; }
        .error-type-storage { background-color: #8f5700; }
        .error-type-configuration { background-color: #7e57c2; }
        
        #error-details-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 10000;
        }
        
        .error-details-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 5px;
            max-width: 80%;
            max-height: 80%;
            overflow-y: auto;
        }
        
        .error-details-content h3 {
            margin-top: 0;
        }
        
        .error-details-content pre {
            background: #f1f1f1;
            padding: 10px;
            border-radius: 3px;
            overflow-x: auto;
            white-space: pre-wrap;
        }
    </style>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('error-details-modal');
            const modalBody = document.getElementById('error-details-body');
            const closeBtn = document.getElementById('close-modal');
            
            // Ouvrir le modal
            document.querySelectorAll('.view-details').forEach(button => {
                button.addEventListener('click', function() {
                    const errorId = this.dataset.errorId;
                    const context = this.dataset.context;
                    const userAgent = this.dataset.userAgent;
                    
                    let html = '<p><strong>ID de l\'erreur :</strong> ' + errorId + '</p>';
                    
                    if (userAgent) {
                        html += '<p><strong>User Agent :</strong></p><pre>' + userAgent + '</pre>';
                    }
                    
                    if (context && context !== 'null') {
                        try {
                            const contextData = JSON.parse(context);
                            html += '<p><strong>Données de contexte :</strong></p><pre>' + JSON.stringify(contextData, null, 2) + '</pre>';
                        } catch (e) {
                            html += '<p><strong>Données de contexte :</strong></p><pre>' + context + '</pre>';
                        }
                    }
                    
                    modalBody.innerHTML = html;
                    modal.style.display = 'block';
                });
            });
            
            // Fermer le modal
            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });
            
            // Fermer le modal en cliquant à l'extérieur
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });
    </script>
    <?php
}