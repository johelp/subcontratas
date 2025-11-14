<?php
require_once 'config/database.php';
verificarLogin();

if (!esAdmin()) {
    header('Location: index.php');
    exit;
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar_usuario'])) {
        $username = limpiar($_POST['username']);
        $nombre = limpiar($_POST['nombre']);
        $password = $_POST['password'];
        $rol = limpiar($_POST['rol']);
        $contraparte_id = isset($_POST['contraparte_id']) ? (int)$_POST['contraparte_id'] : null;
        
        if (isset($_POST['id']) && $_POST['id'] > 0) {
            // Editar
            $id = (int)$_POST['id'];
            
            if (!empty($password)) {
                // Cambiar password
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $sql = "UPDATE usuarios SET username=?, password=?, nombre=?, rol=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('ssssi', $username, $password_hash, $nombre, $rol, $id);
            } else {
                // Sin cambiar password
                $sql = "UPDATE usuarios SET username=?, nombre=?, rol=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('sssi', $username, $nombre, $rol, $id);
            }
            
            if ($stmt->execute()) {
                // Si es escuela, actualizar contraparte_id
                if ($rol === 'escuela' && $contraparte_id) {
                    $sql = "UPDATE contrapartes SET usuario_id=? WHERE id=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('ii', $id, $contraparte_id);
                    $stmt->execute();
                }
                $success = "Usuario actualizado correctamente";
            }
        } else {
            // Crear
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $sql = "INSERT INTO usuarios (username, password, nombre, rol) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssss', $username, $password_hash, $nombre, $rol);
            
            if ($stmt->execute()) {
                $nuevo_id = $conn->insert_id;
                
                // Si es escuela, vincular con contraparte
                if ($rol === 'escuela' && $contraparte_id) {
                    $sql = "UPDATE contrapartes SET usuario_id=? WHERE id=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('ii', $nuevo_id, $contraparte_id);
                    $stmt->execute();
                }
                
                $success = "Usuario creado correctamente";
            } else {
                $error = "Error: Usuario ya existe";
            }
        }
    }
    
    if (isset($_POST['eliminar_usuario'])) {
        $id = (int)$_POST['id'];
        
        // No permitir eliminar admin principal
        if ($id == 1) {
            $error = "No se puede eliminar el usuario administrador principal";
        } else {
            $sql = "UPDATE usuarios SET activo = 0 WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $success = "Usuario desactivado correctamente";
            }
        }
    }
    
    if (isset($_POST['activar_usuario'])) {
        $id = (int)$_POST['id'];
        $sql = "UPDATE usuarios SET activo = 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $success = "Usuario activado correctamente";
        }
    }
}

// Obtener usuarios
$sql = "SELECT u.*, c.nombre as contraparte_nombre 
        FROM usuarios u
        LEFT JOIN contrapartes c ON u.id = c.usuario_id
        ORDER BY u.rol, u.nombre";
$usuarios = $conn->query($sql);

// Obtener contrapartes sin usuario asignado
$sql = "SELECT id, nombre FROM contrapartes WHERE activo = 1 AND usuario_id IS NULL ORDER BY nombre";
$contrapartes_disponibles = $conn->query($sql);

