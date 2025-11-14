<?php
require_once 'config/database.php';
verificarLogin();

if (!esAdmin()) {
    header('Location: index.php');
    exit;
}

// Mes actual
$mes_actual = date('Y-m');
$anio_actual = date('Y');

// Estadísticas del mes actual
$sql = "SELECT 
    SUM(CASE WHEN tipo = 'favor' THEN horas ELSE 0 END) as favor,
    SUM(CASE WHEN tipo = 'contra' THEN horas ELSE 0 END) as contra
    FROM transacciones WHERE DATE_FORMAT(fecha, '%Y-%m') = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $mes_actual);
$stmt->execute();
$stats_mes = $stmt->get_result()->fetch_assoc();

// Estadísticas del año
$sql = "SELECT 
    SUM(CASE WHEN tipo = 'favor' THEN horas ELSE 0 END) as favor,
    SUM(CASE WHEN tipo = 'contra' THEN horas ELSE 0 END) as contra,
    COUNT(DISTINCT contraparte_id) as escuelas_activas
    FROM transacciones WHERE YEAR(fecha) = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $anio_actual);
$stmt->execute();
$stats_anio = $stmt->get_result()->fetch_assoc();

// Top 5 escuelas por balance
$sql = "SELECT 
    c.nombre,
    SUM(CASE WHEN t.tipo = 'favor' THEN t.horas ELSE 0 END) as favor,
    SUM(CASE WHEN t.tipo = 'contra' THEN t.horas ELSE 0 END) as contra,
    (SUM(CASE WHEN t.tipo = 'favor' THEN t.horas ELSE 0 END) - 
     SUM(CASE WHEN t.tipo = 'contra' THEN t.horas ELSE 0 END)) as balance
    FROM contrapartes c
    LEFT JOIN transacciones t ON c.id = t.contraparte_id AND YEAR(t.fecha) = ?
    WHERE c.activo = 1
    GROUP BY c.id, c.nombre
    HAVING (SUM(CASE WHEN t.tipo = 'favor' THEN t.horas ELSE 0 END) - 
            SUM(CASE WHEN t.tipo = 'contra' THEN t.horas ELSE 0 END)) != 0
    ORDER BY ABS(SUM(CASE WHEN t.tipo = 'favor' THEN t.horas ELSE 0 END) - 
                 SUM(CASE WHEN t.tipo = 'contra' THEN t.horas ELSE 0 END)) DESC
    LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $anio_actual);
$stmt->execute();
$top_escuelas = $stmt->get_result();

// Pendientes de liquidación (balance > 10h hace más de 30 días)
$sql = "SELECT 
    c.nombre,
    c.id,
    SUM(CASE WHEN t.tipo = 'favor' THEN t.horas ELSE 0 END) as favor,
    SUM(CASE WHEN t.tipo = 'contra' THEN t.horas ELSE 0 END) as contra,
    COALESCE(SUM(p.horas_saldadas), 0) as pagadas,
    MAX(COALESCE(p.fecha_pago, t.fecha)) as ultima_actividad,
    DATEDIFF(CURDATE(), MAX(COALESCE(p.fecha_pago, t.fecha))) as dias_sin_actividad
    FROM contrapartes c
    LEFT JOIN transacciones t ON c.id = t.contraparte_id
    LEFT JOIN pagos p ON c.id = p.contraparte_id
    WHERE c.activo = 1
    GROUP BY c.id, c.nombre
    HAVING ABS((favor - contra - pagadas)) > 10 AND dias_sin_actividad > 30
    ORDER BY dias_sin_actividad DESC
    LIMIT 5";
$pendientes = $conn->query($sql);

// Distribución por disciplina (este año)
$sql = "SELECT 
    disciplina,
    SUM(horas) as total_horas
    FROM transacciones 
    WHERE YEAR(fecha) = ? AND disciplina IS NOT NULL
    GROUP BY disciplina";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $anio_actual);
$stmt->execute();
$disciplinas = $stmt->get_result();

