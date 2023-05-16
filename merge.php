<?php

$filenames = ['city.json', 'district.json'];

foreach ($filenames as $filename) {
    $inputJson1 = 'dist/' . $filename;
    $inputJson2 = 'merge/' . $filename;
    $outputJson = 'merge/' . $filename;

    $input1 = json_decode(file_get_contents($inputJson1), true);
    $input2 = json_decode(file_get_contents($inputJson2), true);

    $output = [];

    foreach ($input1 as $provinceId => $cities) {
        if (hasData($cities)) {
            $output[$provinceId] = $cities;
        }
    }

    foreach ($input2 as $provinceId => $cities) {
        if (hasData($cities)) {
            $output[$provinceId] = $cities;
        }
    }

    file_put_contents($outputJson, json_encode($output, JSON_UNESCAPED_UNICODE));
}

function hasData($data)
{
    if ($data == null || count($data) == 0) {
        return false;
    } else {
        return $data;
    }
}
