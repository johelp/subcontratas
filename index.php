<?php
require_once 'config/database.php';
verificarLogin();

// Si es usuario tipo escuela, redirigir a su reporte
if (!esAdmin()) {
    header('Location: mi_reporte.php');
    exit;
}

// Procesar nueva transacción
$success_transaccion = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_transaccion'])) {
    $fecha = limpiar($_POST['fecha']);
    $contraparte_id = (int)$_POST['contraparte_id'];
    $tipo = limpiar($_POST['tipo']);
    $horas = (float)$_POST['horas'];
    $disciplina = limpiar($_POST['disciplina']);
    $nivel = limpiar($_POST['nivel']);
    $idiomas = limpiar($_POST['idiomas']);
    $notas = limpiar($_POST['notas']);
    
    $sql = "INSERT INTO transacciones (fecha, contraparte_id, tipo, horas, disciplina, nivel, idiomas, notas, usuario_registro_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sisdssssi', $fecha, $contraparte_id, $tipo, $horas, $disciplina, $nivel, $idiomas, $notas, $_SESSION['usuario_id']);
    
    if ($stmt->execute()) {
        $success_transaccion = "Transacción registrada correctamente";
    } else {
        $error_transaccion = "Error al registrar la transacción";
    }
}

// Obtener contrapartes para el modal
$sql = "SELECT id, nombre, tipo FROM contrapartes WHERE activo = 1 ORDER BY nombre";
$contrapartes_modal = $conn->query($sql);