// Para edición
$editando = null;
if (isset($_GET['editar'])) {
    $id = (int)$_GET['editar'];
    $sql = "SELECT u.*, c.id as contraparte_id 
            FROM usuarios u
            LEFT JOIN contrapartes c ON u.id = c.usuario_id
            WHERE u.id = ?";
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
    <title>Usuarios - SNOW MOTION</title>
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
                        <i class="bi bi-<?php echo $editando ? 'pencil' : 'person-plus'; ?>"></i>
                        <?php echo $editando ? 'Editar' : 'Nuevo'; ?> Usuario
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php if ($editando): ?>
                                <input type="hidden" name="id" value="<?php echo $editando['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Usuario *</label>
                                <input type="text" name="username" class="form-control" 
                                       value="<?php echo $editando ? htmlspecialchars($editando['username']) : ''; ?>" 
                                       required <?php echo $editando ? 'readonly' : ''; ?>>
                                <?php if ($editando): ?>
                                    <small class="text-muted">El username no se puede modificar</small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Nombre Completo *</label>
                                <input type="text" name="nombre" class="form-control" 
                                       value="<?php echo $editando ? htmlspecialchars($editando['nombre']) : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Contraseña <?php echo $editando ? '' : '*'; ?></label>
                                <input type="password" name="password" class="form-control" 
                                       <?php echo $editando ? '' : 'required'; ?>>
                                <?php if ($editando): ?>
                                    <small class="text-muted">Dejar en blanco para mantener la actual</small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Rol *</label>
                                <select name="rol" class="form-select" id="rolSelect" required>
                                    <option value="admin" <?php echo ($editando && $editando['rol'] === 'admin') ? 'selected' : ''; ?>>
                                        Administrador
                                    </option>
                                    <option value="escuela" <?php echo ($editando && $editando['rol'] === 'escuela') ? 'selected' : ''; ?>>
                                        Escuela/Autónomo
                                    </option>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="contraparteSelect" style="display: <?php echo ($editando && $editando['rol'] === 'escuela') ? 'block' : 'none'; ?>;">
                                <label class="form-label fw-bold">Vincular con Escuela/Autónomo</label>
                                <select name="contraparte_id" class="form-select">
                                    <option value="">Seleccionar...</option>
                                    <?php if ($editando && $editando['contraparte_id']): ?>
                                        <option value="<?php echo $editando['contraparte_id']; ?>" selected>
                                            <?php echo htmlspecialchars($editando['contraparte_nombre']); ?>
                                        </option>
                                    <?php endif; ?>
                                    <?php while ($cp = $contrapartes_disponibles->fetch_assoc()): ?>
                                        <option value="<?php echo $cp['id']; ?>">
                                            <?php echo htmlspecialchars($cp['nombre']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <small class="text-muted">Este usuario verá solo los datos de esta escuela</small>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="guardar_usuario" class="btn btn-danger">
                                    <i class="bi bi-save"></i> <?php echo $editando ? 'Actualizar' : 'Crear'; ?> Usuario
                                </button>
                                <?php if ($editando): ?>
                                    <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
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
                        <i class="bi bi-people"></i> Usuarios del Sistema (<?php echo $usuarios->num_rows; ?>)
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Nombre</th>
                                        <th>Rol</th>
                                        <th>Vinculado a</th>
                                        <th>Estado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $usuarios->data_seek(0);
                                    while ($user = $usuarios->fetch_assoc()): 
                                    ?>
                                    <tr class="<?php echo $user['activo'] ? '' : 'table-secondary'; ?>">
                                        <td>
                                            <i class="bi bi-person-circle"></i>
                                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['nombre']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['rol'] === 'admin' ? 'danger' : 'primary'; ?>">
                                                <?php echo ucfirst($user['rol']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user['contraparte_nombre']): ?>
                                                <small class="text-muted">
                                                    <i class="bi bi-building"></i> 
                                                    <?php echo htmlspecialchars($user['contraparte_nombre']); ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['activo']): ?>
                                                <span class="badge bg-success">Activo</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="usuarios.php?editar=<?php echo $user['id']; ?>" 
                                                   class="btn btn-outline-primary" title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php if ($user['id'] != 1): ?>
                                                    <?php if ($user['activo']): ?>
                                                        <button type="button" class="btn btn-outline-warning" 
                                                                onclick="toggleUsuario(<?php echo $user['id']; ?>, 0, '<?php echo htmlspecialchars($user['username']); ?>')" 
                                                                title="Desactivar">
                                                            <i class="bi bi-x-circle"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-outline-success" 
                                                                onclick="toggleUsuario(<?php echo $user['id']; ?>, 1, '<?php echo htmlspecialchars($user['username']); ?>')" 
                                                                title="Activar">
                                                            <i class="bi bi-check-circle"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <button class="btn btn-outline-secondary" disabled title="Usuario principal">
                                                        <i class="bi bi-shield-lock"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle"></i> <strong>Tipos de usuario:</strong>
                    <ul class="mb-0 mt-2">
                        <li><strong>Administrador:</strong> Acceso total al sistema</li>
                        <li><strong>Escuela/Autónomo:</strong> Solo puede ver su propio balance y exportar su reporte</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Forms ocultos -->
    <form id="formToggle" method="POST" style="display:none;">
        <input type="hidden" name="id" id="toggleId">
        <input type="hidden" name="eliminar_usuario" id="eliminarInput">
        <input type="hidden" name="activar_usuario" id="activarInput">
    </form>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mostrar/ocultar selector de contraparte según rol
        document.getElementById('rolSelect').addEventListener('change', function() {
            const contraparteDiv = document.getElementById('contraparteSelect');
            if (this.value === 'escuela') {
                contraparteDiv.style.display = 'block';
            } else {
                contraparteDiv.style.display = 'none';
            }
        });
        
        function toggleUsuario(id, activar, username) {
            const accion = activar ? 'activar' : 'desactivar';
            if (confirm('¿' + (activar ? 'Activar' : 'Desactivar') + ' usuario "' + username + '"?')) {
                document.getElementById('toggleId').value = id;
                if (activar) {
                    document.getElementById('activarInput').disabled = false;
                    document.getElementById('eliminarInput').disabled = true;
                } else {
                    document.getElementById('eliminarInput').disabled = false;
                    document.getElementById('activarInput').disabled = true;
                }
                document.getElementById('formToggle').submit();
            }
        }
    </script>
</body>
</html>