<?php
/**
 * contact.php
 * Traite le formulaire de contact du portfolio cybersécurité.
 * Valide, nettoie, protège (CSRF, rate limiting, anti-injection) et envoie
 * les données par email. Répond toujours en JSON.
 *
 * ⚠️ À CONFIGURER avant mise en prod :
 *   - $destinataire  : ton adresse email
 *   - Vérifie que le dossier php/storage/ratelimit/ est accessible en écriture
 *     par le serveur web (chmod 755, ou 775 selon l'hébergeur).
 *   - La fonction mail() nécessite un serveur mail configuré côté hébergeur
 *     (fonctionne généralement out-of-the-box sur un mutualisé type OVH/o2switch).
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// --- Autoriser uniquement les requêtes POST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée.']);
    exit;
}

// --- Vérification CSRF (double-submit cookie) ---
// Le JS dépose un token aléatoire dans un cookie ET dans un champ caché du
// formulaire. On vérifie que les deux correspondent : un site tiers qui
// forcerait une soumission ne peut pas lire/écrire le cookie du visiteur.
$cookieToken = $_COOKIE['csrf_token'] ?? '';
$formToken   = $_POST['csrf_token'] ?? '';

if ($cookieToken === '' || $formToken === '' || !hash_equals($cookieToken, $formToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Session invalide. Recharge la page et réessaie.']);
    exit;
}

// --- Anti-spam : honeypot ---
// Un champ caché que seuls les bots remplissent (les humains ne le voient pas).
if (!empty($_POST['website'])) {
    // On répond succès pour ne pas indiquer au bot que le piège a fonctionné.
    echo json_encode(['success' => true]);
    exit;
}

// --- Rate limiting basé sur l'IP (fichier, sans base de données) ---
// Limite : 5 envois maximum par fenêtre de 15 minutes, et 15 secondes
// minimum entre deux envois, par adresse IP.
function client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function check_rate_limit(string $ip): array {
    $dir = __DIR__ . '/storage/ratelimit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    // Si le dossier n'est pas accessible en écriture, on n'échoue pas la
    // requête pour autant (fail-open) : on log l'anomalie et on continue.
    if (!is_writable($dir)) {
        return ['allowed' => true, 'file' => null, 'data' => null];
    }

    $file = $dir . '/' . hash('sha256', $ip) . '.json';
    $now = time();
    $windowSeconds = 900;   // 15 minutes
    $maxPerWindow = 5;
    $minIntervalSeconds = 15;

    $data = ['count' => 0, 'window_start' => $now, 'last_submit' => 0];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $decoded = $raw ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    // Fenêtre expirée : on réinitialise le compteur
    if ($now - ($data['window_start'] ?? 0) > $windowSeconds) {
        $data = ['count' => 0, 'window_start' => $now, 'last_submit' => $data['last_submit'] ?? 0];
    }

    if (($now - ($data['last_submit'] ?? 0)) < $minIntervalSeconds) {
        return ['allowed' => false, 'file' => $file, 'data' => $data];
    }

    if (($data['count'] ?? 0) >= $maxPerWindow) {
        return ['allowed' => false, 'file' => $file, 'data' => $data];
    }

    return ['allowed' => true, 'file' => $file, 'data' => $data];
}

function record_submission(?string $file, array $data): void {
    if ($file === null) {
        return;
    }
    $data['count'] = ($data['count'] ?? 0) + 1;
    $data['last_submit'] = time();
    @file_put_contents($file, json_encode($data), LOCK_EX);
}

$ip = client_ip();
$rate = check_rate_limit($ip);

if (!$rate['allowed']) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Trop de tentatives. Merci de patienter quelques minutes avant de réessayer.',
    ]);
    exit;
}

// --- Nettoyage des champs ---
// Pour un envoi en texte brut, on ne veut PAS d'entités HTML (&amp; etc.)
// dans le corps du mail : on retire seulement les balises et les
// caractères de contrôle dangereux, sans encoder les caractères normaux.
function sanitize_plain(string $value, int $maxLength): string {
    $value = trim($value);
    $value = strip_tags($value);
    // Retire les caractères de contrôle (sauf retour à la ligne dans le message)
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    // mb_substr n'est utilisé que si l'extension mbstring est active (quasi
    // toujours le cas chez les hébergeurs) ; sinon on retombe sur substr().
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function safe_strlen(string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

$name    = isset($_POST['name']) ? sanitize_plain($_POST['name'], 100) : '';
$email   = isset($_POST['email']) ? trim(strip_tags((string) $_POST['email'])) : '';
$subject = isset($_POST['subject']) ? sanitize_plain($_POST['subject'], 150) : '';
$message = isset($_POST['message']) ? sanitize_plain($_POST['message'], 5000) : '';

// --- Validation ---
$errors = [];

if ($name === '') {
    $errors[] = 'name';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || safe_strlen($email) > 254) {
    $errors[] = 'email';
}
if ($subject === '') {
    $errors[] = 'subject';
}
if ($message === '') {
    $errors[] = 'message';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Certains champs sont invalides ou manquants.',
        'fields'  => $errors,
    ]);
    exit;
}

// --- Protection anti-injection d'en-têtes email ---
// Un attaquant pourrait tenter d'insérer "\r\nBcc: victime@..." dans un
// champ pour détourner le formulaire en relais de spam. On rejette tout
// caractère de contrôle restant dans les champs utilisés comme en-têtes.
if (preg_match('/[\r\n\x00]/', $name . $email . $subject)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Requête invalide.']);
    exit;
}

// --- Envoi de l'email ---
$destinataire = 'ton.email@example.com'; // 🔧 remplace par ta vraie adresse

$mailSubject = '[Portfolio Cyber] ' . $subject;

$mailBody = "Nouveau message depuis le portfolio\n\n"
          . "Nom     : {$name}\n"
          . "Email   : {$email}\n"
          . "IP      : {$ip}\n"
          . "Sujet   : {$subject}\n\n"
          . "Message :\n{$message}\n";

$headers   = [];
$headers[] = 'From: Portfolio <no-reply@tondomaine.com>'; // 🔧 à adapter à ton propre domaine
$headers[] = 'Reply-To: ' . $email;
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headers[] = 'X-Mailer: PHP/' . phpversion();

$sent = @mail($destinataire, $mailSubject, $mailBody, implode("\r\n", $headers));

// On n'enregistre la tentative dans le rate limiter qu'une fois la
// validation passée, qu'elle réussisse ou échoue à l'envoi.
record_submission($rate['file'], $rate['data']);

if ($sent) {
    echo json_encode(['success' => true, 'message' => 'Message envoyé avec succès.']);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => "L'envoi a échoué. Le serveur mail n'est peut-être pas configuré.",
    ]);
}
