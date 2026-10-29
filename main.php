<?php

$debug = getenv('DEBUG') == 'true';
$safe = getenv('SAFE') == 'true';
$outDir = 'dist/';
$cookie = getenv('MCA_COOKIE') ?: '';

require_once('functions.php');

$tableName = getAdministrativeTableName($cookie);
if ($debug) {
    echo 'Using administrative-division table ' . $tableName . PHP_EOL;
}

$provinces = getAdministrativeItems(
    getAdministrativeDivisions('00', $tableName, $cookie),
    1
);
if ($debug) {
    echo json_encode($provinces, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

$provinceDivisionsData = [];
$cityDivisionsData = [];
foreach ($provinces as $provinceId => $provinceName) {
    if ($safe) {
        sleep(1);
    }

    $provinceDivisionsData[$provinceId] = getAdministrativeDivisions($provinceId . '000', $tableName, $cookie);
    $cities = getAdministrativeItems($provinceDivisionsData[$provinceId], 2);
    if ($debug) {
        echo $provinceId . ' => ' . count($cities) . PHP_EOL;
    }

    foreach ($cities as $cityId => $cityName) {
        if ($safe) {
            sleep(1);
        }
        $cityDivisionsData[$cityId] = getAdministrativeDivisions($cityId . '000', $tableName, $cookie);

        if ($debug) {
            echo $cityId . ' => ' . count(getAdministrativeItems($cityDivisionsData[$cityId], 3)) . PHP_EOL;
        }
    }
}

$citiesData = [];
$districtsData = [];
$missingCities = [];
$citiesWithoutDistricts = [];

foreach ($provinces as $provinceId => $provinceName) {
    $citiesData[$provinceId] = getAdministrativeItems($provinceDivisionsData[$provinceId], 2);
}

echo '[复检] 按返回的 level 重新归并三级区划...' . PHP_EOL;
$fetchedDivisions = array_merge(
    array_values($provinceDivisionsData),
    array_values($cityDivisionsData)
);
foreach ($fetchedDivisions as $divisions) {
    foreach (getAdministrativeItems($divisions, 3) as $districtId => $districtName) {
        $cityId = substr($districtId, 0, 4) . '00';
        $provinceId = substr($districtId, 0, 2) . '0000';
        if (!isset($provinces[$provinceId])) {
            fwrite(STDERR, "Skipping {$districtId}: province {$provinceId} was not fetched." . PHP_EOL);
            continue;
        }
        if (!isset($citiesData[$provinceId][$cityId])) {
            $citiesData[$provinceId][$cityId] = '!!!未知市!!!';
            $missingCities[$cityId] = $provinceId;
        }
        $districtsData[$cityId][$districtId] = $districtName;
    }
}

foreach ($citiesData as $provinceId => $cities) {
    foreach ($cities as $cityId => $cityName) {
        if (!empty($districtsData[$cityId])) {
            continue;
        }
        $citiesWithoutDistricts[] = $cityId;

        $level4 = getAdministrativeItems($cityDivisionsData[$cityId] ?? [], 4);
        foreach ($level4 as $divisionId => $divisionName) {
            $parentCityId = substr($divisionId, 0, 4) . '00';
            $parentProvinceId = substr($divisionId, 0, 2) . '0000';
            if (!isset($provinces[$parentProvinceId])) {
                fwrite(STDERR, "Skipping {$divisionId}: province {$parentProvinceId} was not fetched." . PHP_EOL);
                continue;
            }
            if (!isset($citiesData[$parentProvinceId][$parentCityId])) {
                $citiesData[$parentProvinceId][$parentCityId] = '!!!未知市!!!';
                $missingCities[$parentCityId] = $parentProvinceId;
            }
            $districtsData[$parentCityId][$divisionId] = $divisionName;
        }
        if (!isset($districtsData[$cityId])) {
            $districtsData[$cityId] = [];
        }
    }
}

echo '[复检] 缺失的2级区划（已添加占位）:' . PHP_EOL;
if (count($missingCities) === 0) {
    echo "  无" . PHP_EOL;
} else {
    foreach ($missingCities as $cityId => $provinceId) {
        echo "  {$provinceId} => {$cityId} !!!未知市!!!" . PHP_EOL;
    }
}

echo '[复检] 没有3级区划的2级区划（已提升可用的4级区划）:' . PHP_EOL;
if (count($citiesWithoutDistricts) === 0) {
    echo "  无" . PHP_EOL;
} else {
    foreach ($citiesWithoutDistricts as $cityId) {
        echo "  {$cityId}" . PHP_EOL;
    }
}

if (!is_dir($outDir) && !mkdir($outDir, 0777, true) && !is_dir($outDir)) {
    throw new RuntimeException("Unable to create output directory: {$outDir}");
}

$outputs = [
    'province.json' => $provinces,
    'city.json' => $citiesData,
    'district.json' => $districtsData,
];
foreach ($outputs as $filename => $data) {
    if (file_put_contents($outDir . $filename, json_encode($data, JSON_UNESCAPED_UNICODE)) === false) {
        throw new RuntimeException("Unable to write output file: {$outDir}{$filename}");
    }
    echo $filename . ' generated' . PHP_EOL;
}
