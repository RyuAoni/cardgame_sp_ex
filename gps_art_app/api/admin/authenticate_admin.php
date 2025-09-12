<?php
// authenticate_admin.php
// 【管理者用】APIの門番。トークンを検証し、管理ユーザーかを確認する。

// 親ディレクトリにあるdb_connect.phpを読み込む
require_once __DIR__ . '/../db_connect.php';

// 認証失敗時にエラーを返して処理を終了する共通関数
function send_error_response($http_code, $message) {
    http_response_code($http_code);
    echo json_encode(['error' => $message]);
    exit();
}

// 1. リクエストのヘッダーから認証情報を取得
$headers = getallheaders();
$auth_header = $headers['Authorization'] ?? null;

if ($auth_header && preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
    $token = $matches[1];

    try {
        // 2. 受け取ったトークンからユーザーIDを取得
        $stmt = $pdo->prepare("SELECT user_id FROM auth_tokens WHERE token = :token");
        $stmt->execute([':token' => $token]);
        $auth_result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($auth_result) {
            $user_id = $auth_result['user_id'];
            
            // 3. 【重要】そのユーザーが管理者権限を持っているかチェックする
            $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE id = :user_id");
            $stmt->execute([':user_id' => $user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // is_adminフラグが1である（管理者である）ことを確認
            if ($user && $user['is_admin'] == 1) {
                // 認証成功！以降のファイルで使えるように管理者IDを保存
                $authenticated_admin_id = $user_id;
            } else {
                send_error_response(403, 'この操作を行う権限がありません。'); // 403 Forbidden
            }
        } else {
            send_error_response(401, '認証トークンが無効です。'); // 401 Unauthorized
        }
    } catch (PDOException $e) {
        send_error_response(500, 'データベースエラー: ' . $e->getMessage());
    }
} else {
    send_error_response(401, '認証ヘッダーが必要です。'); // 401 Unauthorized
}

