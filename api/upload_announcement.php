<?php
// api/upload_announcement.php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;

// Inclui a classe da biblioteca getID3
use getID3;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['title']) && isset($_FILES['audio_file'])) {
        
        if ($_FILES['audio_file']['error'] !== UPLOAD_ERR_OK) {
            die("Erro no upload do ficheiro. Código: " . $_FILES['audio_file']['error']);
        }

        $title = $_POST['title'];
        $audioFile = $_FILES['audio_file'];
        
        $uploadDir = __DIR__ . '/../public/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = uniqid() . '-' . basename($audioFile['name']);
        $uploadFilePath = $uploadDir . $fileName;

        if (move_uploaded_file($audioFile['tmp_name'], $uploadFilePath)) {
            
            // --- NOVA LÓGICA PARA OBTER A DURAÇÃO ---
            $getID3 = new getID3;
            $fileInfo = $getID3->analyze($uploadFilePath);
            $durationSeconds = isset($fileInfo['playtime_seconds']) ? (int)round($fileInfo['playtime_seconds']) : 0;
            // --- FIM DA NOVA LÓGICA ---

            try {
                $pdo = Database::getInstance();
                // Query de inserção atualizada para incluir a duração
                $sql = "INSERT INTO announcements (title, file_path, duration_seconds) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$title, $fileName, $durationSeconds]);

                header('Location: ../public/index.php?page=manage_announcements&upload=success');
                exit();
                
            } catch (Exception $e) {
                unlink($uploadFilePath); 
                die("Erro ao guardar na base de dados: " . $e->getMessage());
            }
        } else {
            die("Erro ao mover o ficheiro para o diretório de uploads.");
        }
    }
} else {
    // Se alguém tentar aceder a este script diretamente via GET, redireciona para o dashboard
    header('Location: ../public/index.php?page=dashboard');
    exit();
}

/**
 * Função auxiliar para criar um nome de ficheiro "amigável" para URLs e sistemas de ficheiros.
 * Ex: "Anúncio Verão!" -> "anuncio-verao"
 */
function slugify($text) {
    // Substitui caracteres acentuados pelos seus equivalentes sem acento
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    // Substitui tudo o que não for letras, números, espaços ou hífen por nada
    $text = preg_replace('~[^\\pL\d\s-]+~u', '', $text);
    // Remove espaços no início e no fim
    $text = trim($text);
    // Converte para minúsculas
    $text = strtolower($text);
    // Substitui espaços e outros caracteres não alfanuméricos por hífen
    $text = preg_replace('~[\s-]+~', '-', $text);
    return $text;
}