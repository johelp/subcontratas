<?php
require_once 'config/database.php';
verificarLogin();

if (!esAdmin()) {
    header('Location: index.php');
    exit;
}

// Obtener filtros
$fecha_desde = isset($_GET['desde']) ? $_GET['desde'] : date('Y-m-01');
$fecha_hasta = isset($_GET['hasta']) ? $_GET['hasta'] : date('Y-m-t');
$contraparte_filtro = isset($_GET['contraparte']) ? (int)$_GET['contraparte'] : 0;
$tipo_reporte = isset($_GET['tipo']) ? $_GET['tipo'] : 'resumen';
$incluir_detalle = isset($_GET['incluir_detalle']) ? true : false;

// Obtener contrapartes para filtro
$sql = "SELECT id, nombre FROM contrapartes WHERE activo = 1 ORDER BY nombre";
$contrapartes_lista = $conn->query($sql);

// Construir query según tipo de reporte
$where = ["t.fecha BETWEEN ? AND ?"];
$params = [$fecha_desde, $fecha_hasta];
$types = 'ss';

if ($contraparte_filtro > 0) {
    $where[] = "t.contraparte_id = ?";
    $params[] = $contraparte_filtro;
    $types .= 'i';
}

if ($tipo_reporte === 'resumen') {
    // Resumen por contraparte
    $sql = "SELECT 
        c.id,
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
    $stmt->execute();
    $datos = $stmt->get_result();
    
} else if ($tipo_reporte === 'detallado') {
    // Detalle de transacciones
    $sql = "SELECT t.*, c.nombre as contraparte_nombre, c.tipo as contraparte_tipo
            FROM transacciones t
            INNER JOIN contrapartes c ON t.contraparte_id = c.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY t.fecha DESC, t.id DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $datos = $stmt->get_result();
    
} else if ($tipo_reporte === 'pagos') {
    // Detalle de pagos
    $sql = "SELECT p.*, c.nombre as contraparte_nombre, c.tipo as contraparte_tipo
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
    $stmt->execute();
    $datos = $stmt->get_result();
}

// Calcular totales generales
$sql_totales = "SELECT 
    SUM(CASE WHEN tipo = 'favor' THEN horas ELSE 0 END) as favor,
    SUM(CASE WHEN tipo = 'contra' THEN horas ELSE 0 END) as contra
    FROM transacciones WHERE fecha BETWEEN ? AND ?";
