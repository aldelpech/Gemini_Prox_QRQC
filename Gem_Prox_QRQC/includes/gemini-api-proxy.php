<?php
// includes/gemini-api-proxy.php

// Le script ne s'exécute que si c'est une requête AJAX
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
