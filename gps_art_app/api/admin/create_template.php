<?php
// create_template.php
// 機能：新しい「型」をデータベースに保存する

// ★★★ 管理者用の門番を配置！ ★★★
// これ以降のコードは、管理者として認証された場合にのみ実行される。
// $authenticated_admin_id 変数が使えるようになる。
require_once 'authenticate_admin.php';

header('Content-Type: application/json');

// フロントエンドから送信されたJSON形式のデータを取得
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// 必須項目があるかチェック
if (
    !isset($data['template_name']) ||
    !isset($data['prefecture_id']) ||
    !isset($data['start_point']['latitude']) ||
    !isset($data['start_point']['longitude']) ||
    !isset($data['gps_points']) ||
    !is_array($data['gps_points'])
) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => '型名、都道府県ID、スタート地点、GPS座標は必須です。']);
    exit();
}

// 受け取ったデータを変数に格納
$template_name = $data['template_name'];
$prefecture_id = $data['prefecture_id'];
$start_lat = $data['start_point']['latitude'];
$start_lng = $data['start_point']['longitude'];
$gps_points = $data['gps_points'];

try {
    // データベース操作をトランザクション内で実行
    $pdo->beginTransaction();

    // 1. templatesテーブルに基本情報をINSERTする
    $stmt = $pdo->prepare(
        "INSERT INTO templates (template_name, prefecture_id, start_latitude, start_longitude) VALUES (:name, :pref_id, :start_lat, :start_lng)"
    );
    $stmt->execute([
        ':name' => $template_name,
        ':pref_id' => $prefecture_id,
        ':start_lat' => $start_lat,
        ':start_lng' => $start_lng
    ]);

    // 2. 新しく作られたtemplate_idを取得する
    $template_id = $pdo->lastInsertId();

    // 3. gps_pointsをループして、template_pointsテーブルにINSERTする
    $stmt = $pdo->prepare(
        "INSERT INTO template_points (template_id, latitude, longitude, sequence) VALUES (:template_id, :lat, :lng, :seq)"
    );
    
    $sequence = 0;
    foreach ($gps_points as $point) {
        $stmt->execute([
            ':template_id' => $template_id,
            ':lat' => $point['latitude'],
            ':lng' => $point['longitude'],
            ':seq' => $sequence
        ]);
        $sequence++;
    }

    // 4. すべてのクエリが成功したら、トランザクションを確定
    $pdo->commit();

    // 成功レスポンスを返す
    http_response_code(201); // Created
    echo json_encode([
        'status' => 'success',
        'message' => '新しい型を作成しました。',
        'template_id' => (int)$template_id
    ]);

} catch (PDOException $e) {
    // エラーが発生した場合は、トランザクションを元に戻す
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'データベースへの保存中にエラーが発生しました: ' . $e->getMessage()]);
}

