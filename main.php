<?php

$base = 'http://www.stats.gov.cn/sj/tjbz/tjyqhdmhcxhfdm';
$year = '2022';
$baseURL = "{$base}/{$year}";

$sourceURL = "{$baseURL}/index.html";

$debug = false;
$safe = false;
$outDir = 'dist/';

$provinces = getProvinces($sourceURL);
$provinceData = [];
foreach($provinces as $provinceId => $provinceName) {
    $provinceData[$provinceId . '0000'] = $provinceName;
}
file_put_contents($outDir.'province.json', json_encode($provinceData, JSON_UNESCAPED_UNICODE));

$cities = [];
foreach($provinces as $provinceId => $provinceName) {
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

function getProvinces(string $url) {
    $source = @gzdecode(file_get_contents($url));

    $provinceMatches = [];
    // <a href="21.html">辽宁省<br /></a>
    $provincePattern = '/<a href="(\d{2}).html">(\S*)<br \/><\/a>/';
    preg_match_all($provincePattern, $source, $provinceMatches);

    $provinces = [];
    for ($i = 0; $i < count($provinceMatches[1]); $i++) {
        $provinces[$provinceMatches[1][$i]] = $provinceMatches[2][$i];
    }
    return $provinces;
}

function getCities(string $url) {
    $source = @gzdecode(file_get_contents($url));

    $cityMatches = [];
    // <a href="45/4505.html">北海市</a>
    $cityPattern = '/<a href="\d{2}\/(\d{4})\.html">([^\d]*)<\/a>/';
    preg_match_all($cityPattern, $source, $cityMatches);

    $cities = [];
    for ($i = 0; $i < count($cityMatches[1]); $i++) {
        $cities[$cityMatches[1][$i]] = $cityMatches[2][$i];
    }
    return $cities;
}

function getDistricts(string $url) {
    $source = @gzdecode(file_get_contents($url));

    $districtMatches = [];
    // <a href="10/451027.html">凌云县</a>
    $districtPattern = '/<a href="\d{2}\/(\d{6})\.html">([^\d]*)<\/a>/';
    preg_match_all($districtPattern, $source, $districtMatches);

    $districts = [];
    for ($i = 0; $i < count($districtMatches[1]); $i++) {
        $districts[$districtMatches[1][$i]] = $districtMatches[2][$i];
    }
    return $districts;
}