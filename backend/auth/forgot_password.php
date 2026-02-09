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

$config = require __DIR__ . '/../config/config.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);

if (!$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'E-mail inválido']);
    exit;
}

/**
 * Envia email de redefinição de senha via Resend API.
 */
function sendResetEmail(string $to, string $resetLink, string $apiKey): bool {
    $htmlBody = '
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body style="margin:0;padding:0;background-color:#f5f5f5;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f5f5f5;padding:40px 20px;">
            <tr>
                <td align="center">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
                        <!-- Header -->
                        <tr>
                            <td style="background-color:#FACC15;padding:32px 40px;text-align:center;">
                                <h1 style="margin:0;font-size:28px;font-weight:700;color:#171717;letter-spacing:-0.5px;">
                                    Meli360
                                </h1>
                            </td>
                        </tr>
                        <!-- Content -->
                        <tr>
                            <td style="padding:40px;">
                                <h2 style="margin:0 0 8px;font-size:22px;font-weight:600;color:#171717;">
                                    Redefinir sua senha
                                </h2>
                                <p style="margin:0 0 24px;font-size:15px;color:#525252;line-height:1.6;">
                                    Recebemos uma solicitação para redefinir a senha da sua conta. Clique no botão abaixo para criar uma nova senha.
                                </p>
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td align="center" style="padding:8px 0 24px;">
                                            <a href="' . htmlspecialchars($resetLink) . '" style="display:inline-block;background-color:#FACC15;color:#171717;font-size:15px;font-weight:600;text-decoration:none;padding:14px 32px;border-radius:12px;">
                                                Redefinir Senha
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                                <p style="margin:0 0 16px;font-size:13px;color:#a3a3a3;line-height:1.5;">
                                    Este link expira em <strong>1 hora</strong>. Se você não solicitou a redefinição de senha, ignore este e-mail.
                                </p>
                                <hr style="border:none;border-top:1px solid #e5e5e5;margin:24px 0;">
                                <p style="margin:0;font-size:12px;color:#a3a3a3;line-height:1.5;">
                                    Se o botão não funcionar, copie e cole o link abaixo no seu navegador:
                                </p>
                                <p style="margin:8px 0 0;font-size:12px;color:#FACC15;word-break:break-all;">
                                    ' . htmlspecialchars($resetLink) . '
                                </p>
                            </td>
                        </tr>
                        <!-- Footer -->
                        <tr>
                            <td style="padding:24px 40px;background-color:#fafafa;text-align:center;border-top:1px solid #f0f0f0;">
                                <p style="margin:0;font-size:12px;color:#a3a3a3;">
                                    &copy; 2026 Meli360. Todos os direitos reservados.
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';

    $payload = json_encode([
        'from' => 'Meli360 <noreply@meliai.d3ecom.com.br>',
        'to' => [$to],
        'subject' => 'Redefinição de Senha - Meli360',
        'html' => $htmlBody
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode >= 200 && $httpCode < 300;
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

        $frontendUrl = $config['FRONTEND_URL'] ?? 'https://d3ecom.com.br/meli360';
        $resetLink = $frontendUrl . "/reset-password?token=" . $token;

        sendResetEmail($email, $resetLink, $config['RESEND_API_KEY']);
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
