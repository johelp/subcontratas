<?php
require_once 'config/database.php';
verificarLogin();

// Si es usuario tipo escuela, redirigir a su reporte
if (!esAdmin()) {
    header('Location: mi_reporte.php');
    exit;
}

// Procesar nueva transacción rápida
$success_transaccion = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_rapido'])) {
    $contraparte_id = (int)$_POST['contraparte_id'];
    $tipo = limpiar($_POST['tipo']);
    $horas = (float)$_POST['horas'];
    $fecha = date('Y-m-d');
    
    $sql = "INSERT INTO transacciones (fecha, contraparte_id, tipo, horas, usuario_registro_id) 
            VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sisdi', $fecha, $contraparte_id, $tipo, $horas, $_SESSION['usuario_id']);
    
    if ($stmt->execute()) {
        $success_transaccion = "✓ Registrado: " . number_format($horas, 1) . "h " . ($tipo === 'favor' ? 'a favor' : 'en contra');
    }
}

// Obtener contrapartes para formulario rápido
$sql = "SELECT id, nombre, tipo FROM contrapartes WHERE activo = 1 ORDER BY nombre";
$contrapartes_form = $conn->query($sql);

// Obtener estadísticas
$stats = [
    'total_contrapartes' => 0,
    'total_favor' => 0,
    'total_contra' => 0,
    'balance_general' => 0
];

$sql = "SELECT COUNT(*) as total FROM contrapartes WHERE activo = 1";
$result = $conn->query($sql);
$stats['total_contrapartes'] = $result->fetch_assoc()['total'];

$sql = "SELECT 
    SUM(CASE WHEN tipo = 'favor' THEN horas ELSE 0 END) as favor,
    SUM(CASE WHEN tipo = 'contra' THEN horas ELSE 0 END) as contra
    FROM transacciones";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$stats['total_favor'] = $row['favor'] ?? 0;
$stats['total_contra'] = $row['contra'] ?? 0;
$stats['balance_general'] = $stats['total_favor'] - $stats['total_contra'];

// Top 5 por urgencia (mayor balance pendiente)
$sql = "SELECT 
    c.id,
    c.nombre,
    c.tipo,
    COALESCE(SUM(CASE WHEN t.tipo = 'favor' THEN t.horas ELSE 0 END), 0) as horas_favor,
    COALESCE(SUM(CASE WHEN t.tipo = 'contra' THEN t.horas ELSE 0 END), 0) as horas_contra,
    COALESCE(SUM(p.horas_saldadas), 0) as horas_pagadas,
    MAX(t.fecha) as ultima_transaccion
    FROM contrapartes c
    LEFT JOIN transacciones t ON c.id = t.contraparte_id
    LEFT JOIN pagos p ON c.id = p.contraparte_id
    WHERE c.activo = 1
    GROUP BY c.id, c.nombre, c.tipo
    HAVING ABS((SUM(CASE WHEN t.tipo = 'favor' THEN t.horas ELSE 0 END) - 
                SUM(CASE WHEN t.tipo = 'contra' THEN t.horas ELSE 0 END) - 
                COALESCE(SUM(p.horas_saldadas), 0))) > 0
    ORDER BY ABS((SUM(CASE WHEN t.tipo = 'favor' THEN t.horas ELSE 0 END) - 
                  SUM(CASE WHEN t.tipo = 'contra' THEN t.horas ELSE 0 END) - 
                  COALESCE(SUM(p.horas_saldadas), 0))) DESC
    LIMIT 5";
$top_urgentes = $conn->query($sql);

// Actividad reciente (últimas 10 transacciones)
$sql = "SELECT t.*, c.nombre as contraparte_nombre, c.tipo as contraparte_tipo
        FROM transacciones t
        INNER JOIN contrapartes c ON t.contraparte_id = c.id
        ORDER BY t.fecha DESC, t.id DESC
        LIMIT 10";
$actividad_reciente = $conn->query($sql);

// Todas las contrapartes para acceso rápido
$sql = "SELECT 
    c.id,
    c.nombre,
    c.tipo,
    COALESCE(SUM(CASE WHEN t.tipo = 'favor' THEN t.horas ELSE 0 END), 0) as horas_favor,
    COALESCE(SUM(CASE WHEN t.tipo = 'contra' THEN t.horas ELSE 0 END), 0) as horas_contra,
    COALESCE(SUM(p.horas_saldadas), 0) as horas_pagadas
    FROM contrapartes c
    LEFT JOIN transacciones t ON c.id = t.contraparte_id
    LEFT JOIN pagos p ON c.id = p.contraparte_id
    WHERE c.activo = 1
    GROUP BY c.id, c.nombre, c.tipo
    ORDER BY c.nombre";
