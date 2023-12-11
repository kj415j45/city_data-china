<?php

$base = 'https://www.stats.gov.cn/sj/tjbz/tjyqhdmhcxhfdm';
$year = '2023';
$baseURL = "{$base}/{$year}";

$sourceURL = "{$baseURL}/index.html";

$debug = getenv('DEBUG') == 'true';
$safe = getenv('SAFE') == 'true';
$outDir = 'dist/';

require_once('functions.php');

$provinces = getProvinces($sourceURL);
if($debug) {
    echo(json_encode($provinces, JSON_UNESCAPED_UNICODE).PHP_EOL);
}
$provinceData = [];
foreach($provinces as $provinceId => $provinceName) {
    $provinceData[$provinceId . '0000'] = $provinceName;
}
file_put_contents($outDir.'province.json', json_encode($provinceData, JSON_UNESCAPED_UNICODE));
echo("province.json generated".PHP_EOL);

$cities = [];
foreach($provinces as $provinceId => $provinceName) {
    if($safe) sleep(1);
    $cities[$provinceId] = getCities("{$baseURL}/{$provinceId}.html");
    if($debug){
        echo($provinceId." => ".count($cities[$provinceId]).PHP_EOL);
    }
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
echo("city.json generated".PHP_EOL);

$districts = [];
foreach($cities as $provinceId => $cityData) {
    foreach($cityData as $cityId => $cityName) {
        if($safe) sleep(1);
        $districts[$cityId] = getDistricts("{$baseURL}/{$provinceId}/{$cityId}.html");
        if($debug) {
            echo($cityId." => ".count($districts[$cityId]).PHP_EOL);
        }
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
echo("district.json generated".PHP_EOL);