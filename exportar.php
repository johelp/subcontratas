<?php
require_once 'config/database.php';
verificarLogin();

if (!esAdmin()) {
    header('Location: index.php');
    exit;
}

$formato = isset($_GET['formato']) ? $_GET['formato'] : 'excel';
$tipo_reporte = isset($_GET['tipo']) ? $_GET['tipo'] : 'resumen';
$fecha_desde = isset($_GET['desde']) ? $_GET['desde'] : date('Y-m-01');
$fecha_hasta = isset($_GET['hasta']) ? $_GET['hasta'] : date('Y-m-t');
$contraparte_filtro = isset($_GET['contraparte']) ? (int)$_GET['contraparte'] : 0;

// Mismo query que reportes.php
$where = ["t.fecha BETWEEN ? AND ?"];
$params = [$fecha_desde, $fecha_hasta];
$types = 'ss';

if ($contraparte_filtro > 0) {
    $where[] = "t.contraparte_id = ?";
    $params[] = $contraparte_filtro;
    $types .= 'i';
}

if ($tipo_reporte === 'resumen') {
    $sql = "SELECT 
        c.nombre,
        c.tipo,
        SUM(CASE WHEN t.tipo = 'favor' THEN t.horas ELSE 0 END) as horas_favor,
        SUM(CASE WHEN t.tipo = 'contra' THEN t.horas ELSE 0 END) as horas_contra,
        COALESCE(SUM(p.horas_saldadas), 0) as horas_pagadas,
        COALESCE(SUM(p.monto), 0) as monto_pagado
        FROM contrapartes c
        LEFT JOIN transacciones t ON c.id = t.contraparte_id AND t.fecha BETWEEN ? AND ?
        LEFT JOIN pagos p ON c.id = p.contraparte_id AND p.fecha_pago BETWEEN ? AND ?
        WHERE c.activo = 1";
    
    if ($contraparte_filtro > 0) {
        $sql .= " AND c.id = ?";
    }
    
    $sql .= " GROUP BY c.id, c.nombre, c.tipo ORDER BY c.nombre";
    
    $stmt = $conn->prepare($sql);
    if ($contraparte_filtro > 0) {
        $stmt->bind_param('ssssi', $fecha_desde, $fecha_hasta, $fecha_desde, $fecha_hasta, $contraparte_filtro);
    } else {
        $stmt->bind_param('ssss', $fecha_desde, $fecha_hasta, $fecha_desde, $fecha_hasta);
    }
    
} else if ($tipo_reporte === 'detallado') {
    $sql = "SELECT t.fecha, c.nombre as contraparte_nombre, t.tipo, t.horas, t.disciplina, t.nivel, t.idiomas
            FROM transacciones t
            INNER JOIN contrapartes c ON t.contraparte_id = c.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY t.fecha DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
} else { // pagos
    $sql = "SELECT p.fecha_pago, c.nombre as contraparte_nombre, p.concepto, p.monto, p.horas_saldadas, p.quien_paga
            FROM pagos p
            INNER JOIN contrapartes c ON p.contraparte_id = c.id
            WHERE p.fecha_pago BETWEEN ? AND ?";
    
    if ($contraparte_filtro > 0) {
        $sql .= " AND p.contraparte_id = ?";
    }
    
    $sql .= " ORDER BY p.fecha_pago DESC";
    
    $stmt = $conn->prepare($sql);
    if ($contraparte_filtro > 0) {
        $stmt->bind_param('ssi', $fecha_desde, $fecha_hasta, $contraparte_filtro);
    } else {
        $stmt->bind_param('ss', $fecha_desde, $fecha_hasta);
    }
}

$stmt->execute();
$datos = $stmt->get_result();

