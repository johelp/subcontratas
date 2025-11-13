<?php
require_once 'config/database.php';
verificarLogin();

// Los admin no deberían estar aquí
if (esAdmin()) {
    header('Location: index.php');
    exit;
}

// Obtener contraparte vinculada a este usuario
$sql = "SELECT c.* FROM contrapartes c WHERE c.usuario_id = ? AND c.activo = 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $_SESSION['usuario_id']);
$stmt->execute();
$mi_contraparte = $stmt->get_result()->fetch_assoc();

if (!$mi_contraparte) {
    die("Error: Usuario no vinculado a ninguna escuela/autónomo");
}

$contraparte_id = $mi_contraparte['id'];

// Filtros de fecha
$mes_filtro = isset($_GET['mes']) ? $_GET['mes'] : date('Y-m');

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
$sql = "SELECT 
    COALESCE(SUM(horas_saldadas), 0) as horas_pagadas, 
    COALESCE(SUM(monto), 0) as monto_total
    FROM pagos WHERE contraparte_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $contraparte_id);
$stmt->execute();
$pagos_total = $stmt->get_result()->fetch_assoc();

$balance_final = $balance['favor'] - $balance['contra'] - $pagos_total['horas_pagadas'];

// Balance mensual
$sql = "SELECT 
    DATE_FORMAT(fecha, '%Y-%m') as mes,
    SUM(CASE WHEN tipo = 'favor' THEN horas ELSE 0 END) as favor,
    SUM(CASE WHEN tipo = 'contra' THEN horas ELSE 0 END) as contra
    FROM transacciones 
    WHERE contraparte_id = ?
    GROUP BY DATE_FORMAT(fecha, '%Y-%m')
    ORDER BY mes DESC
    LIMIT 12";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $contraparte_id);
$stmt->execute();
$balance_mensual = $stmt->get_result();

// Transacciones del mes seleccionado
$sql = "SELECT * FROM transacciones 
        WHERE contraparte_id = ? AND DATE_FORMAT(fecha, '%Y-%m') = ?
        ORDER BY fecha DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('is', $contraparte_id, $mes_filtro);
$stmt->execute();
$transacciones = $stmt->get_result();

// Totales del mes
$sql = "SELECT 
    SUM(CASE WHEN tipo = 'favor' THEN horas ELSE 0 END) as favor,
    SUM(CASE WHEN tipo = 'contra' THEN horas ELSE 0 END) as contra
    FROM transacciones WHERE contraparte_id = ? AND DATE_FORMAT(fecha, '%Y-%m') = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('is', $contraparte_id, $mes_filtro);
$stmt->execute();
$totales_mes = $stmt->get_result()->fetch_assoc();

// Pagos del año actual
$sql = "SELECT * FROM pagos 
        WHERE contraparte_id = ? AND YEAR(fecha_pago) = YEAR(CURDATE())
        ORDER BY fecha_pago DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $contraparte_id);
