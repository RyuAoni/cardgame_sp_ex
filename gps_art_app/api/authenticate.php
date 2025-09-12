<?php
// authenticate.php
// APIの門番。トークンを検証し、認証されたユーザーIDを提供する。

require_once 'db_connect.php';

// 認証失敗時にエラーを返して処理を終了する共通関数
function unauthorized($message = '認証が必要です。') {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => $message]);
    exit();
}

// 1. リクエストのヘッダーから認証情報を取得
$headers = getallheaders();
$auth_header = $headers['Authorization'] ?? null;

// 2. ヘッダーが'Bearer <トークン>'の形式かチェック
if ($auth_header && preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
    $token = $matches[1];

    try {
        // 3. 受け取ったトークンがデータベースに存在するか検証
        $stmt = $pdo->prepare("SELECT user_id FROM auth_tokens WHERE token = :token");
        $stmt->execute([':token' => $token]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            // 4. 認証成功！リクエスト元のユーザーIDを変数に保存
            $authenticated_user_id = $result['user_id'];
        } else {
            // トークンが無効な場合
            unauthorized('認証トークンが無効です。');
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'データベースエラーが発生しました。']);
        exit();
    }
} else {
    // 認証ヘッダーがない、または形式が不正な場合
    unauthorized();
}