// Actividad de los últimos 7 días
$sql = "SELECT 
    DATE(fecha) as dia,
    SUM(CASE WHEN tipo = 'favor' THEN horas ELSE 0 END) as favor,
    SUM(CASE WHEN tipo = 'contra' THEN horas ELSE 0 END) as contra
    FROM transacciones 
    WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(fecha)
    ORDER BY fecha ASC";
$actividad_semanal = $conn->query($sql);

$balance_mes = ($stats_mes['favor'] ?? 0) - ($stats_mes['contra'] ?? 0);
$balance_anio = ($stats_anio['favor'] ?? 0) - ($stats_anio['contra'] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Reportes - SNOW MOTION</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="bi bi-graph-up"></i> Dashboard de Reportes</h4>
            <a href="reportes.php" class="btn btn-outline-dark">
                <i class="bi bi-file-earmark-text"></i> Reportes Detallados
            </a>
        </div>
        
        <!-- Resumen Este Mes vs Este Año -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="card-body text-white p-4">
                        <h6 class="text-white-50 mb-3">ESTE MES (<?php echo strtoupper(strftime('%B', strtotime($mes_actual . '-01'))); ?>)</h6>
                        <div class="row">
                            <div class="col-4">
                                <small class="d-block text-white-50">Favor</small>
                                <h3 class="mb-0"><?php echo number_format($stats_mes['favor'] ?? 0, 0); ?>h</h3>
                            </div>
                            <div class="col-4">
                                <small class="d-block text-white-50">Contra</small>
                                <h3 class="mb-0"><?php echo number_format($stats_mes['contra'] ?? 0, 0); ?>h</h3>
                            </div>
                            <div class="col-4">
                                <small class="d-block text-white-50">Balance</small>
                                <h3 class="mb-0 <?php echo $balance_mes >= 0 ? '' : 'text-warning'; ?>">
                                    <?php echo $balance_mes >= 0 ? '+' : ''; ?><?php echo number_format($balance_mes, 0); ?>h
                                </h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card h-100" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="card-body text-white p-4">
                        <h6 class="text-white-50 mb-3">ESTE AÑO (<?php echo $anio_actual; ?>)</h6>
                        <div class="row">
                            <div class="col-3">
                                <small class="d-block text-white-50">Favor</small>
                                <h3 class="mb-0"><?php echo number_format($stats_anio['favor'] ?? 0, 0); ?>h</h3>
                            </div>
                            <div class="col-3">
                                <small class="d-block text-white-50">Contra</small>
                                <h3 class="mb-0"><?php echo number_format($stats_anio['contra'] ?? 0, 0); ?>h</h3>
                            </div>
                            <div class="col-3">
                                <small class="d-block text-white-50">Balance</small>
                                <h3 class="mb-0 <?php echo $balance_anio >= 0 ? '' : 'text-warning'; ?>">
                                    <?php echo $balance_anio >= 0 ? '+' : ''; ?><?php echo number_format($balance_anio, 0); ?>h
                                </h3>
                            </div>
                            <div class="col-3">
                                <small class="d-block text-white-50">Escuelas</small>
                                <h3 class="mb-0"><?php echo $stats_anio['escuelas_activas'] ?? 0; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row g-3">
            <!-- Top 5 Escuelas -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="bi bi-trophy"></i> Top 5 Escuelas por Volumen
                    </div>
                    <div class="card-body">
                        <?php if ($top_escuelas->num_rows > 0): ?>
                            <?php 
                            $posicion = 1;
                            $max_balance = 0;
                            $data = $top_escuelas->fetch_all(MYSQLI_ASSOC);
                            if (count($data) > 0) {
                                $max_balance = abs($data[0]['balance']);
                            }
                            foreach ($data as $escuela): 
                                $balance = $escuela['balance'];
                                $porcentaje = $max_balance > 0 ? (abs($balance) / $max_balance * 100) : 0;
                                $color = $balance >= 0 ? 'success' : 'danger';
                            ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>
                                        <strong><?php echo $posicion; ?>.</strong> 
                                        <?php echo htmlspecialchars($escuela['nombre']); ?>
                                    </span>
                                    <strong class="text-<?php echo $color; ?>">
                                        <?php echo $balance >= 0 ? '+' : ''; ?><?php echo number_format($balance, 1); ?>h
                                    </strong>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-<?php echo $color; ?>" role="progressbar" 
                                         style="width: <?php echo $porcentaje; ?>%"></div>
                                </div>
                                <small class="text-muted">
                                    Favor: <?php echo number_format($escuela['favor'], 1); ?>h • 
                                    Contra: <?php echo number_format($escuela['contra'], 1); ?>h
                                </small>
                            </div>
                            <?php 
                            $posicion++;
                            endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-inbox display-4"></i>
                                <p class="mt-3">No hay datos disponibles</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Pendientes de Liquidación -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-warning text-dark">
                        <i class="bi bi-exclamation-triangle"></i> Pendientes de Liquidación
                    </div>
                    <div class="card-body">
                        <?php if ($pendientes->num_rows > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php while ($pend = $pendientes->fetch_assoc()): 
                                    $balance = $pend['favor'] - $pend['contra'] - $pend['pagadas'];
                                ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($pend['nombre']); ?></h6>
                                            <small class="text-muted">
                                                Última actividad: <?php echo date('d/m/Y', strtotime($pend['ultima_actividad'])); ?>
                                                (hace <?php echo $pend['dias_sin_actividad']; ?> días)
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <strong class="<?php echo $balance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo $balance >= 0 ? '+' : ''; ?><?php echo number_format($balance, 1); ?>h
                                            </strong>
                                            <br>
                                            <a href="detalle.php?id=<?php echo $pend['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                Ver <i class="bi bi-arrow-right"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-success py-4">
                                <i class="bi bi-check-circle display-4"></i>
                                <p class="mt-3 mb-0">¡Todo al día!</p>
                                <small class="text-muted">No hay pendientes significativos</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Actividad Semanal y Distribución -->
        <div class="row g-3 mt-3">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-graph-up"></i> Actividad de los Últimos 7 Días
                    </div>
                    <div class="card-body">
                        <?php if ($actividad_semanal->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Día</th>
                                            <th class="text-center">A Favor</th>
                                            <th class="text-center">En Contra</th>
                                            <th class="text-center">Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($act = $actividad_semanal->fetch_assoc()): 
                                            $balance_dia = $act['favor'] - $act['contra'];
                                        ?>
                                        <tr>
                                            <td><?php echo strftime('%A %d', strtotime($act['dia'])); ?></td>
                                            <td class="text-center text-success"><?php echo number_format($act['favor'], 1); ?>h</td>
                                            <td class="text-center text-danger"><?php echo number_format($act['contra'], 1); ?>h</td>
                                            <td class="text-center">
                                                <strong class="<?php echo $balance_dia >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo $balance_dia >= 0 ? '+' : ''; ?><?php echo number_format($balance_dia, 1); ?>h
                                                </strong>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted">No hay actividad en los últimos 7 días</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-pie-chart"></i> Por Disciplina (<?php echo $anio_actual; ?>)
                    </div>
                    <div class="card-body">
                        <?php if ($disciplinas->num_rows > 0): ?>
                            <?php 
                            $total_disciplinas = 0;
                            $datos_disc = $disciplinas->fetch_all(MYSQLI_ASSOC);
                            foreach ($datos_disc as $d) {
                                $total_disciplinas += $d['total_horas'];
                            }
                            
                            $colores = [
                                'ski' => 'primary',
                                'snowboard' => 'danger',
                                'ambos' => 'success'
                            ];
                            
                            foreach ($datos_disc as $disc): 
                                $porcentaje = $total_disciplinas > 0 ? ($disc['total_horas'] / $total_disciplinas * 100) : 0;
                                $color = $colores[$disc['disciplina']] ?? 'secondary';
                            ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><?php echo ucfirst($disc['disciplina']); ?></span>
                                    <strong><?php echo number_format($disc['total_horas'], 0); ?>h (<?php echo number_format($porcentaje, 0); ?>%)</strong>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-<?php echo $color; ?>" role="progressbar" 
                                         style="width: <?php echo $porcentaje; ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-center text-muted">Sin datos</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>