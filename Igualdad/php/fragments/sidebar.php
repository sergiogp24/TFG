<?php
/**
 * Sidebar universal reutilizable para todos los roles.
 * Detecta el rol de la sesión y muestra las opciones correspondientes.
 */
declare(strict_types=1);

$view = (string)($view ?? 'menu');
$userRole = strtoupper((string)($_SESSION['user']['rol'] ?? ''));
$userName = (string)($adminUsername ?? $tecnicoUsername ?? $sessionUsername ?? $_SESSION['user']['nombre_usuario'] ?? 'Usuario');
$userEmail = (string)($adminEmail ?? $tecnicoEmail ?? $sessionEmail ?? $_SESSION['user']['email'] ?? '');

// Determinar el avatar e ícono según rol
$sidebarAvatar = '👤';
$sidebarRoleLabel = 'Panel';
$sidebarGradient = 'linear-gradient(135deg, var(--color-blue), var(--color-purple))';

if ($userRole === 'ADMINISTRADOR') {
    $sidebarAvatar = '⚙️';
    $sidebarRoleLabel = 'Panel Admin';
} elseif ($userRole === 'TECNICO') {
    $sidebarAvatar = '👨‍💼';
    $sidebarRoleLabel = 'Panel Técnico';
    $sidebarGradient = 'linear-gradient(135deg, var(--color-teal), var(--color-blue))';
} elseif ($userRole === 'CLIENTE') {
    $sidebarAvatar = '🏢';
    $sidebarRoleLabel = 'Panel Cliente';
    $sidebarGradient = 'linear-gradient(135deg, var(--color-teal), var(--color-green))';
}
?>

