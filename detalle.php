<?php
require_once 'config/database.php';
verificarLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Registrar pago
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrar_pago'])) {
    $fecha_pago = limpiar($_POST['fecha_pago']);
    $monto = (float)$_POST['monto'];
    $horas_saldadas = (float)$_POST['horas_saldadas'];
    $quien_paga = limpiar($_POST['quien_paga']);
    $concepto = limpiar($_POST['concepto']);
    $notas = limpiar($_POST['notas']);
    
    $sql = "INSERT INTO pagos (fecha_pago, contraparte_id, monto, horas_saldadas, quien_paga, concepto, notas, usuario_registro_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('siddsssi', $fecha_pago, $id, $monto, $horas_saldadas, $quien_paga, $concepto, $notas, $_SESSION['usuario_id']);
    
    if ($stmt->execute()) {
        $success = "Pago registrado correctamente";
    }
}

// Obtener info de la contraparte
$sql = "SELECT * FROM contrapartes WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$contraparte = $stmt->get_result()->fetch_assoc();

if (!$contraparte) {
    header('Location: index.php');
    exit;
}

// Balance general
$sql = "SELECT 
    COALESCE(SUM(CASE WHEN tipo = 'favor' THEN horas ELSE 0 END), 0) as favor,
    COALESCE(SUM(CASE WHEN tipo = 'contra' THEN horas ELSE 0 END), 0) as contra
    FROM transacciones WHERE contraparte_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$balance = $stmt->get_result()->fetch_assoc();

// Total pagado
$sql = "SELECT COALESCE(SUM(horas_saldadas), 0) as horas_pagadas, COALESCE(SUM(monto), 0) as monto_total
        FROM pagos WHERE contraparte_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
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
    ORDER BY mes DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$balance_mensual = $stmt->get_result();

// Historial de pagos
$sql = "SELECT * FROM pagos WHERE contraparte_id = ? ORDER BY fecha_pago DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$pagos = $stmt->get_result();

