<?php

$base = 'http://www.stats.gov.cn/sj/tjbz/tjyqhdmhcxhfdm';
$year = '2022';
$baseURL = "{$base}/{$year}";

require_once('functions.php');

$districts = getDistricts($baseURL.'/44/4419.html');

echo json_encode($districts, JSON_UNESCAPED_UNICODE);
