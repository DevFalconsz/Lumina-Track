<?php

// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'TheFalconsz1*');
define('DB_NAME', 'lumina_track');

// Função para conectar ao banco de dados
function connectDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
	    if ($conn->connect_error) {
	        // Em caso de falha, retorna uma resposta JSON de erro
	        header("Content-Type: application/json; charset=UTF-8");
	        http_response_code(500);
	        echo json_encode(array('error' => 'Falha na conexão com o banco de dados.'));
	        exit();
	    }
    return $conn;
}



