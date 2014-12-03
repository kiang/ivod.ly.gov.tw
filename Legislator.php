<?php

$tmpPath = __DIR__ . '/tmp/Legislator';
$targetPath = __DIR__ . '/Legislator';
$targetPathLen = strlen($targetPath) + 1;
if (!file_exists($tmpPath)) {
    mkdir($tmpPath, 0777, true);
}
$listFh = array();

$legislators = getJson('http://ivod.ly.gov.tw/Legislator/Lglts');

foreach ($legislators['lglts'] AS $legislator) {
    $pageCount = 1;
    for ($i = 1; $i <= $pageCount; $i ++) {
        $page = postJson($legislator['LGLTID'], $i);
        if ($i === 1) {
            $pageCount = ceil($page['total'] / 5);
        }
        if (!empty($page['result'])) {
            foreach ($page['result'] AS $video) {
                $videoTime = strtotime(substr($video['ST_TIM'], 0, strpos($video['ST_TIM'], ' ')) . ' ' . $video['SYS_ST']);
                $videoYear = date('Y', $videoTime);
                if (!isset($listFh[$videoYear])) {
                    $listFh[$videoYear] = fopen(__DIR__ . '/Legislator_' . $videoYear . '.csv', 'w');
                    fputcsv($listFh[$videoYear], array(
                        '立法委員', '影片編號', '影片日期', '影片標題', '觀看網址', '影片資訊'
                    ));
                }
                $filename = date('Ym/Ymd_His', $videoTime) . '.json';
                $videoTarget = "{$targetPath}/{$page['lglt']['STAGE_']}/{$page['lglt']['LGLTID']}/{$filename}";
                $profileTarget = "{$targetPath}/{$page['lglt']['STAGE_']}/{$page['lglt']['LGLTID']}/profile.json";
                if (!file_exists($videoTarget)) {
                    $p = pathinfo($videoTarget);
                    if (!file_exists($p['dirname'])) {
                        mkdir($p['dirname'], 0777, true);
                    }
                    if (!empty($video['PHOTO_'])) {
                        $video['PHOTO_'] = 'http://ivod.ly.gov.tw/Image/Pic/' . $video['PHOTO_'];
                    }
                    file_put_contents($videoTarget, json_encode($video, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                }
                if (!file_exists($profileTarget)) {
                    file_put_contents($profileTarget, json_encode($page['lglt'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                }
                if (empty($video['METNAM'])) {
                    $video['METNAM'] = $video['CM_NAM'];
                }
                fputcsv($listFh[$videoYear], array(
                    $page['lglt']['CH_NAM'], $video['WZS_ID'], date('Y-m-d H:i:s', $videoTime),
                    $video['METNAM'], "http://ivod.ly.gov.tw/Play/VOD/{$video['WZS_ID']}/1M",
                    substr($videoTarget, $targetPathLen),
                ));
            }
        }
    }
}

function getJson($url) {
    global $tmpPath;
    $cache = "{$tmpPath}/" . md5($url);
    if (!file_exists($cache)) {
        file_put_contents($cache, file_get_contents($url));
    }
    $c = file_get_contents($cache);
    return json_decode(substr($c, strpos($c, '{')), true);
}

function postJson($id = '', $page = 1) {
    global $tmpPath;
    $url = 'http://ivod.ly.gov.tw/Legislator/MovieByLglt';
    $data = "lgltid={$id}&page={$page}";
    $cache = "{$tmpPath}/" . md5($url . $data);
    if (!file_exists($cache) || filesize($cache) < 100) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $headers = array(
            'Referer:http://ivod.ly.gov.tw/Legislator',
            'X-Requested-With:XMLHttpRequest',
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $server_output = curl_exec($ch);
        curl_close($ch);
        $server_output = substr($server_output, strpos($server_output, '{'));
        file_put_contents($cache, $server_output);
    } else {
        $server_output = file_get_contents($cache);
    }

    return json_decode($server_output, true);
}
