<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Subscription.php';

// Inicializar la conexión a la base de datos
$db = new Database();
$subscription = new Subscription($db);

// Verificar suscripciones expiradas
$expiredCount = $subscription->checkExpiredSubscriptions();

// Registrar en el log
$logMessage = date('Y-m-d H:i:s') . " - Se procesaron $expiredCount suscripciones expiradas\n";
file_put_contents(__DIR__ . '/subscription_check.log', $logMessage, FILE_APPEND);

echo "Proceso completado. Se procesaron $expiredCount suscripciones expiradas.\n"; 