$stmt->execute();
$pagos = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Reporte - SNOW MOTION</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="mb-4">
            <h4>
                <i class="bi bi-building"></i> 
                <?php echo htmlspecialchars($mi_contraparte['nombre']); ?>
            </h4>
            <p class="text-muted mb-0">Resumen de colaboración con SNOW MOTION</p>
        </div>
        
        <!-- Balance Destacado -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white p-4">
                        <div class="row align-items-center">
                            <div class="col-7">
                                <h6 class="text-white-50 mb-2">BALANCE ACTUAL</h6>
                                <h1 class="display-4 fw-bold mb-0">
                                    <?php echo $balance_final >= 0 ? '+' : ''; ?><?php echo number_format($balance_final, 1); ?><small class="fs-4">h</small>
                                </h1>
                                <p class="mb-0 mt-2 text-white-50">
                                    <?php 
                                    if ($balance_final > 0) {
                                        echo "SNOW MOTION nos debe";
                                    } else if ($balance_final < 0) {
                                        echo "Debemos a SNOW MOTION";
                                    } else {
                                        echo "Cuenta saldada";
                                    }
                                    ?>
                                </p>
                            </div>
                            <div class="col-5 text-end">
                                <div class="mb-3">
                                    <small class="text-white-50 d-block">Cedimos</small>
                                    <h4 class="mb-0"><?php echo number_format($balance['favor'], 1); ?>h</h4>
                                </div>
                                <div>
                                    <small class="text-white-50 d-block">Solicitamos</small>
                                    <h4 class="mb-0"><?php echo number_format($balance['contra'], 1); ?>h</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body text-center d-flex flex-column justify-content-center">
                        <i class="bi bi-cash-coin display-4 text-muted mb-3"></i>
                        <h6 class="text-muted mb-2">Total Pagado</h6>
                        <h3 class="mb-0"><?php echo number_format($pagos_total['monto_total'], 2); ?>€</h3>
                        <small class="text-muted"><?php echo number_format($pagos_total['horas_pagadas'], 1); ?>h saldadas</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3" id="reporteTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="mensual-tab" data-bs-toggle="tab" data-bs-target="#mensual" type="button">
                    <i class="bi bi-calendar3"></i> Balance Mensual
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="transacciones-tab" data-bs-toggle="tab" data-bs-target="#transacciones-tab-pane" type="button">
                    <i class="bi bi-clock-history"></i> Transacciones
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pagos-tab" data-bs-toggle="tab" data-bs-target="#pagos-tab-pane" type="button">
                    <i class="bi bi-cash-stack"></i> Pagos
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="reporteTabsContent">
            <!-- Balance Mensual -->
            <div class="tab-pane fade show active" id="mensual" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <?php if ($balance_mensual->num_rows > 0): ?>
                            <?php while ($bm = $balance_mensual->fetch_assoc()): 
                                $balance_mes = $bm['favor'] - $bm['contra'];
                                $mes_nombre = strftime('%B %Y', strtotime($bm['mes'] . '-01'));
                            ?>
                            <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                                <div>
                                    <h6 class="mb-1"><?php echo ucfirst($mes_nombre); ?></h6>
                                    <small class="text-muted">
                                        <span class="text-success"><?php echo number_format($bm['favor'], 1); ?>h cedimos</span> • 
                                        <span class="text-danger"><?php echo number_format($bm['contra'], 1); ?>h solicitamos</span>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <h5 class="mb-0 <?php echo $balance_mes >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $balance_mes >= 0 ? '+' : ''; ?><?php echo number_format($balance_mes, 1); ?>h
                                    </h5>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-x display-4"></i>
                                <p class="mt-3">No hay movimientos registrados</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Transacciones -->
            <div class="tab-pane fade" id="transacciones-tab-pane" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                Transacciones
                            </div>
                            <div class="col-md-4">
                                <input type="month" class="form-control form-control-sm" 
                                       value="<?php echo $mes_filtro; ?>" 
                                       onchange="window.location.href='mi_reporte.php?mes='+this.value">
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Totales del mes -->
                        <div class="row g-2 mb-3">
                            <div class="col-4">
                                <div class="text-center p-2 bg-light rounded">
                                    <small class="text-muted d-block">Cedimos</small>
                                    <strong class="text-success"><?php echo number_format($totales_mes['favor'] ?? 0, 1); ?>h</strong>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-center p-2 bg-light rounded">
                                    <small class="text-muted d-block">Solicitamos</small>
                                    <strong class="text-danger"><?php echo number_format($totales_mes['contra'] ?? 0, 1); ?>h</strong>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-center p-2 bg-light rounded">
                                    <small class="text-muted d-block">Balance</small>
                                    <?php 
                                    $balance_mes_sel = ($totales_mes['favor'] ?? 0) - ($totales_mes['contra'] ?? 0);
                                    ?>
                                    <strong class="<?php echo $balance_mes_sel >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $balance_mes_sel >= 0 ? '+' : ''; ?><?php echo number_format($balance_mes_sel, 1); ?>h
                                    </strong>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($transacciones->num_rows > 0): ?>
                            <div class="list-group">
                                <?php while ($t = $transacciones->fetch_assoc()): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <span class="badge <?php echo $t['tipo'] === 'favor' ? 'badge-favor' : 'badge-contra'; ?>">
                                                    <?php echo $t['tipo'] === 'favor' ? 'Cedimos' : 'Solicitamos'; ?>
                                                </span>
                                                <strong><?php echo number_format($t['horas'], 1); ?>h</strong>
                                            </div>
                                            <small class="text-muted d-block">
                                                <i class="bi bi-calendar3"></i> <?php echo date('d/m/Y', strtotime($t['fecha'])); ?>
                                            </small>
                                            <?php if ($t['disciplina'] || $t['nivel'] || $t['idiomas']): ?>
                                                <small class="text-muted d-block mt-1">
                                                    <?php if ($t['disciplina']): ?>
                                                        <span class="badge bg-light text-dark"><?php echo ucfirst($t['disciplina']); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($t['nivel']): ?>
                                                        <span class="badge bg-light text-dark"><?php echo ucfirst($t['nivel']); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($t['idiomas']): ?>
                                                        <span class="badge bg-light text-dark"><?php echo $t['idiomas']; ?></span>
                                                    <?php endif; ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                No hay transacciones en este mes
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Pagos -->
            <div class="tab-pane fade" id="pagos-tab-pane" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <?php if ($pagos->num_rows > 0): ?>
                            <div class="list-group">
                                <?php while ($p = $pagos->fetch_assoc()): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($p['concepto']); ?></h6>
                                            <small class="text-muted">
                                                <i class="bi bi-calendar3"></i> <?php echo date('d/m/Y', strtotime($p['fecha_pago'])); ?>
                                            </small>
                                            <div class="mt-2">
                                                <span class="badge bg-<?php echo $p['quien_paga'] === 'ellos' ? 'success' : 'danger'; ?>">
                                                    <?php echo $p['quien_paga'] === 'ellos' ? 'Nos pagaron' : 'Pagamos'; ?>
                                                </span>
                                                <small class="text-muted ms-2"><?php echo number_format($p['horas_saldadas'], 1); ?>h saldadas</small>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <h5 class="mb-0"><?php echo number_format($p['monto'], 2); ?>€</h5>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-cash-coin display-4"></i>
                                <p class="mt-3">No hay pagos registrados este año</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Botón de exportar -->
        <div class="text-center mt-4">
            <a href="exportar_mi_reporte.php" class="btn btn-lg btn-success">
                <i class="bi bi-file-earmark-excel"></i> Exportar Mi Reporte (Excel)
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>