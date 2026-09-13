<?php

namespace App;

final class VehicleColor
{
    private const TRANSLATIONS = [
        'amarelo' => ['pt' => 'amarelo', 'en' => 'yellow', 'es' => 'amarillo', 'fr' => 'jaune'],
        'azul' => ['pt' => 'azul', 'en' => 'blue', 'es' => 'azul', 'fr' => 'bleue'],
        'bege' => ['pt' => 'bege', 'en' => 'beige', 'es' => 'beige', 'fr' => 'beige'],
        'bordô' => ['pt' => 'bordô', 'en' => 'burgundy', 'es' => 'burdeos', 'fr' => 'bordeaux'],
        'branco' => ['pt' => 'branco', 'en' => 'white', 'es' => 'blanco', 'fr' => 'blanche'],
        'castanho' => ['pt' => 'castanho', 'en' => 'brown', 'es' => 'marrón', 'fr' => 'marron'],
        'cinzento' => ['pt' => 'cinzento', 'en' => 'grey', 'es' => 'gris', 'fr' => 'grise'],
        'dourado' => ['pt' => 'dourado', 'en' => 'gold', 'es' => 'dorado', 'fr' => 'dorée'],
        'laranja' => ['pt' => 'laranja', 'en' => 'orange', 'es' => 'naranja', 'fr' => 'orange'],
        'prateado' => ['pt' => 'prateado', 'en' => 'silver', 'es' => 'plateado', 'fr' => 'argentée'],
        'preto' => ['pt' => 'preto', 'en' => 'black', 'es' => 'negro', 'fr' => 'noire'],
        'roxo' => ['pt' => 'roxo', 'en' => 'purple', 'es' => 'morado', 'fr' => 'violette'],
        'verde' => ['pt' => 'verde', 'en' => 'green', 'es' => 'verde', 'fr' => 'verte'],
        'vermelho' => ['pt' => 'vermelho', 'en' => 'red', 'es' => 'rojo', 'fr' => 'rouge'],
    ];

    public static function translate(string $color, string $language): ?string
    {
        $normalizedColor = mb_strtolower(trim($color), 'UTF-8');
        return self::TRANSLATIONS[$normalizedColor][$language] ?? null;
    }
}