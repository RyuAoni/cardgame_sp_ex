<?php
// login.php (データベース連携版)

// データベース接続ファイルを読み込み、$pdo変数が使えるようにする
require_once 'db_connect.php'; 

header('Content-Type: application/json');

// --- 入力データの受け取り ---
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// 必須項目があるかチェック
if (!isset($data['email']) || !isset($data['password'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'メールアドレスとパスワードは必須です。']);
    exit();
}

$email = $data['email'];
$password = $data['password'];


try {
    // --- ここからが本物のデータベース処理 ---

    // 1. 入力されたメールアドレスを持つユーザーをデータベースから探す
    $stmt = $pdo->prepare("SELECT id, user_name, password_hash FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. パスワードを検証する
    // ユーザーが見つかり、かつ入力されたパスワードがデータベースのハッシュ値と一致するかを検証
    if ($user && password_verify($password, $user['password_hash'])) {
    
    // --- ここからが追加部分 ---

    // 1. 安全でランダムなトークンを生成
    $token = bin2hex(random_bytes(32));

    // 2. 生成したトークンをデータベースに保存
    $stmt = $pdo->prepare("INSERT INTO auth_tokens (user_id, token) VALUES (:user_id, :token)");
    $stmt->execute([
        ':user_id' => $user['id'],
        ':token' => $token
    ]);

    // --- ここまでが追加部分 ---


    // ログイン成功
    echo json_encode([
        'status' => 'success',
        'message' => 'ログインに成功しました。',
        'user' => [
            'user_id' => $user['id'],
            'user_name' => $user['user_name']
        ],
        'token' => $token // ★★★ 生成したトークンをレスポンスに追加！ ★★★
    ]);

} else {
        // ログイン失敗 (メールアドレスが存在しない、またはパスワードが間違っている)
        http_response_code(401); // Unauthorized
        echo json_encode(['error' => 'メールアドレスまたはパスワードが正しくありません。']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'データベースエラー: ' . $e->getMessage()]);
}