<aside class="col-12 col-md-3 col-xl-2">
    <div class="card shadow-sm border-0 sidebar">
        <div class="card-body p-3">
            <!-- Header Sidebar -->
            <div class="sidebar-header mb-4">
                <div class="sidebar-avatar" style="background: <?= $sidebarGradient ?>;">
                    <span style="font-size: 1.2rem;"><?= $sidebarAvatar ?></span>
                </div>
                <h5 class="sidebar-title mb-0"><?= h($sidebarRoleLabel) ?></h5>
            </div>

            <!-- User Info -->
            <div class="sidebar-user-info mb-4">
                <div class="info-label">USUARIO ACTUAL</div>
                <div class="info-value mb-2"><?= h($userName) ?></div>
                <?php if ($userEmail !== ''): ?>
                    <div class="info-email">
                        <span class="me-1">📧</span> <?= h($userEmail) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Navegación -->
            <nav class="sidebar-nav d-flex flex-column gap-2">

                <?php if ($userRole === 'ADMINISTRADOR'): ?>
                    <!-- Panel Admin -->
                    <a class="nav-button <?= ($view === 'menu') ? 'active' : '' ?>" href="<?= app_path('/model/admin.php?view=menu') ?>">
                        <div class="d-flex align-items-center gap-2">
                            <span class="nav-icon">📊</span>
                            <span>Panel Admin</span>
                        </div>
                    </a>
                    <!-- Usuarios -->
                    <a class="nav-button <?= in_array($view, ['ver_usuarios', 'add', 'edit', 'delete'], true) ? 'active' : '' ?>" href="<?= app_path('/model/admin.php?view=ver_usuarios') ?>">
                        <div class="d-flex align-items-center gap-2">
                            <span class="nav-icon">👥</span>
                            <span>Usuarios</span>
                        </div>
                    </a>
                    <!-- Directorio Empresas -->
                    <a class="nav-button" href="<?= app_path('/model/empresa.php?view=ver_empresas&from=admin') ?>">
                        <div class="d-flex align-items-center gap-2">
                            <span class="nav-icon">🏢</span>
                            <span>Directorio Empresas</span>
                        </div>
                    </a>

                <?php elseif ($userRole === 'TECNICO'): ?>
                    <!-- Mi Panel -->
                    <a class="nav-button <?= ($view === 'menu') ? 'active' : '' ?>" href="<?= app_path('/model/tecnico.php?view=menu') ?>">
                        <div class="d-flex align-items-center gap-2">
                            <span class="nav-icon">📊</span>
                            <span>Mi Panel</span>
                        </div>
                    </a>
                    <!-- Empresas Collapse -->
                    <?php $isEmpresasView = !in_array($view, ['menu', 'perfil', 'privada', 'reuniones', 'contacto_empresa'], true); ?>
                    <button class="nav-button nav-collapse <?= $isEmpresasView ? 'active' : '' ?>"
                        type="button" data-bs-toggle="collapse" data-bs-target="#menuEmpresas"
                        aria-expanded="<?= $isEmpresasView ? 'true' : 'false' ?>">
                        <div class="d-flex align-items-center gap-2">
                            <span class="nav-icon">🏢</span>
                            <span>Empresas</span>
                        </div>
                        <span class="collapse-icon">▾</span>
                    </button>
                    <div id="menuEmpresas" class="collapse nav-submenu <?= $isEmpresasView ? 'show' : '' ?> ps-3 mt-1 d-flex flex-column gap-1">
                        <a class="nav-subbutton" href="<?= app_path('/model/empresa.php?view=ver_empresas&from=tecnico') ?>">
                            <span>📋</span> <span>Mis Empresas</span>
                        </a>
                        <a class="nav-subbutton" href="<?= app_path('/model/empresa.php?view=ver_planes&from=tecnico') ?>">
                            <span>📂</span> <span>Mis Planes</span>
                        </a>
                        <a class="nav-subbutton" href="<?= app_path('/model/empresa.php?view=ver_contratos&tipo_contrato=MANTENIMIENTO&from=tecnico') ?>">
                            <span>🛠️</span> <span>Mis Mantenimientos</span>
                        </a>
                    </div>

                <?php elseif ($userRole === 'CLIENTE'): ?>
                    <!-- Mis Empresas -->
                    <a class="nav-button <?= ($view === 'empresas') ? 'active' : '' ?>" href="<?= app_path('/html/index_cliente.php?view=empresas') ?>">
                        <div class="d-flex align-items-center gap-2">
                            <span class="nav-icon">🏢</span>
                            <span>Mis Empresas</span>
                        </div>
                    </a>
                    <!-- Registro Retributivo -->
                    <a class="nav-button <?= ($view === 'menu') ? 'active' : '' ?>" href="<?= app_path('/html/index_cliente.php?view=menu') ?>">
                        <div class="d-flex align-items-center gap-2">
                            <span class="nav-icon">📝</span>
                            <span>Registro Retributivo</span>
                        </div>
                    </a>
                    <!-- Documentación -->
                    <a class="nav-button <?= (str_contains($_SERVER['PHP_SELF'], 'ver_archivos.php')) ? 'active' : '' ?>" href="<?= app_path('/php/ver_archivos.php') ?>">
                        <div class="d-flex align-items-center gap-2">
                            <span class="nav-icon">📁</span>
                            <span>Documentación</span>
                        </div>
                    </a>
                <?php endif; ?>

                <!-- Área Privada (Común a todos) -->
                <?php 
                $isPrivateView = in_array($view, ['privada', 'perfil', 'reuniones'], true); 
                $prefix = ($userRole === 'ADMINISTRADOR') ? app_path('/model/admin.php') : (($userRole === 'TECNICO') ? app_path('/model/tecnico.php') : app_path('/html/index_cliente.php'));
                ?>
                <button class="nav-button nav-collapse <?= $isPrivateView ? 'active' : '' ?>"
                    type="button" data-bs-toggle="collapse" data-bs-target="#menuAreaPrivada"
                    aria-expanded="<?= $isPrivateView ? 'true' : 'false' ?>">
                    <div class="d-flex align-items-center gap-2">
                        <span class="nav-icon">🔐</span>
                        <span>Área Privada</span>
                    </div>
                    <span class="collapse-icon">▾</span>
                </button>
                <div id="menuAreaPrivada" class="collapse nav-submenu <?= $isPrivateView ? 'show' : '' ?> ps-3 mt-1 d-flex flex-column gap-1">
                    <a class="nav-subbutton <?= ($view === 'perfil') ? 'active' : '' ?>" href="<?= $prefix ?>?view=perfil">
                        <span>👤</span> <span>Mi Cuenta</span>
                    </a>
                    <a class="nav-subbutton <?= ($view === 'reuniones') ? 'active' : '' ?>" href="<?= $prefix ?>?view=reuniones">
                        <span>📅</span> <span>Mis Reuniones</span>
                    </a>
                </div>

                <!-- Botón Volver (si existe link) -->
                <?php if (!empty($volverLink)): ?>
                    <div class="mt-2">
                        <a class="nav-button nav-back" href="<?= h($volverLink) ?>">
                            <div class="d-flex align-items-center gap-2">
                                <span class="nav-icon">⬅️</span>
                                <span><?= h($volverLinkText !== '' ? $volverLinkText : 'Volver') ?></span>
                            </div>
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Cerrar Sesión -->
                <div class="mt-2">
                    <a class="nav-button nav-logout" href="<?= h(app_path('/php/logout.php')) ?>">
                        <div class="d-flex align-items-center gap-2">
                            <span class="nav-icon">🚪</span>
                            <span>Cerrar Sesión</span>
                        </div>
                    </a>
                </div>
            </nav>
        </div>
    </div>
</aside>
