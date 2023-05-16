<?php

$debug = getenv('DEBUG');

$inputDir = 'merge/';

$provinces = json_decode(file_get_contents($inputDir.'province.json'), true);
$cities = json_decode(file_get_contents($inputDir.'city.json'), true);
$districts = json_decode(file_get_contents($inputDir.'district.json'), true);

foreach($provinces as $provinceId => $provinceName) {
    if($debug) echo "{$provinceId} => {$provinceName}\n";
    if(!isset($cities[$provinceId])) {
        echo "[Error] ({$provinceId})\n";
        continue;
    }
    foreach($cities[$provinceId] as $cityId => $cityName) {
        if($debug) echo "  {$cityId} => {$cityName}\n";
        if(!isset($districts[$cityId])) {
            echo "[Error] ({$cityId})\n";
            continue;
        }
        if(count($districts[$cityId]) == 0) {
            echo "[Error] ({$cityId})\n";
            continue;
        }
        if($debug) foreach($districts[$cityId] as $districtId => $districtName) {
            echo "    {$districtId} => {$districtName}\n";
        }
    }
}
