<?php
// includes/error-handler.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enregistre une erreur dans la base de données et envoie un email à l'administrateur
 */
function gem_prox_qrqc_log_error($error_type, $error_message, $context_data = array()) {
    global $wpdb;
    
    // Enregistrer l'erreur en base de données
    $table_name = $wpdb->prefix . 'gem_qrqc_error_logs';
    $wpdb->insert(
        $table_name,
        array(
            'error_type' => sanitize_text_field($error_type),
            'error_message' => sanitize_textarea_field($error_message),
            'context_data' => json_encode($context_data),
            'user_ip' => gem_prox_qrqc_get_user_ip(),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'error_date' => current_time('mysql'),
        )
    );
    
    $error_id = $wpdb->insert_id;
    
    // Envoyer un email à l'administrateur
    gem_prox_qrqc_send_error_email($error_id, $error_type, $error_message, $context_data);
}

/**
 * Envoie un email d'alerte erreur à l'administrateur
 */
function gem_prox_qrqc_send_error_email($error_id, $error_type, $error_message, $context_data) {
    $admin_email = get_option('gem_prox_qrqc_admin_email', get_option('admin_email'));
    
    if (empty($admin_email)) {
        return; // Pas d'email configuré
    }
    
    $site_name = get_bloginfo('name');
    $site_url = get_bloginfo('url');
    $error_date = current_time('d/m/Y à H:i:s');
    
    $subject = "[{$site_name}] Erreur Application QRQC - {$error_type}";
    
    $message = "Une erreur s'est produite dans l'application QRQC :\n\n";
    $message .= "ID de l'erreur : {$error_id}\n";
    $message .= "Type d'erreur : {$error_type}\n";
    $message .= "Date et heure : {$error_date}\n";
    $message .= "Site : {$site_url}\n\n";
    $message .= "Message d'erreur :\n{$error_message}\n\n";
    
    if (!empty($context_data)) {
        $message .= "Contexte :\n";
        foreach ($context_data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_PRETTY_PRINT);
            }
            $message .= "- {$key} : {$value}\n";
        }
        $message .= "\n";
    }
    
    $message .= "IP utilisateur : " . gem_prox_qrqc_get_user_ip() . "\n";
    $message .= "User Agent : " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Non défini') . "\n\n";
    $message .= "Vous pouvez consulter tous les logs d'erreur dans l'administration WordPress : {$site_url}/wp-admin/admin.php?page=gem-prox-qrqc-errors\n\n";
    $message .= "Cordialement,\nSystème de monitoring QRQC";
    
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    
    wp_mail($admin_email, $subject, $message, $headers);
}

/**
 * Récupère l'adresse IP de l'utilisateur de manière sécurisée
 */
function gem_prox_qrqc_get_user_ip() {
    // Vérifier les en-têtes proxy courants
    $ip_keys = array(
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    );
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip)[0];
            }
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'Inconnue';
}

/**
 * Nettoie les anciens logs d'erreur (garde les 30 derniers jours)
 */
function gem_prox_qrqc_cleanup_old_error_logs() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'gem_qrqc_error_logs';
    $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
    
    $wpdb->query($wpdb->prepare(
        "DELETE FROM $table_name WHERE error_date < %s",
        $thirty_days_ago
    ));
}

/**
 * Planifie le nettoyage automatique des logs
 */
function gem_prox_qrqc_schedule_cleanup() {
    if (!wp_next_scheduled('gem_prox_qrqc_cleanup_logs')) {
        wp_schedule_event(time(), 'daily', 'gem_prox_qrqc_cleanup_logs');
    }
}
add_action('init', 'gem_prox_qrqc_schedule_cleanup');
add_action('gem_prox_qrqc_cleanup_logs', 'gem_prox_qrqc_cleanup_old_error_logs');