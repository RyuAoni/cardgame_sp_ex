<?php
// register.php (ファイルアップロード対応版)

require_once 'db_connect.php'; 

header('Content-Type: application/json');

// --- 1. 入力データの受け取り (FormData対応) ---
// ReactからFormDataで送信されるため、$_POSTと$_FILESで受け取る
$user_name = $_POST['user_name'] ?? null;
$email = $_POST['email'] ?? null;
$password = $_POST['password'] ?? null;
$bio = $_POST['bio'] ?? null;
$icon_file = $_FILES['icon_image'] ?? null;

// 必須項目があるかチェック
if (!$user_name || !$email || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'ユーザー名、メールアドレス、パスワードは必須です。']);
    exit();
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);
$icon_image_url = null; // デフォルトはURLなし

// --- 2. アイコン画像のアップロード処理 ---
if ($icon_file && $icon_file['error'] === UPLOAD_ERR_OK) {
    // 保存先のディレクトリ
    $upload_dir = __DIR__ . '/../images/icons/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // 安全なファイル名を生成 (例: icon_1694436180.png)
    $extension = pathinfo($icon_file['name'], PATHINFO_EXTENSION);
    $file_name = 'icon_' . time() . '.' . $extension;
    $upload_file = $upload_dir . $file_name;

    // 一時ファイルから保存先へ移動
    if (move_uploaded_file($icon_file['tmp_name'], $upload_file)) {
        // 成功したら、公開URLを保存
        $icon_image_url = '/gps_art_app/images/icons/' . $file_name;
    }
}

try {
    // --- 3. データベースへの保存処理 ---
    $stmt = $pdo->prepare(
        "INSERT INTO users (user_name, email, password_hash, icon_image_url, bio) VALUES (:user_name, :email, :password_hash, :icon_image_url, :bio)"
    );
    $stmt->execute([
        ':user_name' => $user_name,
        ':email' => $email,
        ':password_hash' => $password_hash,
        ':icon_image_url' => $icon_image_url, // 保存した画像のURL or null
        ':bio' => $bio
    ]);

    // 成功したことをJSONで返す
    http_response_code(201); // Created
    echo json_encode(['status' => 'success', 'message' => 'ユーザー登録が完了しました。']);

} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'このメールアドレスは既に使用されています。']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'データベースエラー: ' . $e->getMessage()]);
    }
}

