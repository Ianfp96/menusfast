<?php
require_once __DIR__ . '/../config/database.php';

// Script para actualizar estadísticas por hora
echo "<h2>Actualizando estadísticas por hora...</h2>";

try {
    // Verificar si la tabla hourly_activity existe
    $stmt = $conn->query("SHOW TABLES LIKE 'hourly_activity'");
    if (!$stmt->fetch()) {
        echo "<p style='color: red;'>Error: La tabla hourly_activity no existe.</p>";
        exit;
    }

    echo "<p>✅ Tabla hourly_activity encontrada.</p>";

    // Contar registros existentes
    $stmt = $conn->query("SELECT COUNT(*) as count FROM hourly_activity WHERE restaurant_id = 1");
    $existing_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>📊 Registros existentes en hourly_activity: $existing_count</p>";

    // Contar registros en page_views
    $stmt = $conn->query("SELECT COUNT(*) as count FROM page_views WHERE restaurant_id = 1");
    $page_views_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>📊 Registros en page_views: $page_views_count</p>";

    // Insertar/actualizar datos de actividad por hora
    $sql = "
        INSERT INTO hourly_activity (restaurant_id, hour_of_day, activity_date, page_views, unique_visitors, created_at, updated_at)
        SELECT 
            restaurant_id,
            HOUR(created_at) as hour_of_day,
            DATE(created_at) as activity_date,
            COUNT(*) as page_views,
            COUNT(DISTINCT ip_address) as unique_visitors,
            NOW() as created_at,
            NOW() as updated_at
        FROM page_views 
        WHERE restaurant_id = 1 
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY restaurant_id, HOUR(created_at), DATE(created_at)
        ON DUPLICATE KEY UPDATE
            page_views = VALUES(page_views),
            unique_visitors = VALUES(unique_visitors),
            updated_at = NOW()
    ";

    $stmt = $conn->prepare($sql);
    $result = $stmt->execute();
    
    if ($result) {
        echo "<p style='color: green;'>✅ Datos de actividad por hora actualizados correctamente.</p>";
    } else {
        echo "<p style='color: red;'>❌ Error al actualizar datos.</p>";
    }

    // Verificar los datos actualizados
    $stmt = $conn->prepare("
        SELECT 
            hour_of_day,
            SUM(page_views) as total_views,
            SUM(unique_visitors) as total_unique_visitors
        FROM hourly_activity 
        WHERE restaurant_id = 1 
            AND activity_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY hour_of_day
        ORDER BY hour_of_day ASC
    ");
    $stmt->execute();
    $hourly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>📈 Resumen de actividad por hora (últimos 7 días):</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Hora</th><th>Vistas Totales</th><th>Visitantes Únicos</th></tr>";
    
    foreach ($hourly_data as $data) {
        $hour = str_pad($data['hour_of_day'], 2, '0', STR_PAD_LEFT) . ':00';
        echo "<tr>";
        echo "<td>$hour</td>";
        echo "<td>{$data['total_views']}</td>";
        echo "<td>{$data['total_unique_visitors']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Contar registros después de la actualización
    $stmt = $conn->query("SELECT COUNT(*) as count FROM hourly_activity WHERE restaurant_id = 1");
    $new_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>📊 Registros en hourly_activity después de la actualización: $new_count</p>";

    echo "<p style='color: green;'>✅ Proceso completado. Ahora puedes revisar las estadísticas.</p>";
    echo "<p><a href='estadisticas.php'>← Volver a Estadísticas</a></p>";

} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?> 
