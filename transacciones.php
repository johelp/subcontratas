<?php
require_once 'config/database.php';
verificarLogin();

// Procesar formulario
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
        $success = "Transacción registrada correctamente";
    } else {
        $error = "Error al registrar la transacción";
    }
}

// Filtros
$mes_actual = date('Y-m');
$mes_filtro = isset($_GET['mes']) ? $_GET['mes'] : $mes_actual;
$contraparte_filtro = isset($_GET['contraparte']) ? (int)$_GET['contraparte'] : 0;

// Obtener contrapartes para el select
$sql_contrapartes = "SELECT id, nombre, tipo FROM contrapartes WHERE activo = 1 ORDER BY nombre";
$contrapartes = $conn->query($sql_contrapartes);

// Construir query de transacciones
$where = ["DATE_FORMAT(t.fecha, '%Y-%m') = ?"];
$params = [$mes_filtro];
$types = 's';

if ($contraparte_filtro > 0) {
    $where[] = "t.contraparte_id = ?";
    $params[] = $contraparte_filtro;
    $types .= 'i';
}

$sql = "SELECT t.*, c.nombre as contraparte_nombre, c.tipo as contraparte_tipo
        FROM transacciones t
        INNER JOIN contrapartes c ON t.contraparte_id = c.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY t.fecha DESC, t.id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$transacciones = $stmt->get_result();

// Calcular totales del mes
$sql_totales = "SELECT 
    SUM(CASE WHEN tipo = 'favor' THEN horas ELSE 0 END) as favor,
    SUM(CASE WHEN tipo = 'contra' THEN horas ELSE 0 END) as contra
    FROM transacciones WHERE DATE_FORMAT(fecha, '%Y-%m') = ?";
$stmt = $conn->prepare($sql_totales);
$stmt->bind_param('s', $mes_filtro);
$stmt->execute();
$totales = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transacciones - SNOW MOTION</title>
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
        
        <!-- Botón para nueva transacción -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4><i class="bi bi-clock-history"></i> Transacciones</h4>
            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalNuevaTransaccion">
                <i class="bi bi-plus-circle"></i> Nueva Transacción
            </button>
        </div>
        
        <!-- Filtros -->
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Mes</label>
                        <input type="month" name="mes" class="form-control" value="<?php echo $mes_filtro; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Escuela/Autónomo</label>
                        <select name="contraparte" class="form-select">
                            <option value="0">Todas</option>
                            <?php 
                            $contrapartes->data_seek(0);
                            while ($cp = $contrapartes->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $cp['id']; ?>" <?php echo $contraparte_filtro == $cp['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cp['nombre']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-dark w-100">
                            <i class="bi bi-funnel"></i> Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Totales del mes -->
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-4">
                <div class="card stat-card">
                    <div class="stat-label">Nos solicitaron</div>
                    <div class="stat-value text-success"><?php echo number_format($totales['favor'] ?? 0, 1); ?>h</div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="card stat-card">
                    <div class="stat-label">Solicitamos</div>
                    <div class="stat-value text-danger"><?php echo number_format($totales['contra'] ?? 0, 1); ?>h</div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card stat-card">
                    <div class="stat-label">Balance del Mes</div>
                    <?php 
                    $balance_mes = ($totales['favor'] ?? 0) - ($totales['contra'] ?? 0);
                    ?>
                    <div class="stat-value <?php echo $balance_mes >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo $balance_mes >= 0 ? '+' : ''; ?><?php echo number_format($balance_mes, 1); ?>h
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Lista de transacciones -->
        <div class="card">
            <div class="card-header">
                Transacciones de <?php echo date('F Y', strtotime($mes_filtro . '-01')); ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
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
                            <?php if ($transacciones->num_rows > 0): ?>
                                <?php while ($t = $transacciones->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($t['fecha'])); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($t['contraparte_nombre']); ?>
                                        <br><small class="text-muted"><?php echo ucfirst($t['contraparte_tipo']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $t['tipo'] === 'favor' ? 'badge-favor' : 'badge-contra'; ?>">
                                            <?php echo $t['tipo'] === 'favor' ? 'A Favor' : 'En Contra'; ?>
                                        </span>
                                    </td>
                                    <td class="text-center"><strong><?php echo number_format($t['horas'], 1); ?>h</strong></td>
                                    <td><?php echo $t['disciplina'] ? ucfirst($t['disciplina']) : '-'; ?></td>
                                    <td><?php echo $t['nivel'] ? ucfirst($t['nivel']) : '-'; ?></td>
                                    <td><?php echo $t['idiomas'] ?: '-'; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        No hay transacciones para este período
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
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
                                $contrapartes->data_seek(0);
                                while ($cp = $contrapartes->fetch_assoc()): 
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
                                        <strong>A FAVOR</strong>
                                        <small class="d-block text-muted">Cedimos profesores</small>
                                    </label>
                                </div>
                                <div class="col-6">
                                    <input type="radio" class="btn-check" name="tipo" id="tipo_contra" value="contra" required>
                                    <label class="btn btn-outline-danger w-100 py-3" for="tipo_contra">
                                        <i class="bi bi-arrow-down-circle d-block fs-3 mb-2"></i>
                                        <strong>EN CONTRA</strong>
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