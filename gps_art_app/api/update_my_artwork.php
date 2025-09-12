<?php
// update_my_artwork.php

require_once 'db_connect.php';
// ★★★ 門番を配置！ ★★★
require_once 'authenticate.php';

header('Content-Type: application/json');

// 1. どの作品を更新するかartwork_idを受け取る
$artwork_id = $_GET['artwork_id'] ?? null;
if (!$artwork_id) {
    http_response_code(400);
    echo json_encode(['error' => 'artwork_idが指定されていません。']);
    exit();
}

// 2. フロントエンドから更新するデータを受け取る
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// 更新するデータを変数に格納（存在しない場合はnull）
$artwork_title = $data['artwork_title'] ?? null;
$description = $data['description'] ?? null;
$is_public = $data['is_public'] ?? null;

try {
    // 3. 【重要】認可チェック：作品が本人ものか確認
    $stmt = $pdo->prepare("SELECT user_id FROM artworks WHERE id = :id");
    $stmt->execute([':id' => $artwork_id]);
    $artwork = $stmt->fetch();

    if (!$artwork || $artwork['user_id'] != $authenticated_user_id) {
        http_response_code(404);
        echo json_encode(['error' => '指定された作品が見つからないか、アクセス権限がありません。']);
        exit();
    }

    // 4. データベースを更新する
    // SET句を動的に構築することもできるが、今回はシンプルに全項目を更新対象とする
    $stmt = $pdo->prepare(
        "UPDATE artworks SET artwork_title = :title, description = :desc, is_public = :is_public WHERE id = :id"
    );
    $stmt->execute([
        ':title' => $artwork_title,
        ':desc' => $description,
        ':is_public' => $is_public,
        ':id' => $artwork_id
    ]);

    echo json_encode(['status' => 'success', 'message' => '作品情報を更新しました。']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'データベースエラー: ' . $e->getMessage()]);
}
