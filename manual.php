<?php

require_once('functions.php');

$cookie = getenv('MCA_COOKIE') ?: '';
$tableName = getAdministrativeTableName($cookie);
$divisions = getAdministrativeDivisions('441900000', $tableName, $cookie);

echo json_encode($divisions, JSON_UNESCAPED_UNICODE);