// Últimas transacciones
$sql = "SELECT * FROM transacciones WHERE contraparte_id = ? ORDER BY fecha DESC, id DESC LIMIT 20";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$transacciones = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle - <?php echo htmlspecialchars($contraparte['nombre']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <a href="index.php" class="btn btn-sm btn-outline-secondary mb-2">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
                <h4 class="mb-0">
                    <i class="bi bi-<?php echo $contraparte['tipo'] === 'escuela' ? 'building' : 'person'; ?>"></i>
                    <?php echo htmlspecialchars($contraparte['nombre']); ?>
                </h4>
            </div>
            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalPago">
                <i class="bi bi-cash-coin"></i> Registrar Pago
            </button>
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
                                    <?php echo $balance_final > 0 ? 'Nos deben' : ($balance_final < 0 ? 'Debemos' : 'Saldado'); ?>
                                </p>
                            </div>
                            <div class="col-5 text-end">
                                <div class="mb-3">
                                    <small class="text-white-50 d-block">A Favor</small>
                                    <h4 class="mb-0"><?php echo number_format($balance['favor'], 1); ?>h</h4>
                                </div>
                                <div>
                                    <small class="text-white-50 d-block">En Contra</small>
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
        
        <!-- Tabs de Contenido -->
        <ul class="nav nav-tabs mb-3" id="detalleTabs" role="tablist">
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
                    <i class="bi bi-cash-stack"></i> Pagos (<?php echo $pagos->num_rows; ?>)
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="detalleTabsContent">
            <!-- Tab Balance Mensual -->
            <div class="tab-pane fade show active" id="mensual" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <?php if ($balance_mensual->num_rows > 0): ?>
                            <?php $balance_mensual->data_seek(0); ?>
                            <?php while ($bm = $balance_mensual->fetch_assoc()): 
                                $balance_mes = $bm['favor'] - $bm['contra'];
                                $mes_nombre = strftime('%B %Y', strtotime($bm['mes'] . '-01'));
                            ?>
                            <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                                <div>
                                    <h6 class="mb-1"><?php echo ucfirst($mes_nombre); ?></h6>
                                    <small class="text-muted">
                                        <span class="text-success"><?php echo number_format($bm['favor'], 1); ?>h favor</span> • 
                                        <span class="text-danger"><?php echo number_format($bm['contra'], 1); ?>h contra</span>
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
            
            <!-- Tab Transacciones -->
            <div class="tab-pane fade" id="transacciones-tab-pane" role="tabpanel">
                <div class="card">
                    <div class="card-body p-0">
                        <?php if ($transacciones->num_rows > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php $transacciones->data_seek(0); ?>
                                <?php while ($t = $transacciones->fetch_assoc()): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <span class="badge <?php echo $t['tipo'] === 'favor' ? 'badge-favor' : 'badge-contra'; ?>">
                                                    <?php echo $t['tipo'] === 'favor' ? 'A Favor' : 'En Contra'; ?>
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
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-inbox display-4"></i>
                                <p class="mt-3">No hay transacciones registradas</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Tab Pagos -->
            <div class="tab-pane fade" id="pagos-tab-pane" role="tabpanel">
                <div class="card">
                    <div class="card-body p-0">
                        <?php if ($pagos->num_rows > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php $pagos->data_seek(0); ?>
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
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-cash-coin display-4"></i>
                                <p class="mt-3">No hay pagos registrados</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Registrar Pago -->
    <div class="modal fade" id="modalPago" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>Registrar Pago</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info d-flex align-items-center">
                            <i class="bi bi-info-circle fs-4 me-3"></i>
                            <div>
                                <strong>Balance Pendiente</strong><br>
                                <span class="fs-5 fw-bold <?php echo $balance_final >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo $balance_final >= 0 ? '+' : ''; ?><?php echo number_format($balance_final, 1); ?>h
                                </span>
                                <small class="d-block text-muted">
                                    <?php echo $balance_final > 0 ? 'Nos deben' : ($balance_final < 0 ? 'Debemos' : 'Saldado'); ?>
                                </small>
                            </div>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Fecha de Pago *</label>
                                <input type="date" name="fecha_pago" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Monto (€) *</label>
                                <input type="number" step="0.01" min="0.01" name="monto" class="form-control form-control-lg text-end" 
                                       placeholder="0.00" required style="font-size: 1.5rem; font-weight: bold;">
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <label class="form-label fw-bold">Horas que Salda *</label>
                            <input type="number" step="0.5" min="0.5" name="horas_saldadas" class="form-control form-control-lg text-center" 
                                   max="<?php echo abs($balance_final); ?>" placeholder="0.0" required 
                                   style="font-size: 1.5rem; font-weight: bold;">
                            <small class="text-muted">Máximo disponible: <?php echo number_format(abs($balance_final), 1); ?>h</small>
                        </div>
                        
                        <div class="mt-3">
                            <label class="form-label fw-bold">¿Quién Realiza el Pago? *</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="quien_paga" id="paga_ellos" value="ellos" required>
                                    <label class="btn btn-outline-success w-100 py-3" for="paga_ellos">
                                        <i class="bi bi-arrow-down-circle d-block fs-3 mb-2"></i>
                                        <strong>ELLOS</strong>
                                        <small class="d-block text-muted">Nos pagan</small>
                                    </label>
                                </div>
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="quien_paga" id="paga_nosotros" value="nosotros" required>
                                    <label class="btn btn-outline-danger w-100 py-3" for="paga_nosotros">
                                        <i class="bi bi-arrow-up-circle d-block fs-3 mb-2"></i>
                                        <strong>NOSOTROS</strong>
                                        <small class="d-block text-muted">Pagamos</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <label class="form-label fw-bold">Concepto *</label>
                            <input type="text" name="concepto" class="form-control" 
                                   value="Pago <?php echo date('F Y'); ?>" required>
                        </div>
                        
                        <div class="mt-3">
                            <label class="form-label">Notas</label>
                            <textarea name="notas" class="form-control" rows="2" 
                                      placeholder="Observaciones adicionales..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancelar
                        </button>
                        <button type="submit" name="registrar_pago" class="btn btn-danger btn-lg">
                            <i class="bi bi-check-circle me-1"></i>Registrar Pago
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>