<?php
// api/get_weather.php
header('Content-Type: application/json');
require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

// Coordenadas para Estômbar, Portugal (aproximadas)
$latitude = 37.1352;
$longitude = -8.4831;

// Documentação da API: https://open-meteo.com/en/docs
$apiUrl = "https://api.open-meteo.com/v1/forecast?latitude={$latitude}&longitude={$longitude}&current=temperature_2m,weather_code&timezone=Europe/Lisbon";

$client = new Client();

try {
    $response = $client->get($apiUrl);
    $data = json_decode($response->getBody()->getContents());

    // Vamos simplificar e formatar os dados antes de os enviar para o frontend
    $weather_code = $data->current->weather_code;
    $temperature = round($data->current->temperature_2m);

    // Função para traduzir o código do tempo num ícone e descrição
    function getWeatherInfo($code) {
        if ($code == 0) return ['icon' => 'fa-sun', 'description' => 'Céu limpo'];
        if ($code == 1) return ['icon' => 'fa-cloud-sun', 'description' => 'Maioritariamente limpo'];
        if ($code == 2) return ['icon' => 'fa-cloud', 'description' => 'Parcialmente nublado'];
        if ($code == 3) return ['icon' => 'fa-cloud', 'description' => 'Nublado'];
        if ($code >= 51 && $code <= 65) return ['icon' => 'fa-cloud-rain', 'description' => 'Chuva'];
        if ($code >= 80 && $code <= 82) return ['icon' => 'fa-cloud-showers-heavy', 'description' => 'Aguaceiros'];
        if ($code >= 95 && $code <= 99) return ['icon' => 'fa-cloud-bolt', 'description' => 'Trovoada'];
        return ['icon' => 'fa-smog', 'description' => 'Nevoeiro']; // Para outros casos como nevoeiro, etc.
    }
    
    $weatherInfo = getWeatherInfo($weather_code);

    $formattedData = [
        'location' => 'Estômbar',
        'temperature' => $temperature,
        'icon' => $weatherInfo['icon'],
        'description' => $weatherInfo['description']
    ];

    echo json_encode($formattedData);

} catch (GuzzleException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível obter os dados do tempo.']);
}