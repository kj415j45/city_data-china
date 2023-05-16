<?php

$inputDir = 'merge/';

$provinces = json_decode(file_get_contents($inputDir.'province.json'), true);
$cities = json_decode(file_get_contents($inputDir.'city.json'), true);
$districts = json_decode(file_get_contents($inputDir.'district.json'), true);

foreach($provinces as $provinceId => $provinceName) {
    echo "{$provinceId} => {$provinceName}\n";
    if(!isset($cities[$provinceId])) {
        echo "[Error] No cities in {$provinceName}({$provinceId})\n";
        continue;
    }
    foreach($cities[$provinceId] as $cityId => $cityName) {
        echo "  {$cityId} => {$cityName}\n";
        if(!isset($districts[$cityId])) {
            echo "[Error] No districts in {$cityName}({$cityId})\n";
            continue;
        }
        if(count($districts[$cityId]) == 0) {
            echo "[Error] District data missing in {$cityName}({$cityId})\n";
            continue;
        }
        foreach($districts[$cityId] as $districtId => $districtName) {
            echo "    {$districtId} => {$districtName}\n";
        }
    }
}
