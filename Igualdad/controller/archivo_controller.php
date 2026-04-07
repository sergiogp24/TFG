<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

/**
 * Retorna el archivo que debe ofrecerse para descarga.
 *
 * - Si el cliente tiene al menos un archivo subido de tipo REGISTRO_RETRIBUTIVO,
 *   devuelve el último subido.
 * - Si no, devuelve el archivo por defecto con id = 1.
 *
 * @param int $usuarioId id_usuario de la sesión (cliente)
 * @return array{id:int,nombre:string}
 */
function archivo_get_download_file_for_user(int $usuarioId): array {
  $default = ['id' => 1, 'nombre' => 'Formato Registro Retributivo'];
  if ($usuarioId <= 0) return $default;

  $db = db();
  $stmt = $db->prepare("\n    SELECT a.id_archivo, a.nombre_original\n    FROM archivos a\n    JOIN cliente_medida cm ON cm.id_cliente_medida = a.id_cliente_medida\n    JOIN plan_cliente pc ON pc.id_plan_cliente = cm.id_plan_cliente\n    JOIN usuario_empresa ue ON ue.id_empresa = pc.id_empresa\n    WHERE ue.id_usuario = ? AND a.tipo = 'REGISTRO_RETRIBUTIVO'\n    ORDER BY a.subido_en DESC\n    LIMIT 1\n  ");
  if (!$stmt) return $default;

  $stmt->bind_param('i', $usuarioId);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$row) return $default;

  return [
    'id' => (int)$row['id_archivo'],
    'nombre' => (string)$row['nombre_original'],
  ];
}
