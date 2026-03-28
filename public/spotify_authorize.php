<?php
// public/spotify_authorize.php

// Carregamos as nossas configurações, que contêm as chaves da API
require_once __DIR__ . '/../config/database.php';

session_start();

// Geramos uma string aleatória para o 'state'. É uma medida de segurança (proteção CSRF).
$state = bin2hex(random_bytes(16));
$_SESSION['spotify_auth_state'] = $state;

// As permissões ('scopes') que a nossa aplicação precisa.
// - user-read-playback-state: para ver o que está a tocar e em que dispositivo.
// - user-modify-playback-state: para dar play, pausar, etc.
$scope = 'user-read-playback-state user-modify-playback-state';

// Montamos o URL de autorização
$queryParams = http_build_query([
    'response_type' => 'code',
    'client_id' => SPOTIFY_CLIENT_ID,
    'scope' => $scope,
    'redirect_uri' => SPOTIFY_REDIRECT_URI,
    'state' => $state,
]);

// Redirecionamos o utilizador para a página de autorização do Spotify
header('Location: https://accounts.spotify.com/authorize?' . $queryParams);
exit();