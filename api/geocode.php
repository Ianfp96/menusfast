<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Methods: GET');

// Verificar que la petición sea GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Verificar que se proporcionó una dirección
if (empty($_GET['address'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Se requiere una dirección']);
    exit;
}

$address = urlencode($_GET['address']);

// Configurar la petición a Nominatim
$url = "https://nominatim.openstreetmap.org/search?format=json&q={$address}&limit=1";
$options = [
    'http' => [
        'method' => 'GET',
        'header' => [
            'User-Agent: WebMenu/1.0',
            'Accept-Language: es'
        ]
    ]
];

try {
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        throw new Exception('Error al obtener datos de Nominatim');
    }
    
    $data = json_decode($response, true);
    
    if (empty($data)) {
        echo json_encode([
            'success' => false,
            'error' => 'No se encontró la ubicación'
        ]);
        exit;
    }
    
    // Devolver solo los datos necesarios
    echo json_encode([
        'success' => true,
        'lat' => $data[0]['lat'],
        'lon' => $data[0]['lon'],
        'display_name' => $data[0]['display_name']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al procesar la solicitud: ' . $e->getMessage()
    ]);
} 
