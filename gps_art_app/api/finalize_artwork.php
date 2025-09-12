<?php
// finalize_artwork.php (サムネイル生成機能付き)

require_once 'db_connect.php';
require_once 'authenticate.php';

header('Content-Type: application/json');

// --- 設定項目 ---
$image_width = 800;
$image_height = 600;
$thumb_width = 400; // サムネイルの幅
$thumb_height = 300; // サムネイルの高さ
$line_color_hex = '#14BE6F';
$line_width = 5;

// ... (入力データの受け取りと認可チェックは変更なし) ...
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);
$artwork_id = $data['artwork_id'] ?? null;
$points = $data['points'] ?? null;

if (!$artwork_id || !$points || count($points) < 2) {
    http_response_code(400);
    echo json_encode(['error' => 'artwork_idと2つ以上の座標データは必須です。']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT user_id FROM artworks WHERE id = :id");
    $stmt->execute([':id' => $artwork_id]);
    $artwork = $stmt->fetch();
    if (!$artwork || $artwork['user_id'] != $authenticated_user_id) {
        http_response_code(403);
        echo json_encode(['error' => 'この作品を編集する権限がありません。']);
        exit();
    }
    
    // ... (画像生成の前半部分は変更なし) ...
    $min_lat = $points[0]['latitude']; $max_lat = $points[0]['latitude'];
    $min_lng = $points[0]['longitude']; $max_lng = $points[0]['longitude'];
    foreach ($points as $p) {
        if ($p['latitude'] < $min_lat) $min_lat = $p['latitude'];
        if ($p['latitude'] > $max_lat) $max_lat = $p['latitude'];
        if ($p['longitude'] < $min_lng) $min_lng = $p['longitude'];
        if ($p['longitude'] > $max_lng) $max_lng = $p['longitude'];
    }
    $zoom = 15;
    $center_lat = $min_lat + ($max_lat - $min_lat) / 2;
    $center_lng = $min_lng + ($max_lng - $min_lng) / 2;
    list($center_tile_x, $center_tile_y) = latLonToTile($center_lat, $center_lng, $zoom);
    list($center_pixel_x, $center_pixel_y) = latLonToPixel($center_lat, $center_lng, $zoom);
    $map_image = imagecreatetruecolor($image_width, $image_height);
    $tiles_x = ceil($image_width / 256) + 1;
    $tiles_y = ceil($image_height / 256) + 1;
    for ($x = 0; $x <= $tiles_x; $x++) {
        for ($y = 0; $y <= $tiles_y; $y++) {
            $tile_x = $center_tile_x - floor($tiles_x / 2) + $x;
            $tile_y = $center_tile_y - floor($tiles_y / 2) + $y;
            $tile_url = "https://tile.openstreetmap.org/{$zoom}/{$tile_x}/{$tile_y}.png";
            $opts = ['http' => ['header' => "User-Agent: GpsArtApp/1.0\r\n"]];
            $context = stream_context_create($opts);
            $tile_data = @file_get_contents($tile_url, false, $context);
            $tile_image = $tile_data ? imagecreatefromstring($tile_data) : null;
            if ($tile_image) {
                $dest_x = ($x * 256) - ($center_pixel_x % 256) - (floor($tiles_x/2)*256) + ($image_width/2);
                $dest_y = ($y * 256) - ($center_pixel_y % 256) - (floor($tiles_y/2)*256) + ($image_height/2);
                imagecopy($map_image, $tile_image, (int)$dest_x, (int)$dest_y, 0, 0, 256, 256);
                imagedestroy($tile_image);
            }
        }
    }
    list($r, $g, $b) = sscanf($line_color_hex, "#%02x%02x%02x");
    $line_color = imagecolorallocate($map_image, $r, $g, $b);
    imagesetthickness($map_image, $line_width);
    $last_pixel = null;
    foreach ($points as $point) {
        list($px, $py) = latLonToPixel($point['latitude'], $point['longitude'], $zoom);
        $draw_x = $px - $center_pixel_x + $image_width / 2;
        $draw_y = $py - $center_pixel_y + $image_height / 2;
        if ($last_pixel) {
            imageline($map_image, $last_pixel['x'], $last_pixel['y'], (int)$draw_x, (int)$draw_y, $line_color);
        }
        $last_pixel = ['x' => (int)$draw_x, 'y' => (int)$draw_y];
    }
    
    // --- ★★★ ここからが変更・追加部分 ★★★ ---

    // 7. 完成した「大きな画像」をサーバーに保存
    $image_file_name = "artwork_{$artwork_id}_" . time() . ".png";
    $image_save_path = __DIR__ . '/../images/artworks/' . $image_file_name;
    if (!is_dir(dirname($image_save_path))) mkdir(dirname($image_save_path), 0755, true);
    imagepng($map_image, $image_save_path);

    // 8. 「サムネイル画像」を生成して保存
    $thumb_image = imagecreatetruecolor($thumb_width, $thumb_height);
    // imagecopyresampledは、高品質な縮小画像を作成する関数
    imagecopyresampled($thumb_image, $map_image, 0, 0, 0, 0, $thumb_width, $thumb_height, $image_width, $image_height);
    
    $thumb_file_name = "thumb_{$image_file_name}"; // 元のファイル名にthumb_を付ける
    $thumb_save_path = __DIR__ . '/../images/artworks/' . $thumb_file_name;
    imagepng($thumb_image, $thumb_save_path);

    // 9. メモリを解放
    imagedestroy($map_image);
    imagedestroy($thumb_image);

    // 10. データベースのURLを「両方」更新
    $public_image_url = '/gps_art_app/images/artworks/' . $image_file_name;
    $public_thumb_url = '/gps_art_app/images/artworks/' . $thumb_file_name;
    
    $stmt = $pdo->prepare("UPDATE artworks SET image_url = :url, thumbnail_url = :thumb_url WHERE id = :id");
    $stmt->execute([
        ':url' => $public_image_url,
        ':thumb_url' => $public_thumb_url,
        ':id' => $artwork_id
    ]);

    // 11. フロントエンドに成功と「大きな画像のURL」を返す
    echo json_encode(['status' => 'success', 'image_url' => $public_image_url]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// --- (ヘルパー関数は変更なし) ---
function latLonToTile($lat, $lon, $zoom) {
    // ...
}
function latLonToPixel($lat, $lon, $zoom) {
    // ...
}

