<?php
declare(strict_types=1); // evita conversiones automáticas peligrosas.

session_start();  

require __DIR__ . '/../config/config.php'; 

/**
 * Helper para escapar HTML y evitar XSS.
 * Se usa cuando imprimes variables en HTML (por ejemplo, mensajes o valores de formularios).
 */
function h($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$error = ''; // Aquí guardaremos el mensaje de error si el login falla.

/**
 * Solo procesamos el login si el formulario se ha enviado por POST.
 * Si es GET, simplemente mostrará la vista del login (login.html.php).
 */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

  // Leer los datos que envía el formulario
  $username = trim($_POST['nombre_usuario'] ?? '');
  $password = (string)($_POST['password'] ?? '');

  // Validación básica que no permite campos vacíos
  if ($username === '' || $password === '') {
    $error = 'Rellena usuario y contraseña.';
  } else {

    //Consulta para buscar el usuario por username.
    $sql = "
      SELECT u.id_usuario, u.nombre_usuario,u.apellidos, u.email, u.telefono, u.direccion, u.localidad, u.password, r.nombre AS rol
      FROM usuario u
      LEFT JOIN rol r ON r.id = u.rol_id
      WHERE u.nombre_usuario = ?
      LIMIT 1
    ";

    $stmt = db()->prepare($sql);      
    $stmt->bind_param('s', $username); 
    $stmt->execute();                 
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();              

    //Verificación de credenciales
    if (!$user || !password_verify($password, (string)$user['password'])) {
      $error = 'Usuario o contraseña incorrectos.';
    } else {

// Evita un atacante forzando un session_id conocido.
      session_regenerate_id(true);

   
  // Guardamos id, username y rol porque se usan para autorización/redirecciones.
     
      $_SESSION['user'] = [
        'id_usuario' => (int)$user['id_usuario'],
        'nombre_usuario' => (string)$user['nombre_usuario'],
        'rol' => (string)($user['rol'] ?? ''),
      ];

// Según el rol del usuario, enviamos a su panel correspondiente.
                // Redirección por rol
            switch ($_SESSION['user']['rol']) {
                case 'ADMINISTRADOR':
                    header('Location: ../model/empresa.php?view=ver_empresas');
                    exit;
                case 'TECNICO':
                    header('Location: ../model/tecnico.php');
                    exit;
                case 'CLIENTE':
                    header('Location: ../html/index_cliente.php');
                    exit;
                default:
                    // Si el rol no es uno de los esperados
                    header('Location: panel.php');
                    exit;
            }
        }
    }
}

require __DIR__ . '/../html/login.html.php';