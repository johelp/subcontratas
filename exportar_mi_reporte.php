<?php
require_once 'config/database.php';
verificarLogin();

if (esAdmin()) {
    header('Location: index.php');
    exit;
}

// Obtener contraparte vinculada
$sql = "SELECT c.* FROM contrapartes c WHERE c.usuario_id = ? AND c.activo = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $_SESSION['usuario_id']);
$stmt->execute();
$mi_contraparte = $stmt->get_result()->fetch_assoc();

if (!$mi_contraparte) {
    die("Error: Usuario no vinculado");
}

$contraparte_id = $mi_contraparte['id'];
$contraparte_nombre = $mi_contraparte['nombre'];

// Balance general
$sql = "SELECT 
    COALESCE(SUM(CASE WHEN tipo = 'favor' THEN horas ELSE 0 END), 0) as favor,
    COALESCE(SUM(CASE WHEN tipo = 'contra' THEN horas ELSE 0 END), 0) as contra
    FROM transacciones WHERE contraparte_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $contraparte_id);
$stmt->execute();
$balance = $stmt->get_result()->fetch_assoc();

// Total pagado
$sql = "SELECT COALESCE(SUM(horas_saldadas), 0) as horas_pagadas, 
        COALESCE(SUM(monto), 0) as monto_total
        FROM pagos WHERE contraparte_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $contraparte_id);
$stmt->execute();
$pagos_total = $stmt->get_result()->fetch_assoc();

// Balance mensual
$sql = "SELECT 
    DATE_FORMAT(fecha, '%Y-%m') as mes,
    SUM(CASE WHEN tipo = 'favor' THEN horas ELSE 0 END) as favor,
    SUM(CASE WHEN tipo = 'contra' THEN horas ELSE 0 END) as contra
    FROM transacciones 
    WHERE contraparte_id = ?
    GROUP BY DATE_FORMAT(fecha, '%Y-%m')
    ORDER BY mes DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $contraparte_id);
$stmt->execute();
$balance_mensual = $stmt->get_result();

// Todas las transacciones
$sql = "SELECT * FROM transacciones WHERE contraparte_id = ? ORDER BY fecha DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $contraparte_id);
$stmt->execute();
$transacciones = $stmt->get_result();

// Todos los pagos
$sql = "SELECT * FROM pagos WHERE contraparte_id = ? ORDER BY fecha_pago DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $contraparte_id);
$stmt->execute();
$pagos = $stmt->get_result();

// Exportar como CSV
$filename = "reporte_" . preg_replace('/[^A-Za-z0-9]/', '_', $contraparte_nombre) . "_" . date('Ymd') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// BOM para UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Encabezado
fputcsv($output, ['SNOW MOTION - Reporte de Colaboración'], ';');
fputcsv($output, [$contraparte_nombre], ';');
fputcsv($output, ['Generado: ' . date('d/m/Y H:i')], ';');
fputcsv($output, [], ';');

// Resumen general
fputcsv($output, ['RESUMEN GENERAL'], ';');
fputcsv($output, ['Horas Cedidas', 'Horas Solicitadas', 'Balance Bruto', 'Horas Pagadas', 'Balance Final', 'Monto Pagado'], ';');
$balance_bruto = $balance['favor'] - $balance['contra'];
$balance_final = $balance_bruto - $pagos_total['horas_pagadas'];
fputcsv($output, [
    number_format($balance['favor'], 1, ',', '.'),
    number_format($balance['contra'], 1, ',', '.'),
    number_format($balance_bruto, 1, ',', '.'),
    number_format($pagos_total['horas_pagadas'], 1, ',', '.'),
    number_format($balance_final, 1, ',', '.'),
    number_format($pagos_total['monto_total'], 2, ',', '.')
], ';');
fputcsv($output, [], ';');

// Balance mensual
fputcsv($output, ['BALANCE MENSUAL'], ';');
fputcsv($output, ['Mes', 'Horas Cedidas', 'Horas Solicitadas', 'Balance Mes'], ';');
while ($bm = $balance_mensual->fetch_assoc()) {
    $mes_nombre = strftime('%B %Y', strtotime($bm['mes'] . '-01'));
    $balance_mes = $bm['favor'] - $bm['contra'];
    fputcsv($output, [
        ucfirst($mes_nombre),
        number_format($bm['favor'], 1, ',', '.'),
        number_format($bm['contra'], 1, ',', '.'),
        number_format($balance_mes, 1, ',', '.')
    ], ';');
}
fputcsv($output, [], ';');

// Detalle de transacciones
fputcsv($output, ['DETALLE DE TRANSACCIONES'], ';');
fputcsv($output, ['Fecha', 'Tipo', 'Horas', 'Disciplina', 'Nivel', 'Idiomas', 'Notas'], ';');
while ($t = $transacciones->fetch_assoc()) {
    fputcsv($output, [
        date('d/m/Y', strtotime($t['fecha'])),
        $t['tipo'] === 'favor' ? 'Cedimos' : 'Solicitamos',
        number_format($t['horas'], 1, ',', '.'),
        $t['disciplina'] ?: '-',
        $t['nivel'] ?: '-',
        $t['idiomas'] ?: '-',
        $t['notas'] ?: '-'
    ], ';');
}
fputcsv($output, [], ';');

// Detalle de pagos
if ($pagos->num_rows > 0) {
    fputcsv($output, ['DETALLE DE PAGOS'], ';');
    fputcsv($output, ['Fecha', 'Concepto', 'Monto', 'Horas Saldadas', 'Quien Paga'], ';');
    while ($p = $pagos->fetch_assoc()) {
        fputcsv($output, [
            date('d/m/Y', strtotime($p['fecha_pago'])),
            $p['concepto'],
            number_format($p['monto'], 2, ',', '.'),
            number_format($p['horas_saldadas'], 1, ',', '.'),
            $p['quien_paga'] === 'ellos' ? 'SNOW MOTION nos paga' : 'Pagamos a SNOW MOTION'
        ], ';');
    }
}

fclose($output);
exit;
?>