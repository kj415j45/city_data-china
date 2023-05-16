<?php

$base = 'http://www.stats.gov.cn/sj/tjbz/tjyqhdmhcxhfdm';
$year = '2022';
$baseURL = "{$base}/{$year}";

$sourceURL = "{$baseURL}/index.html";

$debug = getenv('DEBUG');
$safe = getenv('SAFE');
$outDir = 'dist/';

require_once('functions.php');

$provinces = getProvinces($sourceURL);
$provinceData = [];
foreach($provinces as $provinceId => $provinceName) {
    $provinceData[$provinceId . '0000'] = $provinceName;
}
file_put_contents($outDir.'province.json', json_encode($provinceData, JSON_UNESCAPED_UNICODE));

$cities = [];
foreach($provinces as $provinceId => $provinceName) {
    if($safe) sleep(1);
    $cities[$provinceId] = getCities("{$baseURL}/{$provinceId}.html");
}
$citiesData = [];
foreach($cities as $provinceId => $cityData) {
    $citiesInProvince = [];
    foreach($cityData as $cityId => $cityName) {
        $citiesInProvince[$cityId . '00'] = $cityName;
    }
    $citiesData[$provinceId.'0000'] = $citiesInProvince;
}
file_put_contents($outDir.'city.json', json_encode($citiesData, JSON_UNESCAPED_UNICODE));

$districts = [];
foreach($cities as $provinceId => $cityData) {
    foreach($cityData as $cityId => $cityName) {
        if($safe) sleep(1);
        $districts[$cityId] = getDistricts("{$baseURL}/{$provinceId}/{$cityId}.html");
    }
}
$districtsData = [];
foreach($districts as $cityId => $districtData) {
    $districtsInCity = [];
    foreach($districtData as $districtId => $districtName) {
        $districtsInCity[$districtId] = $districtName;
    }
    $districtsData[$cityId.'00'] = $districtsInCity;
}
file_put_contents($outDir.'district.json', json_encode($districtsData, JSON_UNESCAPED_UNICODE));
