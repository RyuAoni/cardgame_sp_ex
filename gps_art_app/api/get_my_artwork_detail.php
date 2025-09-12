<?php
// get_my_artwork_detail.php (データベース連携版)

require_once 'db_connect.php';
// ★★★ 門番を配置！ ★★★
// 認証に成功すると、$authenticated_user_id 変数が使えるようになる。
require_once 'authenticate.php';

header('Content-Type: application/json');

// 1. どの作品の詳細を見たいかartwork_idを受け取る
$artwork_id = $_GET['artwork_id'] ?? null;

if (!$artwork_id) {
    http_response_code(400);
    echo json_encode(['error' => 'artwork_idが指定されていません。']);
    exit();
}

try {
    // --- データベースからのデータ取得 ---

    // 2. まず、作品の基本情報を取得する
    $stmt = $pdo->prepare(
        "SELECT * FROM artworks WHERE id = :id"
    );
    $stmt->execute([':id' => $artwork_id]);
    $artwork = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. 【重要】認可チェック
    // 作品が存在しない、または自分のものでない場合はエラー
    if (!$artwork || $artwork['user_id'] != $authenticated_user_id) {
        http_response_code(404); // Not Found (自分のものでない場合は存在しないのと同じ扱い)
        echo json_encode(['error' => '指定された作品が見つからないか、アクセス権限がありません。']);
        exit();
    }

    // 4. 次に、その作品の全GPS座標データを取得する
    $stmt = $pdo->prepare(
        "SELECT latitude, longitude FROM gps_points WHERE artwork_id = :artwork_id ORDER BY id ASC"
    );
    $stmt->execute([':artwork_id' => $artwork_id]);
    $gps_points = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. フロントエンドに返すデータを整形する
    $response = [
        'artwork_info' => [
            'artwork_id' => (int)$artwork['id'],
            'artwork_title' => $artwork['artwork_title'],
            'description' => $artwork['description'],
            'image_url' => $artwork['image_url'],
            'is_public' => (int)$artwork['is_public'],
            'created_at' => $artwork['created_at']
        ],
        'gps_points' => $gps_points
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'データベースエラー: ' . $e->getMessage()]);
}

