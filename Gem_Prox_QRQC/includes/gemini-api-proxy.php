<?php
// includes/gemini-api-proxy.php

if ( ! defined( 'ABSPATH' ) && ! defined( 'DOING_AJAX' ) ) {
    if ( $_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'OPTIONS' ) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès direct non autorisé.']);
        exit;
    }
}

// --- SÉCURITÉ : Récupérer la clé API depuis un fichier distinct ---
$api_key_file = 'api-key.php';
$api_key_path = GEM_PROX_QRQC_PLUGIN_DIR . 'includes/' . $api_key_file;

if (!file_exists($api_key_path) || !is_readable($api_key_path)) {
    http_response_code(500);
    echo json_encode(['error' => 'Le fichier de clé API est manquant ou non lisible.']);
    exit;
}

require_once($api_key_path);
if (empty(GEMINI_API_KEY)) {
    http_response_code(500);
    echo json_encode(['error' => 'La clé API est vide.']);
    exit;
}

// --- Sécurité CORS (Cross-Origin Resource Sharing) ---
$allowed_origin = get_site_url();
if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] === $allowed_origin) {
    header("Access-Control-Allow-Origin: " . $allowed_origin);
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, X-WP-Nonce");
} else {
    http_response_code(403);
    echo json_encode(['error' => 'Accès non autorisé.']);
    exit;
}

// Gérer les requêtes OPTIONS (pré-vol CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// S'assurer que la requête est une méthode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée. Seules les requêtes POST sont acceptées.']);
    exit;
}

// Récupérer le corps de la requête JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Requête JSON invalide.']);
    exit;
}

// Si la requête contient une demande de stockage de rapport
if (isset($data['action']) && $data['action'] === 'store_report') {
    // Vérifier le nonce de sécurité
    if (!isset($data['nonce']) || !wp_verify_nonce($data['nonce'], 'gem-prox-qrqc-nonce')) {
        http_response_code(403);
        echo json_encode(['error' => 'Nonce de sécurité invalide.']);
        exit;
    }

    $report_content = sanitize_text_field($data['report_content']);
    $file_name = sanitize_file_name($data['file_name']);

    $upload_dir = wp_upload_dir();
    $storage_path = $upload_dir['basedir'] . '/gem_qrqc_reports/' . $file_name;

    // Sauvegarde du fichier
    if (file_put_contents($storage_path, $report_content) !== false) {
        http_response_code(200);
        echo json_encode(['success' => 'Rapport stocké avec succès.']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la sauvegarde du rapport.']);
    }
    exit;
}


// Construire l'URL de l'API Gemini
$gemini_api_url = "[https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=](https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=)" . GEMINI_API_KEY;

// Préparer les options de la requête cURL
$ch = curl_init($gemini_api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

// Exécuter la requête cURL
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Gérer les erreurs cURL
if ($curl_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur cURL: ' . $curl_error]);
    exit;
}

http_response_code($http_code);
echo $response;
?>