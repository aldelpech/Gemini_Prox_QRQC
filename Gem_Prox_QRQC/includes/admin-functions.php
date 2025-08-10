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
 * Supprime la table des rapports.
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
    }
}

/**
 * Affiche la liste des rapports dans l'administration.
 */
function gem_prox_qrqc_reports_list_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gem_qrqc_reports';
    $reports = $wpdb->get_results("SELECT * FROM $table_name ORDER BY report_date DESC");

    $upload_dir = wp_upload_dir();
    $base_url = $upload_dir['baseurl'] . '/gem_qrqc_reports/';
    
    ?>
    <div class="wrap">
        <h1>Liste des rapports QRQC</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column">ID</th>
                    <th scope="col" class="manage-column">Énoncé du Problème</th>
                    <th scope="col" class="manage-column">Date & Heure</th>
                    <th scope="col" class="manage-column">Lien vers le rapport</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reports) : ?>
                    <?php foreach ($reports as $report) : ?>
                        <tr>
                            <td><?php echo esc_html($report->id); ?></td>
                            <td><?php echo esc_html($report->problem_statement); ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($report->report_date))); ?></td>
                            <td><a href="<?php echo esc_url($base_url . $report->file_name); ?>" target="_blank">Voir le rapport</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="4">Aucun rapport sauvegardé pour le moment.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
