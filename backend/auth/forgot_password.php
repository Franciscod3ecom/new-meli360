<?php
// backend/auth/forgot_password.php
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);

if (!$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'E-mail inválido']);
    exit;
}

try {
    $pdo = getDatabaseConnection();

    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM saas_users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmtUpdate = $pdo->prepare("UPDATE saas_users SET reset_token = :token, reset_expires_at = :expires WHERE id = :id");
        $stmtUpdate->execute([
            ':token' => $token,
            ':expires' => $expires,
            ':id' => $user['id']
        ]);

        // In a real production app, we would send an email here.
        // For development/debugging, we'll log it or return it.
        $resetLink = "https://d3ecom.com.br/meli360/reset-password?token=" . $token;

        // Log to file to simulate email sending for the user to pick up
        file_put_contents(
            __DIR__ . '/../email_sim.log',
            date('Y-m-d H:i:s') . " - Reset link for $email: $resetLink\n",
            FILE_APPEND
        );
    }

    // Always return success to prevent email enumeration
    echo json_encode([
        'success' => true,
        'message' => 'Se o e-mail existir em nossa base, um link de recuperação será enviado.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
