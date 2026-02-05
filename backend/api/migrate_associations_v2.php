<?php
/**
 * Arquivo: migrate_associations_v2.php
 * Descrição: Versão robusta para vincular contas ML aos usuários do sistema.
 * Tenta usar o token atual, se falhar tenta o refresh, descobre o e-mail e vincula.
 */

header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

echo "=== RECUPERAÇÃO DE VÍNCULOS (V2 - COM REFRESH) ===\n\n";

function doRefresh($pdo, $refresh_token, $ml_user_id)
{
    $config = require __DIR__ . '/../config/config.php';
    $ch = curl_init("https://api.mercadolibre.com/oauth/token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'refresh_token',
        'client_id' => $config['MELI_APP_ID'],
        'client_secret' => $config['MELI_SECRET_KEY'],
        'refresh_token' => $refresh_token
    ]));
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    curl_close($ch);

    if (isset($data['access_token'])) {
        $sql = "UPDATE accounts SET access_token = :a, refresh_token = :r, expires_at = NOW() + INTERVAL '6 hours', updated_at = NOW() WHERE ml_user_id = :u";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':a' => $data['access_token'], ':r' => $data['refresh_token'], ':u' => $ml_user_id]);
        return $data['access_token'];
    }
    return false;
}

function getEmail($token)
{
    $ch = curl_init("https://api.mercadolibre.com/users/me");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200) {
        $data = json_decode($res, true);
        return $data['email'] ?? null;
    }
    return false;
}

try {
    $pdo = getDatabaseConnection();

    $stmt = $pdo->query("SELECT id, ml_user_id, nickname, access_token, refresh_token FROM accounts");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Processando " . count($accounts) . " contas...\n\n";

    $linkedCount = 0;

    foreach ($accounts as $acc) {
        echo "Conta: {$acc['nickname']} (ID ML: {$acc['ml_user_id']})\n";

        // 1. Tentar pegar email com token atual
        $email = getEmail($acc['access_token']);

        // 2. Se falhar, tentar Refresh
        if (!$email) {
            echo "  [INFO] Token atual falhou. Tentando Refresh...\n";
            $newToken = doRefresh($pdo, $acc['refresh_token'], $acc['ml_user_id']);
            if ($newToken) {
                echo "  [INFO] Token renovado! Tentando obter email novamente...\n";
                $email = getEmail($newToken);
            } else {
                echo "  [ERRO] Falha ao renovar token. Não é possível descobrir o e-mail.\n";
            }
        }

        // 3. Vincular se achou o email
        if ($email) {
            echo "  [SUCESSO] Email descoberto: $email\n";

            $stmtUser = $pdo->prepare("SELECT id FROM saas_users WHERE email = :email LIMIT 1");
            $stmtUser->execute([':email' => $email]);
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
                $linkedCount++;
            } else {
                echo "  [AVISO] Nenhum usuário do sistema com o e-mail '$email' encontrado.\n";
            }
        }
        echo "-------------------\n";
    }

    echo "\n=== FINALIZADO ===\n";
    echo "Total de contas processadas: " . count($accounts) . "\n";
    echo "Vínculos restabelecidos: $linkedCount\n";

} catch (Throwable $e) {
    echo "\n[ERRO FATAL] " . $e->getMessage() . "\n";
}
