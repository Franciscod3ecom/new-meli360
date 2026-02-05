<?php
/**
 * Arquivo: discover_account_emails.php
 * Descrição: Tenta encontrar o e-mail de cada conta do Mercado Livre usando o access_token.
 */

header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

echo "=== DESCOBERTA DE E-MAILS DAS CONTAS ML ===\n\n";

try {
    $pdo = getDatabaseConnection();

    $stmt = $pdo->query("SELECT id, ml_user_id, nickname, access_token FROM accounts");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Processando " . count($accounts) . " contas...\n\n";

    foreach ($accounts as $acc) {
        echo "Conta: {$acc['nickname']} (ID ML: {$acc['ml_user_id']})\n";

        $curl = curl_init("https://api.mercadolibre.com/users/me");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $acc['access_token']
        ]);

        $response = curl_exec($curl);
        $data = json_decode($response, true);
        curl_close($curl);

        if (isset($data['email'])) {
            echo "  [SUCESSO] Email encontrado: {$data['email']}\n";

            // Tentar vincular automaticamente
            $stmtUser = $pdo->prepare("SELECT id FROM saas_users WHERE email = :email LIMIT 1");
            $stmtUser->execute([':email' => $data['email']]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                echo "  [VINCULANDO] Usuário do sistema encontrado (ID: {$user['id']})\n";
                $stmtLink = $pdo->prepare("
                    INSERT INTO user_accounts (user_id, account_id) 
                    VALUES (:uid, :aid)
                    ON CONFLICT DO NOTHING
                ");
                $stmtLink->execute([
                    ':uid' => $user['id'],
                    ':aid' => $acc['id']
                ]);
                echo "  [OK] Vínculo restabelecido.\n";
            } else {
                echo "  [AVISO] Nenhum usuário do sistema com este e-mail encontrado.\n";
            }
        } else {
            echo "  [ERRO] Não foi possível obter o e-mail (Token expirado ou inválido).\n";
        }
        echo "-------------------\n";
    }

} catch (Throwable $e) {
    echo "\n[ERRO FATAL] " . $e->getMessage() . "\n";
}
