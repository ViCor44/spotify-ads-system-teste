<?php
// src/Database.php

namespace App; // O namespace que definimos no composer.json

use PDO;
use PDOException;

/**
 * Classe para gerir a ligação à base de dados usando o padrão Singleton.
 * Isto garante que temos apenas uma única instância (e ligação) da base de dados em toda a aplicação.
 */
class Database {
    private static $instance = null;
    private $conn;

    /**
     * O construtor é privado para impedir a criação de novas instâncias
     * com o operador 'new'.
     */
    private function __construct() {
        // Carrega as nossas constantes de configuração
        require_once __DIR__ . '/../config/database.php';

        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

        try {
            $this->conn = new PDO($dsn, DB_USER, DB_PASS);
            // Configura o PDO para lançar exceções em caso de erro, o que é uma boa prática
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            // Se a ligação falhar, termina a execução e mostra o erro.
            die('Erro de ligação à base de dados: ' . $e->getMessage());
        }
    }

    /**
     * O método estático que controla o acesso à instância.
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->conn; // Retorna a conexão PDO diretamente
    }
}