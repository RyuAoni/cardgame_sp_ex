<?php
// delete_my_artwork.php

require_once 'db_connect.php';
// ★★★ 門番を配置！ ★★★
require_once 'authenticate.php';

header('Content-Type: application/json');

// 1. どの作品を削除するかartwork_idを受け取る
$artwork_id = $_GET['artwork_id'] ?? null;
if (!$artwork_id) {
    http_response_code(400);
    echo json_encode(['error' => 'artwork_idが指定されていません。']);
    exit();
}

try {
    // 2. 【重要】認可チェック：作品が本人ものか確認
    $stmt = $pdo->prepare("SELECT user_id FROM artworks WHERE id = :id");
    $stmt->execute([':id' => $artwork_id]);
    $artwork = $stmt->fetch();

    if (!$artwork || $artwork['user_id'] != $authenticated_user_id) {
        http_response_code(404);
        echo json_encode(['error' => '指定された作品が見つからないか、アクセス権限がありません。']);
        exit();
    }

    // 3. データベースから作品を削除する
    // artworksテーブルから削除すると、関連するgps_pointsも自動で削除される（ON DELETE CASCADE）
    $stmt = $pdo->prepare("DELETE FROM artworks WHERE id = :id");
    $stmt->execute([':id' => $artwork_id]);

    echo json_encode(['status' => 'success', 'message' => '作品を削除しました。']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'データベースエラー: ' . $e->getMessage()]);
}

