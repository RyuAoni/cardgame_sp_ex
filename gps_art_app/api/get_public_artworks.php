<?php
// get_public_artworks.php (データベース連携版)

require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    // --- データベースからのデータ取得 ---

    // 1. 公開設定(is_public = 1)の作品を、新しいものから20件取得する
    // ★★★ JOINを使って、artworksテーブルとusersテーブルを連携させる ★★★
    $stmt = $pdo->prepare(
        "SELECT
            art.id as artwork_id,
            art.artwork_title,
            art.thumbnail_url,
            usr.user_name
        FROM
            artworks AS art
        INNER JOIN
            users AS usr ON art.user_id = usr.id
        WHERE
            art.is_public = 1
        ORDER BY
            art.id DESC
        LIMIT 20"
    );

    $stmt->execute();
    $artworks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. 取得した作品リストをJSON形式で出力する
    echo json_encode(['artwork_info' => $artworks]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'データベースエラー: ' . $e->getMessage()]);
}

