<?php
// Habilita a exibição de todos os erros para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json; charset=UTF-8");

// Testa apenas se o arquivo foi recebido com sucesso.
if (isset($_FILES['nfe_xml']) && $_FILES['nfe_xml']['error'] === UPLOAD_ERR_OK) {
    
    // Move o arquivo apenas para confirmar que ele é válido
    $temp_name = $_FILES['nfe_xml']['tmp_name'];
    $new_name = '/tmp/upload_test_' . time();
    move_uploaded_file($temp_name, $new_name);
    unlink($new_name); // Apaga o arquivo de teste

    echo json_encode(array('success' => true, 'message' => 'Arquivo recebido com sucesso pelo script de teste.'));

} else {
    
    http_response_code(400);
    echo json_encode(array('error' => 'Falha no recebimento do arquivo.', 'file_info' => $_FILES));

}
?>
