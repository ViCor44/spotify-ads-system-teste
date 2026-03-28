<?php
// public/templates/header.php
$page = $_GET['page'] ?? 'dashboard';
$bodyClass = ($page === 'dashboard') ? 'theme-dark' : '';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spot Master</title>
    
    <!-- Fontes e Ícones -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" xintegrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@700&display=swap" rel="stylesheet">

    <!-- Ligação para o ficheiro de estilos externo -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?= $bodyClass ?? '' ?>">
    <div class="audio-prompt" id="audioPrompt">
        <span>O leitor de anúncios está inativo.</span>
        <button id="enableAudioButton">Ativar Áudio</button>
    </div>
    <div class="container">
        
        <!-- A ESTRUTURA CORRETA DA SIDEBAR COMEÇA AQUI -->
        <div class="sidebar">

            <!-- Item 1: O Cabeçalho -->
            <div class="sidebar-card-header">
                <a href="index.php?page=about" class="sidebar-title-link">
                    <h2 class="sidebar-header"><i class="fa-solid fa-tower-broadcast"></i> Spot Master</h2>
                    <small>Versão 1.0</small>
                </a>
            </div>

            <!-- Item 2: A Lista de Menus (que cresce e faz scroll) -->
            <ul>
                <li><a href="index.php?page=dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>"><i class="fa-solid fa-house"></i> Dashboard</a></li>
                <li><a href="index.php?page=play_announcements" class="<?= $page === 'play_announcements' ? 'active' : '' ?>"><i class="fa-solid fa-play"></i> Tocar Anúncios</a></li>
                <li><a href="index.php?page=manage_announcements" class="<?= $page === 'manage_announcements' ? 'active' : '' ?>"><i class="fa-solid fa-upload"></i> Gerir Anúncios</a></li>
                <li><a href="index.php?page=manage_schedules&action=list" class="<?= $page === 'manage_schedules' ? 'active' : '' ?>"><i class="fa-solid fa-calendar-days"></i> Agendamentos</a></li>
                <li><a href="index.php?page=tts_announcement" class="<?= $page === 'tts_announcement' ? 'active' : '' ?>"><i class="fa-solid fa-microphone-lines"></i> Anúncio TTS</a></li>
            </ul>

            <!-- Item 3: O Rodapé Fixo -->
            <div class="sidebar-footer">
                <a href="#" id="fullscreen-btn" title="Alternar Ecrã Inteiro">
                    <i class="fa-solid fa-expand"></i>
                    <span>Ecrã Inteiro</span>
                </a>
            </div>

        </div> <!-- Fim de .sidebar -->
        
        <div class="main-content">