if ($formato === 'excel') {
    // Exportar como CSV (Excel lo abre correctamente)
    $filename = "reporte_" . $tipo_reporte . "_" . date('Ymd_His') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Encabezados
    fputcsv($output, ['SNOW MOTION - Subcontratas'], ';');
    fputcsv($output, ['Período: ' . date('d/m/Y', strtotime($fecha_desde)) . ' - ' . date('d/m/Y', strtotime($fecha_hasta))], ';');
    fputcsv($output, ['Generado: ' . date('d/m/Y H:i')], ';');
    fputcsv($output, [], ';');
    
    if ($tipo_reporte === 'resumen') {
        fputcsv($output, ['Escuela/Autónomo', 'Tipo', 'Horas a Favor', 'Horas en Contra', 'Balance', 'Horas Pagadas', 'Monto Pagado', 'Pendiente'], ';');
        
        while ($row = $datos->fetch_assoc()) {
            $balance = $row['horas_favor'] - $row['horas_contra'] - $row['horas_pagadas'];
            fputcsv($output, [
                $row['nombre'],
                ucfirst($row['tipo']),
                number_format($row['horas_favor'], 1, ',', '.'),
                number_format($row['horas_contra'], 1, ',', '.'),
                number_format($row['horas_favor'] - $row['horas_contra'], 1, ',', '.'),
                number_format($row['horas_pagadas'], 1, ',', '.'),
                number_format($row['monto_pagado'], 2, ',', '.'),
                number_format($balance, 1, ',', '.')
            ], ';');
        }
        
    } else if ($tipo_reporte === 'detallado') {
        fputcsv($output, ['Fecha', 'Escuela/Autónomo', 'Tipo', 'Horas', 'Disciplina', 'Nivel', 'Idiomas'], ';');
        
        while ($row = $datos->fetch_assoc()) {
            fputcsv($output, [
                date('d/m/Y', strtotime($row['fecha'])),
                $row['contraparte_nombre'],
                $row['tipo'] === 'favor' ? 'A Favor' : 'En Contra',
                number_format($row['horas'], 1, ',', '.'),
                $row['disciplina'] ?: '-',
                $row['nivel'] ?: '-',
                $row['idiomas'] ?: '-'
            ], ';');
        }
        
    } else { // pagos
        fputcsv($output, ['Fecha', 'Escuela/Autónomo', 'Concepto', 'Monto', 'Horas Saldadas', 'Quien Paga'], ';');
        
        while ($row = $datos->fetch_assoc()) {
            fputcsv($output, [
                date('d/m/Y', strtotime($row['fecha_pago'])),
                $row['contraparte_nombre'],
                $row['concepto'],
                number_format($row['monto'], 2, ',', '.'),
                number_format($row['horas_saldadas'], 1, ',', '.'),
                $row['quien_paga'] === 'ellos' ? 'Nos pagan' : 'Pagamos'
            ], ';');
        }
    }
    
    fclose($output);
    exit;
    
} else if ($formato === 'pdf') {
    // PDF simple con HTML
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; font-size: 10pt; }
            .header { text-align: center; margin-bottom: 20px; }
            .header h1 { margin: 0; font-size: 16pt; }
            .header p { margin: 5px 0; color: #666; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th { background: #333; color: white; padding: 8px; text-align: left; font-size: 9pt; }
            td { padding: 6px; border-bottom: 1px solid #ddd; font-size: 9pt; }
            tr:hover { background: #f5f5f5; }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 8pt; }
            .badge-success { background: #28a745; color: white; }
            .badge-danger { background: #dc3545; color: white; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>SNOW MOTION - Subcontratas</h1>
            <p>Período: ' . date('d/m/Y', strtotime($fecha_desde)) . ' - ' . date('d/m/Y', strtotime($fecha_hasta)) . '</p>
            <p>Generado: ' . date('d/m/Y H:i') . '</p>
        </div>
        
        <table>';
    
    if ($tipo_reporte === 'resumen') {
        $html .= '<thead><tr>
            <th>Escuela/Autónomo</th>
            <th class="text-center">A Favor</th>
            <th class="text-center">En Contra</th>
            <th class="text-center">Balance</th>
            <th class="text-right">Pagado</th>
            <th class="text-center">Pendiente</th>
        </tr></thead><tbody>';
        
        while ($row = $datos->fetch_assoc()) {
            $balance = $row['horas_favor'] - $row['horas_contra'] - $row['horas_pagadas'];
            $html .= '<tr>
                <td><strong>' . htmlspecialchars($row['nombre']) . '</strong><br><small>' . ucfirst($row['tipo']) . '</small></td>
                <td class="text-center">' . number_format($row['horas_favor'], 1) . 'h</td>
                <td class="text-center">' . number_format($row['horas_contra'], 1) . 'h</td>
                <td class="text-center"><strong>' . number_format($row['horas_favor'] - $row['horas_contra'], 1) . 'h</strong></td>
                <td class="text-right">' . number_format($row['monto_pagado'], 2) . '€</td>
                <td class="text-center">' . number_format($balance, 1) . 'h</td>
            </tr>';
        }
        
    } else if ($tipo_reporte === 'detallado') {
        $html .= '<thead><tr>
            <th>Fecha</th>
            <th>Escuela/Autónomo</th>
            <th>Tipo</th>
            <th class="text-center">Horas</th>
            <th>Disciplina</th>
            <th>Nivel</th>
        </tr></thead><tbody>';
        
        while ($row = $datos->fetch_assoc()) {
            $badge_class = $row['tipo'] === 'favor' ? 'badge-success' : 'badge-danger';
            $badge_text = $row['tipo'] === 'favor' ? 'A Favor' : 'En Contra';
            
            $html .= '<tr>
                <td>' . date('d/m/Y', strtotime($row['fecha'])) . '</td>
                <td>' . htmlspecialchars($row['contraparte_nombre']) . '</td>
                <td><span class="badge ' . $badge_class . '">' . $badge_text . '</span></td>
                <td class="text-center"><strong>' . number_format($row['horas'], 1) . 'h</strong></td>
                <td>' . ($row['disciplina'] ? ucfirst($row['disciplina']) : '-') . '</td>
                <td>' . ($row['nivel'] ? ucfirst($row['nivel']) : '-') . '</td>
            </tr>';
        }
        
    } else { // pagos
        $html .= '<thead><tr>
            <th>Fecha</th>
            <th>Escuela/Autónomo</th>
            <th>Concepto</th>
            <th class="text-right">Monto</th>
            <th class="text-center">Horas</th>
        </tr></thead><tbody>';
        
        while ($row = $datos->fetch_assoc()) {
            $html .= '<tr>
                <td>' . date('d/m/Y', strtotime($row['fecha_pago'])) . '</td>
                <td>' . htmlspecialchars($row['contraparte_nombre']) . '</td>
                <td>' . htmlspecialchars($row['concepto']) . '</td>
                <td class="text-right"><strong>' . number_format($row['monto'], 2) . '€</strong></td>
                <td class="text-center">' . number_format($row['horas_saldadas'], 1) . 'h</td>
            </tr>';
        }
    }
    
    $html .= '</tbody></table></body></html>';
    
    // Convertir HTML a PDF usando wkhtmltopdf (si está instalado) o mostrar HTML para imprimir
    $filename = "reporte_" . $tipo_reporte . "_" . date('Ymd_His') . ".pdf";
    
    // Intentar usar wkhtmltopdf
    if (shell_exec('which wkhtmltopdf')) {
        $temp_html = tempnam(sys_get_temp_dir(), 'html');
        file_put_contents($temp_html, $html);
        
        $temp_pdf = tempnam(sys_get_temp_dir(), 'pdf');
        shell_exec("wkhtmltopdf $temp_html $temp_pdf 2>&1");
        
        if (file_exists($temp_pdf) && filesize($temp_pdf) > 0) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            readfile($temp_pdf);
            unlink($temp_html);
            unlink($temp_pdf);
            exit;
        }
    }
    
    // Fallback: mostrar HTML para imprimir como PDF desde el navegador
    echo $html;
    echo '<script>window.print();</script>';
    exit;
}
?>