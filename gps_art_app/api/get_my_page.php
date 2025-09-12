<?php
// get_my_page.php (データベース連携版)

require_once 'db_connect.php';
// ★★★ 門番を配置！ ★★★
// 認証に成功すると、$authenticated_user_id 変数が使えるようになる。
require_once 'authenticate.php';

header('Content-Type: application/json');

try {
    // 1. 認証されたユーザーの情報を取得する
    $stmt = $pdo->prepare("SELECT id as user_id, user_name, icon_image_url, bio FROM users WHERE id = :id");
    $stmt->execute([':id' => $authenticated_user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_info) {
        http_response_code(404);
        echo json_encode(['error' => 'ユーザーが見つかりません。']);
        exit();
    }

    // 2. そのユーザーが作成した全作品のリストを取得する (新しい順)
    $stmt = $pdo->prepare("SELECT id as artwork_id, thumbnail_url FROM artworks WHERE user_id = :user_id ORDER BY id DESC");
    $stmt->execute([':user_id' => $authenticated_user_id]);
    $artworks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. バッジ情報を取得する
    // TODO: 将来的に、描いた作品数などに応じてバッジを付与するロジックをここに実装します。
    // 今回はダミーデータを返します。
    $badges = ["最初の1歩", "10km踏破"];


    // 4. すべての情報をまとめて、フロントエンドに返す
    $response = [
        'user_info' => $user_info,
        'badges' => $badges,
        'artworks' => $artworks
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'データベースエラー: ' . $e->getMessage()]);
}


