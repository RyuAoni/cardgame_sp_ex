<?php
// get_artwork.php (データベース連携版)

require_once 'db_connect.php';

header('Content-Type: application/json');

// 1. フロントエンドから、どの作品を見たいかartwork_idを受け取る
$artwork_id = $_GET['artwork_id'] ?? null;

if (!$artwork_id) {
    http_response_code(400);
    echo json_encode(['error' => 'artwork_idが指定されていません。']);
    exit();
}

try {
    // --- データベースからのデータ取得 ---

    // 2. 作品情報と、それを作成したユーザーの情報をJOINで一度に取得する
    $stmt = $pdo->prepare(
        "SELECT
            art.id as artwork_id,
            art.artwork_title,
            art.description,
            art.image_url,
            art.created_at,
            usr.id as user_id,
            usr.user_name,
            usr.icon_image_url
        FROM
            artworks AS art
        INNER JOIN
            users AS usr ON art.user_id = usr.id
        WHERE
            art.id = :artwork_id AND art.is_public = 1"
    );
    $stmt->execute([':artwork_id' => $artwork_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // 作品が見つからない、または非公開の場合はエラー
    if (!$result) {
        http_response_code(404);
        echo json_encode(['error' => '作品が見つからないか、非公開に設定されています。']);
        exit();
    }

    // 3. その作品の全GPS座標データを取得する
    $stmt = $pdo->prepare(
        "SELECT latitude, longitude FROM gps_points WHERE artwork_id = :artwork_id ORDER BY id ASC"
    );
    $stmt->execute([':artwork_id' => $artwork_id]);
    $gps_points = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. フロントエンドに返すデータを整形する
    $response = [
        'artwork_info' => [
            'artwork_id' => (int)$result['artwork_id'],
            'artwork_title' => $result['artwork_title'],
            'description' => $result['description'],
            'image_url' => $result['image_url'],
            'created_at' => $result['created_at']
        ],
        'user_info' => [
            'user_id' => (int)$result['user_id'],
            'user_name' => $result['user_name'],
            'icon_image_url' => $result['icon_image_url']
        ],
        'gps_points' => $gps_points
    ];

    echo json_encode(['artwork_info' => [$response['artwork_info']], 'user_info' => [$response['user_info']]]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'データベースエラー: ' . $e->getMessage()]);
}

