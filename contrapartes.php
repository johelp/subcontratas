<?php
require_once 'config/database.php';
verificarLogin();

if (!esAdmin()) {
    header('Location: index.php');
    exit;
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar_contraparte'])) {
        $nombre = limpiar($_POST['nombre']);
        $tipo = limpiar($_POST['tipo']);
        $contacto = limpiar($_POST['contacto']);
        $telefono = limpiar($_POST['telefono']);
        $email = limpiar($_POST['email']);
        $notas = limpiar($_POST['notas']);
        
        if (isset($_POST['id']) && $_POST['id'] > 0) {
            // Editar
            $id = (int)$_POST['id'];
            $sql = "UPDATE contrapartes SET nombre=?, tipo=?, contacto=?, telefono=?, email=?, notas=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssssssi', $nombre, $tipo, $contacto, $telefono, $email, $notas, $id);
            $success = "Contraparte actualizada correctamente";
        } else {
            // Crear
            $sql = "INSERT INTO contrapartes (nombre, tipo, contacto, telefono, email, notas) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssssss', $nombre, $tipo, $contacto, $telefono, $email, $notas);
            $success = "Contraparte creada correctamente";
        }
        
        if ($stmt->execute()) {
            // OK
        } else {
            $error = "Error al guardar";
        }
    }
    
    if (isset($_POST['eliminar_contraparte'])) {
        $id = (int)$_POST['id'];
        $sql = "UPDATE contrapartes SET activo = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $success = "Contraparte eliminada correctamente";
        }
    }
}

// Obtener contrapartes
$sql = "SELECT c.*, 
        COUNT(DISTINCT t.id) as total_transacciones,
        COALESCE(SUM(CASE WHEN t.tipo = 'favor' THEN t.horas ELSE 0 END), 0) as horas_favor,
        COALESCE(SUM(CASE WHEN t.tipo = 'contra' THEN t.horas ELSE 0 END), 0) as horas_contra
        FROM contrapartes c
        LEFT JOIN transacciones t ON c.id = t.contraparte_id
        WHERE c.activo = 1
        GROUP BY c.id
        ORDER BY c.nombre";
$contrapartes = $conn->query($sql);

// Para edición
$editando = null;
if (isset($_GET['editar'])) {
    $id = (int)$_GET['editar'];
    $sql = "SELECT * FROM contrapartes WHERE id = ? AND activo = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $editando = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escuelas y Autónomos - SNOW MOTION</title>
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
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Formulario -->
            <div class="col-md-4">
                <div class="card sticky-top" style="top: 20px;">
                    <div class="card-header">
                        <i class="bi bi-<?php echo $editando ? 'pencil' : 'plus-circle'; ?>"></i>
                        <?php echo $editando ? 'Editar' : 'Nueva'; ?> Escuela/Autónomo
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php if ($editando): ?>
                                <input type="hidden" name="id" value="<?php echo $editando['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label">Nombre *</label>
                                <input type="text" name="nombre" class="form-control" 
                                       value="<?php echo $editando ? htmlspecialchars($editando['nombre']) : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Tipo *</label>
                                <select name="tipo" class="form-select" required>
                                    <option value="escuela" <?php echo ($editando && $editando['tipo'] === 'escuela') ? 'selected' : ''; ?>>Escuela</option>
                                    <option value="autonomo" <?php echo ($editando && $editando['tipo'] === 'autonomo') ? 'selected' : ''; ?>>Autónomo</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Persona de Contacto</label>
                                <input type="text" name="contacto" class="form-control" 
                                       value="<?php echo $editando ? htmlspecialchars($editando['contacto']) : ''; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="tel" name="telefono" class="form-control" 
                                       value="<?php echo $editando ? htmlspecialchars($editando['telefono']) : ''; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo $editando ? htmlspecialchars($editando['email']) : ''; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Notas</label>
                                <textarea name="notas" class="form-control" rows="3"><?php echo $editando ? htmlspecialchars($editando['notas']) : ''; ?></textarea>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="guardar_contraparte" class="btn btn-danger">
                                    <i class="bi bi-save"></i> <?php echo $editando ? 'Actualizar' : 'Guardar'; ?>
                                </button>
                                <?php if ($editando): ?>
                                    <a href="contrapartes.php" class="btn btn-secondary">Cancelar</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Lista -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-building"></i> Escuelas y Autónomos (<?php echo $contrapartes->num_rows; ?>)
                    </div>
                    <div class="card-body p-0">
                        <?php if ($contrapartes->num_rows > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php while ($cp = $contrapartes->fetch_assoc()): 
                                    $balance = $cp['horas_favor'] - $cp['horas_contra'];
                                ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <i class="bi bi-<?php echo $cp['tipo'] === 'escuela' ? 'building' : 'person'; ?> text-muted"></i>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($cp['nombre']); ?></h6>
                                                <span class="badge bg-secondary"><?php echo ucfirst($cp['tipo']); ?></span>
                                            </div>
                                            
                                            <?php if ($cp['contacto'] || $cp['telefono'] || $cp['email']): ?>
                                                <small class="text-muted d-block">
                                                    <?php if ($cp['contacto']): ?>
                                                        <i class="bi bi-person"></i> <?php echo htmlspecialchars($cp['contacto']); ?>
                                                    <?php endif; ?>
                                                    <?php if ($cp['telefono']): ?>
                                                        • <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($cp['telefono']); ?>
                                                    <?php endif; ?>
                                                    <?php if ($cp['email']): ?>
                                                        • <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($cp['email']); ?>
                                                    <?php endif; ?>
                                                </small>
                                            <?php endif; ?>
                                            
                                            <?php if ($cp['total_transacciones'] > 0): ?>
                                                <div class="mt-2">
                                                    <small class="text-muted">
                                                        <?php echo $cp['total_transacciones']; ?> transacciones • 
                                                        Balance: 
                                                        <strong class="<?php echo $balance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                            <?php echo $balance >= 0 ? '+' : ''; ?><?php echo number_format($balance, 1); ?>h
                                                        </strong>
                                                    </small>
                                                </div>
                                            <?php else: ?>
                                                <small class="text-muted d-block mt-2">Sin movimientos</small>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="btn-group">
                                            <a href="detalle.php?id=<?php echo $cp['id']; ?>" class="btn btn-sm btn-outline-dark" title="Ver detalle">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="contrapartes.php?editar=<?php echo $cp['id']; ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if ($cp['total_transacciones'] == 0): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="confirmarEliminar(<?php echo $cp['id']; ?>, '<?php echo htmlspecialchars($cp['nombre']); ?>')" title="Eliminar">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-inbox display-4"></i>
                                <p class="mt-3">No hay escuelas o autónomos registrados</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Form oculto para eliminar -->
    <form id="formEliminar" method="POST" style="display:none;">
        <input type="hidden" name="id" id="eliminarId">
        <input type="hidden" name="eliminar_contraparte" value="1">
    </form>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmarEliminar(id, nombre) {
            if (confirm('¿Eliminar "' + nombre + '"?\n\nEsta acción no se puede deshacer.')) {
                document.getElementById('eliminarId').value = id;
                document.getElementById('formEliminar').submit();
            }
        }
    </script>
</body>
</html>