<?php
// Valores generales; las credenciales locales se cargan desde mail.local.php.
$config = [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'username' => getenv('DOMIPIZZAS_SMTP_USER') ?: 'nicolasdominguez180804@gmail.com',
    'password' => getenv('DOMIPIZZAS_SMTP_PASSWORD') ?: '',
    'admin_email' => 'nicolasdominguez180804@gmail.com',
    'from_name' => 'DomiPizzas',
    'recovery_subject' => 'DomiPizzas: solicitud de recuperación de contraseña',
    // {username} se sustituye por el nombre ingresado en el formulario.
    'recovery_body' => <<<'MENSAJE'
Hola, administrador:

Se recibió una solicitud de recuperación de acceso a DomiPizzas.
Usuario ingresado: {username}

Por favor, verificá la identidad del solicitante y gestioná el restablecimiento de su contraseña.
La solicitud no confirma que el usuario exista ni cambia su contraseña automáticamente.

Contacto corporativo por WhatsApp: 0972 957385
Sistema DomiPizzas
MENSAJE,

];

$localFile = __DIR__ . '/mail.local.php';
if (is_file($localFile)) {
    $config = array_replace($config, require $localFile);
}
return $config;