// Obtener estadísticas generales
$stats = [
    'total_contrapartes' => 0,
    'total_favor' => 0,
    'total_contra' => 0,
    'balance_general' => 0,
    'total_pagado' => 0
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

$sql = "SELECT SUM(monto) as total FROM pagos";
$result = $conn->query($sql);
$stats['total_pagado'] = $result->fetch_assoc()['total'] ?? 0;

// Obtener balance por contraparte
$sql = "SELECT 
    c.id,
    c.nombre,
    c.tipo,
    COALESCE(SUM(CASE WHEN t.tipo = 'favor' THEN t.horas ELSE 0 END), 0) as horas_favor,
    COALESCE(SUM(CASE WHEN t.tipo = 'contra' THEN t.horas ELSE 0 END), 0) as horas_contra,
    COALESCE(SUM(p.horas_saldadas), 0) as horas_pagadas,
    COALESCE(SUM(p.monto), 0) as monto_pagado
    FROM contrapartes c
    LEFT JOIN transacciones t ON c.id = t.contraparte_id
    LEFT JOIN pagos p ON c.id = p.contraparte_id
    WHERE c.activo = 1
    GROUP BY c.id, c.nombre, c.tipo
    ORDER BY c.nombre";

$contrapartes = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SNOW MOTION Subcontratas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <?php if (isset($success_transaccion)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?php echo $success_transaccion; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_transaccion)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-x-circle"></i> <?php echo $error_transaccion; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Header con botón de acción -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="bi bi-house-door"></i> Dashboard</h4>
            <button class="btn btn-danger btn-lg" data-bs-toggle="modal" data-bs-target="#modalNuevaTransaccion">
                <i class="bi bi-plus-circle"></i> Nueva Transacción
            </button>
        </div>
        
        <!-- Estadísticas Generales -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card stat-card">
                    <div class="stat-label">Escuelas / Autónomos</div>
                    <div class="stat-value"><?php echo $stats['total_contrapartes']; ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card">
                    <div class="stat-label">Nos solicitaron</div>
                    <div class="stat-value text-success"><?php echo number_format($stats['total_favor'], 1); ?>h</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card">
                    <div class="stat-label">Solicitamos</div>
                    <div class="stat-value text-danger"><?php echo number_format($stats['total_contra'], 1); ?>h</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card">
                    <div class="stat-label">Balance General</div>
                    <div class="stat-value <?php echo $stats['balance_general'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo $stats['balance_general'] >= 0 ? '+' : ''; ?><?php echo number_format($stats['balance_general'], 1); ?>h
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Balance por Escuela -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-building me-2"></i>Balance por Escuela / Autónomos
            </div>
            <div class="card-body p-0">
                <?php if ($contrapartes->num_rows > 0): ?>
                    <?php while ($cp = $contrapartes->fetch_assoc()): 
                        $balance = $cp['horas_favor'] - $cp['horas_contra'] - $cp['horas_pagadas'];
                        $balance_class = $balance > 0 ? 'balance-positive' : ($balance < 0 ? 'balance-negative' : 'balance-zero');
                    ?>
                    <div class="balance-card <?php echo $balance_class; ?> p-3 border-bottom">
                        <div class="row align-items-center">
                            <div class="col-12 col-md-4">
                                <h6 class="mb-1"><?php echo htmlspecialchars($cp['nombre']); ?></h6>
                                <small class="text-muted">
                                    <i class="bi bi-<?php echo $cp['tipo'] === 'escuela' ? 'building' : 'person'; ?>"></i>
                                    <?php echo ucfirst($cp['tipo']); ?>
                                </small>
                            </div>
                            <div class="col-4 col-md-2 text-center">
                                <div class="stat-label">Nos Solicitaron</div>
                                <strong class="text-success"><?php echo number_format($cp['horas_favor'], 1); ?>h</strong>
                            </div>
                            <div class="col-4 col-md-2 text-center">
                                <div class="stat-label">Solicitamos</div>
                                <strong class="text-danger"><?php echo number_format($cp['horas_contra'], 1); ?>h</strong>
                            </div>
                            <div class="col-4 col-md-2 text-center">
                                <div class="stat-label">Balance</div>
                                <strong class="<?php echo $balance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo $balance >= 0 ? '+' : ''; ?><?php echo number_format($balance, 1); ?>h
                                </strong>
                            </div>
                            <div class="col-12 col-md-2 text-end mt-2 mt-md-0">
                                <a href="detalle.php?id=<?php echo $cp['id']; ?>" class="btn btn-sm btn-outline-dark">
                                    Ver Detalle <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                        <?php if ($cp['monto_pagado'] > 0): ?>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="bi bi-cash-coin"></i> Pagado: <?php echo number_format($cp['monto_pagado'], 2); ?>€
                                    (<?php echo number_format($cp['horas_pagadas'], 1); ?>h saldadas)
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox display-4"></i>
                        <p class="mt-3">No hay escuelas registradas aún</p>
                        <a href="contrapartes.php" class="btn btn-primary">Agregar Escuela</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Botón Flotante para Móvil (FAB) -->
    <button class="btn btn-danger btn-fab d-lg-none" data-bs-toggle="modal" data-bs-target="#modalNuevaTransaccion" 
            title="Nueva Transacción">
        <i class="bi bi-plus-lg"></i>
    </button>
    
    <!-- Modal Nueva Transacción -->
    <div class="modal fade" id="modalNuevaTransaccion" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Nueva Transacción</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Fecha *</label>
                                <input type="date" name="fecha" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Horas *</label>
                                <input type="number" step="0.5" min="0.5" name="horas" class="form-control form-control-lg text-center" 
                                       placeholder="0.0" required style="font-size: 1.5rem; font-weight: bold;">
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <label class="form-label fw-bold">Escuela / Autónomo *</label>
                            <select name="contraparte_id" class="form-select form-select-lg" required>
                                <option value="">Seleccionar...</option>
                                <?php 
                                $contrapartes_modal->data_seek(0);
                                while ($cp = $contrapartes_modal->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $cp['id']; ?>">
                                        <?php echo htmlspecialchars($cp['nombre']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="mt-3">
                            <label class="form-label fw-bold">Tipo de Transacción *</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="tipo" id="tipo_favor" value="favor" required>
                                    <label class="btn btn-outline-success w-100 py-3" for="tipo_favor">
                                        <i class="bi bi-arrow-up-circle d-block fs-3 mb-2"></i>
                                        <strong>NOS DEBEN</strong>
                                        <small class="d-block text-muted">Cedimos profesores</small>
                                    </label>
                                </div>
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="tipo" id="tipo_contra" value="contra" required>
                                    <label class="btn btn-outline-danger w-100 py-3" for="tipo_contra">
                                        <i class="bi bi-arrow-down-circle d-block fs-3 mb-2"></i>
                                        <strong>DEBEMOS</strong>
                                        <small class="d-block text-muted">Solicitamos profesores</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <div class="accordion" id="accordionDetalles">
                            <div class="accordion-item border-0">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed bg-light" type="button" 
                                            data-bs-toggle="collapse" data-bs-target="#collapseDetalles">
                                        <i class="bi bi-info-circle me-2"></i> Información Adicional (Opcional)
                                    </button>
                                </h2>
                                <div id="collapseDetalles" class="accordion-collapse collapse" data-bs-parent="#accordionDetalles">
                                    <div class="accordion-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Disciplina</label>
                                                <select name="disciplina" class="form-select">
                                                    <option value="">No especificado</option>
                                                    <option value="ski">⛷️ Ski</option>
                                                    <option value="snowboard">🏂 Snowboard</option>
                                                    <option value="ambos">🎿 Ambos</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Nivel</label>
                                                <select name="nivel" class="form-select">
                                                    <option value="">No especificado</option>
                                                    <option value="principiante">Principiante</option>
                                                    <option value="intermedio">Intermedio</option>
                                                    <option value="avanzado">Avanzado</option>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Idiomas</label>
                                                <input type="text" name="idiomas" class="form-control" placeholder="Ej: Español, Inglés, Francés">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Notas</label>
                                                <textarea name="notas" class="form-control" rows="2" 
                                                          placeholder="Observaciones adicionales..."></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancelar
                        </button>
                        <button type="submit" name="guardar_transaccion" class="btn btn-danger btn-lg">
                            <i class="bi bi-check-circle me-1"></i>Guardar Transacción
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>