if ($contraparte_filtro > 0) {
    $sql_totales .= " AND contraparte_id = ?";
}
$stmt = $conn->prepare($sql_totales);
if ($contraparte_filtro > 0) {
    $stmt->bind_param('ssi', $fecha_desde, $fecha_hasta, $contraparte_filtro);
} else {
    $stmt->bind_param('ss', $fecha_desde, $fecha_hasta);
}
$stmt->execute();
$totales = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - SNOW MOTION</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="bi bi-file-earmark-text"></i> Reportes</h4>
            <div class="btn-group">
                <a href="exportar.php?tipo=<?php echo $tipo_reporte; ?>&desde=<?php echo $fecha_desde; ?>&hasta=<?php echo $fecha_hasta; ?>&contraparte=<?php echo $contraparte_filtro; ?>&formato=excel" 
                   class="btn btn-success">
                    <i class="bi bi-file-earmark-excel"></i> Exportar Excel
                </a>
                <a href="exportar.php?tipo=<?php echo $tipo_reporte; ?>&desde=<?php echo $fecha_desde; ?>&hasta=<?php echo $fecha_hasta; ?>&contraparte=<?php echo $contraparte_filtro; ?>&formato=pdf" 
                   class="btn btn-danger">
                    <i class="bi bi-file-earmark-pdf"></i> Exportar PDF
                </a>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Tipo de Reporte</label>
                        <select name="tipo" class="form-select" id="tipoReporte">
                            <option value="resumen" <?php echo $tipo_reporte === 'resumen' ? 'selected' : ''; ?>>Resumen por Escuela</option>
                            <option value="detallado" <?php echo $tipo_reporte === 'detallado' ? 'selected' : ''; ?>>Detalle de Transacciones</option>
                            <option value="pagos" <?php echo $tipo_reporte === 'pagos' ? 'selected' : ''; ?>>Detalle de Pagos</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Desde</label>
                        <input type="date" name="desde" class="form-control" value="<?php echo $fecha_desde; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Hasta</label>
                        <input type="date" name="hasta" class="form-control" value="<?php echo $fecha_hasta; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Escuela/Autónomo</label>
                        <select name="contraparte" class="form-select">
                            <option value="0">Todas</option>
                            <?php while ($cp = $contrapartes_lista->fetch_assoc()): ?>
                                <option value="<?php echo $cp['id']; ?>" <?php echo $contraparte_filtro == $cp['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cp['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-dark w-100">
                            <i class="bi bi-funnel"></i>
                        </button>
                    </div>
                    <div class="col-12" id="opcionDetalle" style="display: <?php echo $tipo_reporte === 'resumen' ? 'block' : 'none'; ?>;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="incluir_detalle" id="incluirDetalle" 
                                   <?php echo $incluir_detalle ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="incluirDetalle">
                                <i class="bi bi-list-ul me-1"></i>Incluir detalle de transacciones por escuela
                            </label>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Totales -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card">
                    <div class="stat-label">Horas a Favor</div>
                    <div class="stat-value text-success"><?php echo number_format($totales['favor'] ?? 0, 1); ?>h</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card">
                    <div class="stat-label">Horas en Contra</div>
                    <div class="stat-value text-danger"><?php echo number_format($totales['contra'] ?? 0, 1); ?>h</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card">
                    <div class="stat-label">Balance del Período</div>
                    <?php $balance_periodo = ($totales['favor'] ?? 0) - ($totales['contra'] ?? 0); ?>
                    <div class="stat-value <?php echo $balance_periodo >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo $balance_periodo >= 0 ? '+' : ''; ?><?php echo number_format($balance_periodo, 1); ?>h
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabla de Datos -->
        <div class="card">
            <div class="card-header">
                <?php 
                $titulos = [
                    'resumen' => 'Resumen por Escuela/Autónomo',
                    'detallado' => 'Detalle de Transacciones',
                    'pagos' => 'Detalle de Pagos'
                ];
                echo $titulos[$tipo_reporte] ?? 'Reporte';
                ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php if ($tipo_reporte === 'resumen'): ?>
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Escuela/Autónomo</th>
                                    <th class="text-center">A Favor</th>
                                    <th class="text-center">En Contra</th>
                                    <th class="text-center">Balance</th>
                                    <th class="text-end">Pagado</th>
                                    <th class="text-center">Pendiente</th>
                                    <?php if ($incluir_detalle): ?>
                                    <th class="text-center">Detalle</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($datos->num_rows > 0): ?>
                                    <?php 
                                    $contador = 0;
                                    while ($row = $datos->fetch_assoc()): 
                                        $balance = $row['horas_favor'] - $row['horas_contra'] - $row['horas_pagadas'];
                                        $contador++;
                                        
                                        // Si incluir_detalle, obtener transacciones
                                        $transacciones_detalle = [];
                                        if ($incluir_detalle && ($row['horas_favor'] > 0 || $row['horas_contra'] > 0)) {
                                            $sql_trans = "SELECT * FROM transacciones 
                                                         WHERE contraparte_id = ? AND fecha BETWEEN ? AND ?
                                                         ORDER BY fecha DESC";
                                            $stmt_trans = $conn->prepare($sql_trans);
                                            $stmt_trans->bind_param('iss', $row['id'], $fecha_desde, $fecha_hasta);
                                            $stmt_trans->execute();
                                            $transacciones_detalle = $stmt_trans->get_result()->fetch_all(MYSQLI_ASSOC);
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($row['nombre']); ?></strong>
                                            <br><small class="text-muted"><?php echo ucfirst($row['tipo']); ?></small>
                                        </td>
                                        <td class="text-center text-success"><strong><?php echo number_format($row['horas_favor'], 1); ?>h</strong></td>
                                        <td class="text-center text-danger"><strong><?php echo number_format($row['horas_contra'], 1); ?>h</strong></td>
                                        <td class="text-center">
                                            <strong class="<?php echo $balance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $balance >= 0 ? '+' : ''; ?><?php echo number_format($balance, 1); ?>h
                                            </strong>
                                        </td>
                                        <td class="text-end"><?php echo number_format($row['monto_pagado'], 2); ?>€</td>
                                        <td class="text-center">
                                            <?php if (abs($balance) > 0): ?>
                                                <span class="badge bg-warning text-dark"><?php echo number_format(abs($balance), 1); ?>h</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Saldado</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($incluir_detalle): ?>
                                        <td class="text-center">
                                            <?php if (count($transacciones_detalle) > 0): ?>
                                                <button class="btn btn-sm btn-outline-primary" type="button" 
                                                        data-bs-toggle="collapse" data-bs-target="#detalle<?php echo $contador; ?>">
                                                    <i class="bi bi-chevron-down"></i> Ver (<?php echo count($transacciones_detalle); ?>)
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php if ($incluir_detalle && count($transacciones_detalle) > 0): ?>
                                    <tr>
                                        <td colspan="<?php echo $incluir_detalle ? '7' : '6'; ?>" class="p-0 border-0">
                                            <div class="collapse" id="detalle<?php echo $contador; ?>">
                                                <div class="card card-body bg-light m-2">
                                                    <h6 class="mb-3"><i class="bi bi-list-ul"></i> Detalle de Transacciones</h6>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-bordered mb-0">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th>Fecha</th>
                                                                    <th>Tipo</th>
                                                                    <th>Horas</th>
                                                                    <th>Disciplina</th>
                                                                    <th>Nivel</th>
                                                                    <th>Idiomas</th>
                                                                    <th>Notas</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($transacciones_detalle as $trans): ?>
                                                                <tr>
                                                                    <td><?php echo date('d/m/Y', strtotime($trans['fecha'])); ?></td>
                                                                    <td>
                                                                        <span class="badge badge-<?php echo $trans['tipo'] === 'favor' ? 'favor' : 'contra'; ?>">
                                                                            <?php echo $trans['tipo'] === 'favor' ? 'Favor' : 'Contra'; ?>
                                                                        </span>
                                                                    </td>
                                                                    <td><strong><?php echo number_format($trans['horas'], 1); ?>h</strong></td>
                                                                    <td><?php echo $trans['disciplina'] ? ucfirst($trans['disciplina']) : '-'; ?></td>
                                                                    <td><?php echo $trans['nivel'] ? ucfirst($trans['nivel']) : '-'; ?></td>
                                                                    <td><?php echo $trans['idiomas'] ?: '-'; ?></td>
                                                                    <td><small><?php echo $trans['notas'] ? htmlspecialchars($trans['notas']) : '-'; ?></small></td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="<?php echo $incluir_detalle ? '7' : '6'; ?>" class="text-center text-muted py-4">No hay datos para mostrar</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php elseif ($tipo_reporte === 'detallado'): ?>
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Escuela/Autónomo</th>
                                    <th>Tipo</th>
                                    <th class="text-center">Horas</th>
                                    <th>Disciplina</th>
                                    <th>Nivel</th>
                                    <th>Idiomas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($datos->num_rows > 0): ?>
                                    <?php while ($row = $datos->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($row['fecha'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['contraparte_nombre']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $row['tipo'] === 'favor' ? 'badge-favor' : 'badge-contra'; ?>">
                                                <?php echo $row['tipo'] === 'favor' ? 'A Favor' : 'En Contra'; ?>
                                            </span>
                                        </td>
                                        <td class="text-center"><strong><?php echo number_format($row['horas'], 1); ?>h</strong></td>
                                        <td><?php echo $row['disciplina'] ? ucfirst($row['disciplina']) : '-'; ?></td>
                                        <td><?php echo $row['nivel'] ? ucfirst($row['nivel']) : '-'; ?></td>
                                        <td><?php echo $row['idiomas'] ?: '-'; ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">No hay transacciones en este período</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php else: // pagos ?>
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Escuela/Autónomo</th>
                                    <th>Concepto</th>
                                    <th class="text-end">Monto</th>
                                    <th class="text-center">Horas Saldadas</th>
                                    <th>Quien Paga</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($datos->num_rows > 0): ?>
                                    <?php while ($row = $datos->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($row['fecha_pago'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['contraparte_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($row['concepto']); ?></td>
                                        <td class="text-end"><strong><?php echo number_format($row['monto'], 2); ?>€</strong></td>
                                        <td class="text-center"><?php echo number_format($row['horas_saldadas'], 1); ?>h</td>
                                        <td>
                                            <span class="badge bg-<?php echo $row['quien_paga'] === 'ellos' ? 'success' : 'danger'; ?>">
                                                <?php echo $row['quien_paga'] === 'ellos' ? 'Nos pagan' : 'Pagamos'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center text-muted py-4">No hay pagos en este período</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mostrar/ocultar checkbox de detalle según tipo de reporte
        document.getElementById('tipoReporte').addEventListener('change', function() {
            const opcionDetalle = document.getElementById('opcionDetalle');
            if (this.value === 'resumen') {
                opcionDetalle.style.display = 'block';
            } else {
                opcionDetalle.style.display = 'none';
            }
        });
    </script>
</body>
</html>