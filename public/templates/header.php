<?php
// public/templates/header.php
$page = $_GET['page'] ?? 'dashboard';
// Default por página: dashboard = escuro; restantes = claro. Utilizador pode sobrepor via toggle.
$defaultTheme = ($page === 'dashboard') ? 'dark' : 'light';
$bodyClasses = [];
if ($defaultTheme === 'dark') { $bodyClasses[] = 'theme-dark'; }
$bodyClasses[] = 'page-' . preg_replace('/[^a-z0-9_-]/i', '', $page);
$bodyClass = implode(' ', $bodyClasses);
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
<body class="<?= $bodyClass ?>">
    <!-- Aplica preferência de tema guardada antes de renderizar o resto do conteúdo (evita "flash") -->
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('spot-master-theme');
                if (saved === 'dark' || saved === 'light') {
                    document.body.classList.toggle('theme-dark', saved === 'dark');
                }
            } catch (e) { /* ignore */ }
        })();
    </script>
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
                    <div class="sidebar-brand">
                        <svg class="sidebar-logo" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <defs>
                                <linearGradient id="spotMasterGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#1ed760"/>
                                    <stop offset="100%" stop-color="#0e8c3f"/>
                                </linearGradient>
                                <linearGradient id="spotMasterShine" x1="0%" y1="0%" x2="0%" y2="100%">
                                    <stop offset="0%" stop-color="#ffffff" stop-opacity="0.28"/>
                                    <stop offset="55%" stop-color="#ffffff" stop-opacity="0"/>
                                </linearGradient>
                            </defs>
                            <rect x="0" y="0" width="48" height="48" rx="14" fill="url(#spotMasterGrad)"/>
                            <rect x="0" y="0" width="48" height="48" rx="14" fill="url(#spotMasterShine)"/>
                            <g fill="#ffffff">
                                <rect x="11" y="18" width="4" height="12" rx="2">
                                    <animate attributeName="height" values="10;20;10" dur="1.2s" repeatCount="indefinite"/>
                                    <animate attributeName="y" values="19;14;19" dur="1.2s" repeatCount="indefinite"/>
                                </rect>
                                <rect x="19" y="12" width="4" height="24" rx="2">
                                    <animate attributeName="height" values="24;10;24" dur="1.2s" begin="-0.4s" repeatCount="indefinite"/>
                                    <animate attributeName="y" values="12;19;12" dur="1.2s" begin="-0.4s" repeatCount="indefinite"/>
                                </rect>
                                <rect x="27" y="16" width="4" height="16" rx="2">
                                    <animate attributeName="height" values="14;22;14" dur="1.2s" begin="-0.2s" repeatCount="indefinite"/>
                                    <animate attributeName="y" values="17;13;17" dur="1.2s" begin="-0.2s" repeatCount="indefinite"/>
                                </rect>
                                <rect x="35" y="20" width="4" height="8" rx="2">
                                    <animate attributeName="height" values="8;16;8" dur="1.2s" begin="-0.6s" repeatCount="indefinite"/>
                                    <animate attributeName="y" values="20;16;20" dur="1.2s" begin="-0.6s" repeatCount="indefinite"/>
                                </rect>
                            </g>
                        </svg>
                        <div class="sidebar-brand-text">
                            <span class="brand-name">Spot<span class="brand-accent">Master</span></span>
                            <span class="brand-version">v1.0</span>
                        </div>
                    </div>
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
                <a href="#" id="theme-toggle-btn" title="Alternar tema claro/escuro">
                    <i class="fa-solid fa-moon"></i>
                    <span>Tema</span>
                </a>
                <a href="#" id="fullscreen-btn" title="Alternar Ecrã Inteiro">
                    <i class="fa-solid fa-expand"></i>
                    <span>Ecrã Inteiro</span>
                </a>
            </div>

        </div> <!-- Fim de .sidebar -->
        
        <div class="main-content">
