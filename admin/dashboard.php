<?php
require_once '../includes/verificar_admin.php';
require_once '../config/conexion.php';
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Panel de control · TechStore</title>
<link rel="stylesheet" href="../css/admin.css"><link rel="stylesheet" href="../css/admin-mejoras.css"><link rel="stylesheet" href="../css/experiencia.css?v=20261020"><link rel="stylesheet" href="../css/admin-experiencia.css?v=20261020"><link rel="stylesheet" href="../css/dashboard.css"><link rel="stylesheet" href="../css/dashboard-vivo.css">
<script src="../js/admin-experiencia.js?v=20261020" defer></script><script src="../js/dashboard-vivo.js" defer></script><script src="../js/experiencia.js?v=20261020" defer></script></head><body>
<?php render_admin_sidebar('dashboard'); ?><div class="admin-main"><header class="admin-header"><h1>Panel de control</h1><span>Actividad y tareas de la tienda</span></header><main class="admin-content">
<?php require '../includes/vista_dashboard_vivo.php'; ?>
<section aria-labelledby="acciones-dashboard"><header class="dashboard-section-heading"><h2 id="acciones-dashboard">Acciones de administración</h2><p>Accede directamente a la tarea que necesitas realizar.</p></header>
<nav class="dashboard-tools dashboard-tools-organized" aria-label="Acciones de administración">
<?php foreach([
 ['ml/predicciones.php','Ver predicciones','Explora ventas, pedidos, clientes y demanda.'],
 ['ml/control.php','Configurar y entrenar ML','Elige modelos y publica un nuevo pronóstico.'],
 ['operaciones.php','Revisar acciones del día','Atiende prioridades y compara pronóstico y realidad.'],
 ['agentes/index.php','Revisar agentes y alertas','Consulta ejecuciones, auditor y avisos de Telegram.'],
 ['gestion.php?tab=proveedores','Gestionar proveedores','Registra proveedores y organiza la reposición.'],
 ['asistente/index.php','Consultar al asistente','Pregunta por los datos y resultados de tu tienda.']
] as $i=>$acceso): ?><a class="dashboard-tool" href="<?= e($acceso[0]) ?>"><span class="dashboard-tool-number" aria-hidden="true"><?= str_pad((string)($i+1),2,'0',STR_PAD_LEFT) ?></span><span class="dashboard-tool-copy"><strong><?= e($acceso[1]) ?></strong><span><?= e($acceso[2]) ?></span></span><span class="dashboard-tool-arrow" aria-hidden="true">↗</span></a><?php endforeach; ?>
</nav></section>
<details class="dashboard-reports"><summary>Ver informes detallados por área</summary><div class="ai-grid"><?php foreach([
 ['productos','Inventario','Demanda, existencias y reposición.'],['pedidos','Pedidos','Volumen previsto y riesgo de cancelación.'],['clientes','Clientes','Segmentación y probabilidad de recompra.'],['ventas','Ventas','Modelos comparados y evaluación del pronóstico.']
] as $area): ?><a class="ai-card" href="ml/<?= e($area[0]) ?>_ia.php"><h3><?= e($area[1]) ?></h3><p><?= e($area[2]) ?></p><span>Ver informe ↗</span></a><?php endforeach; ?></div></details>
</main></div></body></html>