$todas_contrapartes = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SNOW MOTION - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .quick-action-card {
            transition: all 0.2s;
            cursor: pointer;
            border: 2px solid transparent;
        }
        .quick-action-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-color: #dc3545;
        }
        .mini-stat {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .balance-badge-lg {
            font-size: 1.25rem;
            padding: 0.5rem 1rem;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <?php if ($success_transaccion): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill"></i> <?php echo $success_transaccion; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row g-4">
            <!-- COLUMNA IZQUIERDA: ACCIÓN RÁPIDA -->
            <div class="col-lg-4">
                <!-- Registro Rápido -->
                <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white p-4">
                        <h5 class="mb-3"><i class="bi bi-lightning-charge-fill"></i> Registro Rápido</h5>
                        <form method="POST" id="formRapido">
                            <div class="mb-3">
                                <select name="contraparte_id" class="form-select form-select-lg" required>
                                    <option value="">Seleccionar escuela...</option>
                                    <?php 
                                    $contrapartes_form->data_seek(0);
                                    while ($cp = $contrapartes_form->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $cp['id']; ?>">
                                            <?php echo htmlspecialchars($cp['nombre']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <input type="number" name="horas" class="form-control form-control-lg text-center" 
                                           placeholder="Horas" step="0.5" min="0.5" required 
                                           style="font-size: 1.5rem; font-weight: bold;">
                                </div>
                                <div class="col-6">
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="tipo" id="rapido_favor" value="favor" required>
                                        <label class="btn btn-outline-light btn-lg" for="rapido_favor" title="Cedimos">
                                            <i class="bi bi-arrow-up"></i> Favor
                                        </label>
                                        
                                        <input type="radio" class="btn-check" name="tipo" id="rapido_contra" value="contra" required>
                                        <label class="btn btn-outline-light btn-lg" for="rapido_contra" title="Solicitamos">
                                            <i class="bi bi-arrow-down"></i> Contra
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" name="guardar_rapido" class="btn btn-light btn-lg w-100">
                                <i class="bi bi-check-lg"></i> Registrar Ahora
                            </button>
                            
                            <button type="button" class="btn btn-outline-light btn-sm w-100 mt-2" 
                                    data-bs-toggle="modal" data-bs-target="#modalCompleto">
                                <i class="bi bi-plus-circle"></i> Registro Completo (con detalles)
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Balance General -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body text-center p-4">
                        <div class="mini-stat mb-2">BALANCE GENERAL</div>
                        <h1 class="display-4 mb-3 <?php echo $stats['balance_general'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo $stats['balance_general'] >= 0 ? '+' : ''; ?><?php echo number_format($stats['balance_general'], 0); ?><small class="fs-4">h</small>
                        </h1>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="mini-stat">A Favor</div>
                                <h5 class="text-success mb-0"><?php echo number_format($stats['total_favor'], 0); ?>h</h5>
                            </div>
                            <div class="col-6">
                                <div class="mini-stat">En Contra</div>
                                <h5 class="text-danger mb-0"><?php echo number_format($stats['total_contra'], 0); ?>h</h5>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Urgentes -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-warning text-dark fw-bold">
                        <i class="bi bi-exclamation-triangle-fill"></i> Mayor Balance Pendiente
                    </div>
                    <div class="list-group list-group-flush">
                        <?php if ($top_urgentes->num_rows > 0): ?>
                            <?php while ($urg = $top_urgentes->fetch_assoc()): 
                                $balance = $urg['horas_favor'] - $urg['horas_contra'] - $urg['horas_pagadas'];
                            ?>
                            <a href="detalle.php?id=<?php echo $urg['id']; ?>" 
                               class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($urg['nombre']); ?></strong>
                                        <br><small class="text-muted"><?php echo ucfirst($urg['tipo']); ?></small>
                                    </div>
                                    <span class="badge balance-badge-lg bg-<?php echo $balance >= 0 ? 'success' : 'danger'; ?>">
                                        <?php echo $balance >= 0 ? '+' : ''; ?><?php echo number_format($balance, 0); ?>h
                                    </span>
                                </div>
                            </a>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="list-group-item text-center text-muted">
                                <i class="bi bi-check-circle"></i> Todo al día
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- COLUMNA CENTRO: ACTIVIDAD -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-dark text-white fw-bold">
                        <i class="bi bi-clock-history"></i> Actividad Reciente
                    </div>
                    <div class="list-group list-group-flush" style="max-height: 600px; overflow-y: auto;">
                        <?php if ($actividad_reciente->num_rows > 0): ?>
                            <?php while ($act = $actividad_reciente->fetch_assoc()): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge <?php echo $act['tipo'] === 'favor' ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $act['tipo'] === 'favor' ? '↑ Favor' : '↓ Contra'; ?>
                                            </span>
                                            <strong><?php echo number_format($act['horas'], 1); ?>h</strong>
                                        </div>
                                        <div class="text-muted small">
                                            <?php echo htmlspecialchars($act['contraparte_nombre']); ?>
                                        </div>
                                        <div class="text-muted small">
                                            <i class="bi bi-calendar3"></i> <?php echo date('d/m/Y', strtotime($act['fecha'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="list-group-item text-center text-muted py-5">
                                <i class="bi bi-inbox display-4"></i>
                                <p class="mt-3">Sin actividad aún</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- COLUMNA DERECHA: ACCESO RÁPIDO -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-dark text-white fw-bold">
                        <i class="bi bi-building"></i> Acceso Rápido a Escuelas
                    </div>
                    <div class="card-body p-2" style="max-height: 600px; overflow-y: auto;">
                        <div class="row g-2">
                            <?php if ($todas_contrapartes->num_rows > 0): ?>
                                <?php while ($cp = $todas_contrapartes->fetch_assoc()): 
                                    $balance = $cp['horas_favor'] - $cp['horas_contra'] - $cp['horas_pagadas'];
                                    $balance_class = $balance > 0 ? 'success' : ($balance < 0 ? 'danger' : 'secondary');
                                ?>
                                <div class="col-6">
                                    <a href="detalle.php?id=<?php echo $cp['id']; ?>" class="text-decoration-none">
                                        <div class="card quick-action-card h-100">
                                            <div class="card-body p-3 text-center">
                                                <i class="bi bi-<?php echo $cp['tipo'] === 'escuela' ? 'building' : 'person'; ?> fs-3 text-<?php echo $balance_class; ?>"></i>
                                                <h6 class="mt-2 mb-1 small"><?php echo htmlspecialchars($cp['nombre']); ?></h6>
                                                <span class="badge bg-<?php echo $balance_class; ?>">
                                                    <?php echo $balance >= 0 ? '+' : ''; ?><?php echo number_format($balance, 0); ?>h
                                                </span>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="col-12 text-center text-muted py-5">
                                    <i class="bi bi-inbox display-4"></i>
                                    <p class="mt-3">No hay escuelas registradas</p>
                                    <a href="contrapartes.php" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Agregar Escuela
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Accesos Directos Inferiores -->
        <div class="row g-3 mt-2">
            <div class="col-md-3">
                <a href="transacciones.php" class="btn btn-outline-dark btn-lg w-100">
                    <i class="bi bi-clock-history"></i> Ver Todas las Transacciones
                </a>
            </div>
            <div class="col-md-3">
                <a href="reportes.php" class="btn btn-outline-dark btn-lg w-100">
                    <i class="bi bi-file-earmark-text"></i> Generar Reporte
                </a>
            </div>
            <div class="col-md-3">
                <a href="contrapartes.php" class="btn btn-outline-dark btn-lg w-100">
                    <i class="bi bi-building"></i> Gestionar Escuelas
                </a>
            </div>
            <div class="col-md-3">
                <a href="dashboard_reportes.php" class="btn btn-outline-dark btn-lg w-100">
                    <i class="bi bi-graph-up"></i> Dashboard Reportes
                </a>
            </div>
        </div>
    </div>
    
    <!-- Modal Completo (igual que antes) -->
    <div class="modal fade" id="modalCompleto" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="transacciones.php">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Registro Completo</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted">Para agregar disciplina, nivel, idiomas y notas</p>
                        <div class="d-grid">
                            <a href="transacciones.php" class="btn btn-danger btn-lg">
                                <i class="bi bi-arrow-right-circle"></i> Ir a Transacciones
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alert después de 3 segundos
        setTimeout(function() {
            var alert = document.querySelector('.alert');
            if (alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 3000);
    </script>
</body>
</html>