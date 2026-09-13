<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\VehicleColor;

$cases = [
    ['Preto', 'en', 'black'],
    ['Branco', 'es', 'blanco'],
    ['Azul', 'fr', 'bleue'],
    ['BORDÔ', 'en', 'burgundy'],
    ['Cinzento', 'pt', 'cinzento'],
    ['Turquesa', 'en', null],
];

foreach ($cases as [$color, $language, $expected]) {
    $actual = VehicleColor::translate($color, $language);
    if ($actual !== $expected) {
        throw new RuntimeException("Falha ao traduzir $color para $language: esperado " . var_export($expected, true) . ', obtido ' . var_export($actual, true));
    }
}

echo "VehicleColorTest: OK\n";