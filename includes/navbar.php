<nav class="navbar navbar-expand-lg navbar-dark navbar-custom mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo esAdmin() ? 'index.php' : 'mi_reporte.php'; ?>">
            <i class="bi bi-snow"></i> SM - Subcontratas
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <?php if (esAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="bi bi-house-door"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="transacciones.php">
                        <i class="bi bi-clock-history"></i> Transacciones
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="contrapartes.php">
                        <i class="bi bi-building"></i> Escuelas
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-file-earmark-text"></i> Reportes
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="dashboard_reportes.php">
                            <i class="bi bi-graph-up"></i> Dashboard
                        </a></li>
                        <li><a class="dropdown-item" href="reportes.php">
                            <i class="bi bi-file-earmark-text"></i> Reportes Detallados
                        </a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="usuarios.php">
                        <i class="bi bi-people"></i> Usuarios
                    </a>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="mi_reporte.php">
                        <i class="bi bi-file-earmark-text"></i> Mi Reporte
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['nombre']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>