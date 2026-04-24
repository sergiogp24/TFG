drop database igualdadconsulting;
-- BASE DE DATOS IGUALDAD
CREATE DATABASE IF NOT EXISTS igualdadconsulting;
USE igualdadconsulting;
-- --------------------------------------------------------

--
-- Esctrutura para la tabla ROL
--

CREATE TABLE `rol`(
 `id`INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
 `nombre`VARCHAR(100) UNIQUE NOT NULL
) ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Estrucutra para la tabla USUARIO
-- 

CREATE TABLE `usuario`(
 `id_usuario`INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
 `nombre_usuario`VARCHAR(255) UNIQUE NOT NULL,
 `apellidos`VARCHAR(255) DEFAULT NULL,
 `email`VARCHAR(255) UNIQUE NOT NULL,
 `telefono`VARCHAR(20)  DEFAULT NULL,
 `direccion`VARCHAR(255) DEFAULT NULL,
 `localidad`VARCHAR(20) DEFAULT NULL,
 `password`VARCHAR(255) NOT NULL,
 `rol_id`INT NOT NULL,
    CONSTRAINT fk_usuario_rol FOREIGN KEY (rol_id) REFERENCES rol(id) ON DELETE RESTRICT,
INDEX idx_usuario_rol (rol_id)
)ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Tokens para establecer la contraseña al crear o recuperar
--

CREATE TABLE IF NOT EXISTS password_reset_token (
  `id` INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `token` VARCHAR(255) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME NOT NULL,
  `used` BOOLEAN DEFAULT FALSE,
  KEY `idx_email` (`email`),
  KEY `idx_token` (`token`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB;
SELECT * FROM password_reset_token;
-- --------------------------------------------------------

--
-- Estructura para la tablas de Reuniones
--

CREATE TABLE reuniones(
 id_reunion INT PRIMARY KEY AUTO_INCREMENT,
 objetivo TEXT DEFAULT NULL,
 hora_reunion VARCHAR(255) NOT NULL,
 fecha_reunion DATE NOT NULL
) ENGINE=InnoDB;

CREATE TABLE usuario_reunion(
 id_usuario INT NOT NULL,
 id_reunion INT NOT NULL,
 PRIMARY KEY (id_usuario, id_reunion),
 CONSTRAINT fk_ur_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE,
 CONSTRAINT fk_ur_reunion FOREIGN KEY (id_reunion) REFERENCES reuniones(id_reunion) ON DELETE CASCADE
) ENGINE=InnoDB;
-- --------------------------------------------------------

--
-- Estructura para la tabla CLIENTE(EMPRESA)
--

CREATE TABLE `empresa`(
`id_empresa`INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
`razon_social`VARCHAR(255) UNIQUE NOT NULL,
`nif`VARCHAR(255) DEFAULT NULL,
`domicilio_social`VARCHAR(255) DEFAULT NULL,
`forma_juridica` VARCHAR(255) DEFAULT NULL,
`ano_constitucional`VARCHAR(255) DEFAULT NULL,
-- RESPONSABLE DE LA ENTIDAD
  `responsable` VARCHAR(255) DEFAULT NULL,
  `cargo` VARCHAR(150) DEFAULT NULL,
  `contacto` VARCHAR(255) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `telefono` VARCHAR(20) DEFAULT NULL,
  -- ACTIVIDAD
  `sector` VARCHAR(255) DEFAULT NULL,
  `convenio` VARCHAR(255) DEFAULT NULL,
  -- DIMENSIÓN
  `personas_mujeres` INT DEFAULT NULL,
  `personas_hombres` INT DEFAULT NULL,
  `personas_total` INT DEFAULT NULL,
  `centros_trabajo` INT DEFAULT NULL,
-- Datos del Plan
  `recogida_informacion` VARCHAR(255) DEFAULT NULL,
  `vigencia_plan` VARCHAR(255) DEFAULT NULL,
  `id_usuario` INT DEFAULT NULL,
  CONSTRAINT `fk_usuario_empresa` FOREIGN KEY (`id_usuario`) REFERENCES `usuario`(`id_usuario`) ON DELETE RESTRICT,
  INDEX `idx_cliente_usuario` (`id_usuario`),
  INDEX `idx_cliente_nif` (`nif`),
  INDEX `idx_empresa_razon_social` (`razon_social`)
) ENGINE=InnoDB;


CREATE TABLE `cnae` (
  `id` INT  PRIMARY KEY AUTO_INCREMENT,
  `nombre` LONGTEXT DEFAULT NULL,
  `id_empresa` INT NOT NULL,
   CONSTRAINT `fk_empresa_cnae` FOREIGN KEY (`id_empresa`) REFERENCES `empresa`(`id_empresa`) ON DELETE CASCADE
) ENGINE=InnoDB;
SELECT * FROM cnae;
-- --------------------------------------------------------
--
-- Estructura para la tabla RELACIONAL USUARIOS Y CLIENTES
--

CREATE TABLE `usuario_empresa`(
`id_usuario_empresa` INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
`id_usuario` INT,
CONSTRAINT fk_usuario_empresa_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE,
`id_empresa` INT,
CONSTRAINT fk_usuario_empresa_empresa FOREIGN KEY (id_empresa) REFERENCES empresa(id_empresa) ON DELETE CASCADE)ENGINE = InnoDB;

-- --------------------------------------------------------

--
-- Estructura para la tabla AREA DEL PLAN 
--
CREATE TABLE `area_plan`(
`id_plan`INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
`nombre`VARCHAR(255) NOT NULL)ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Estructura para la tabla medida
--
CREATE TABLE `medida`(
`id_medida`INT AUTO_INCREMENT PRIMARY KEY,
`id_plan`INT NOT NULL,
`descripcion`TEXT DEFAULT NULL ,
`indicador`TEXT DEFAULT NULL, 
CONSTRAINT fk_medida_area FOREIGN KEY (id_plan) REFERENCES area_plan(id_plan),
INDEX idx_medida_plan (id_plan)
)ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Estructura para la tabla Areas_contratadas
--
-- --------------------------------------------------------
-- 
-- antes era plan_clientes
CREATE TABLE `areas_contratadas`(
`id_areas_contratadas`INT AUTO_INCREMENT PRIMARY KEY,
`inicio_plan`DATE NOT NULL,
`fin_plan`DATE NOT NULL,
`id_empresa`INT NOT NULL,
`id_plan`INT NOT NULL,
  CONSTRAINT fk_areas_contratadas_empresa FOREIGN KEY (id_empresa) REFERENCES empresa(id_empresa) ON DELETE CASCADE,
  CONSTRAINT fk_areas_contratadas_plan FOREIGN KEY (id_plan) REFERENCES area_plan(id_plan) ON DELETE RESTRICT,
INDEX idx_areas_contratadas_empresa (id_empresa),
INDEX idx_areas_contratadas_plan (id_plan)
)ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Estructura para la tabla contrato_empresa (lo de los servicios)
--
-- --------------------------------------------------------


CREATE TABLE `contrato_empresa`(
`id_contrato_empresa`INT AUTO_INCREMENT PRIMARY KEY,
`tipo_contrato`	ENUM('PLAN IGUALDAD' , 'MANTENIMIENTO'),
`inicio_contratacion`DATE NOT NULL,
`fin_contratacion`DATE NOT NULL,
`id_empresa`INT NOT NULL,
`id_usuario`INT NOT NULL,
  CONSTRAINT fk_contrato_empresa_empresa FOREIGN KEY (id_empresa) REFERENCES empresa(id_empresa) ON DELETE CASCADE,
INDEX idx_contrato_empresa_empresa (id_empresa),
  CONSTRAINT fk_contrato_empresa_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE,
INDEX idx_contrato_empresa_usuario (id_usuario)
)ENGINE=InnoDB;
-- --------------------------------------------------------

--
-- Estructura para la tabla año_datos
--
-- --------------------------------------------------------

CREATE TABLE `ano_datos`(
`id_ano_datos` INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
`fecha_inicio` DATE NOT NULL,
`fecha_fin` DATE NOT NULL,
`id_contrato_empresa` INT NOT NULL,
 CONSTRAINT fk_ano_datos_contrato_empresa FOREIGN KEY (id_contrato_empresa) REFERENCES contrato_empresa(id_contrato_empresa) ON DELETE CASCADE,
 INDEX idx__ano_datos_contrato_empresa (id_contrato_empresa)
 )ENGINE= InnoDB;
 SELECT * FROM ano_datos;
-- --------------------------------------------------------

--
-- Estructura para la tabla Datos_empleados
--
-- --------------------------------------------------------
CREATE TABLE `datos_empleados`(
`id_datos_empleados` INT PRIMARY KEY AUTO_INCREMENT,
`id`VARCHAR(255) DEFAULT NULL,
`sexo` ENUM('HOMBRE', 'MUJER') DEFAULT NULL,
`fecha_nacimiento`DATE  DEFAULT NULL,
`estudios` VARCHAR(255)  DEFAULT NULL,
`situacion_familiar` INT  DEFAULT NULL,
`hijos` INT  DEFAULT NULL,
`inicio_contratacion` DATE  DEFAULT NULL,
`fin_contratacion` DATE  DEFAULT NULL,
`fecha_antiguedad` DATE  DEFAULT NULL,
`inicio_sit` DATE  DEFAULT NULL,
`fin_sit` DATE  DEFAULT NULL,
`porc_jornada` DECIMAL (10,2) DEFAULT NULL ,
`porc_reducida` DECIMAL (10,2) DEFAULT NULL,
`motivo_reduccion` VARCHAR(255) DEFAULT NULL ,
`clave_contrato` INT DEFAULT NULL,
`area_empresa` VARCHAR(255) DEFAULT NULL,
`dpto_empresa`VARCHAR(255) DEFAULT NULL,
`puesto_empresa` VARCHAR(255) DEFAULT NULL,
`horario`ENUM('CONTINUO','PARTIDO') DEFAULT NULL,
`trabajo_turnos` ENUM ('SI','NO') DEFAULT NULL,
`escala_empresa`VARCHAR(255) DEFAULT NULL,
`agrup_class_prof` VARCHAR(255) DEFAULT NULL,
`agrup_valor_pto` VARCHAR(255) DEFAULT NULL,
`convenio_area` VARCHAR(255) DEFAULT NULL,
`categoria_profesional` VARCHAR(255) DEFAULT NULL,
`grupo_profesional` VARCHAR(255) DEFAULT NULL,
`nivel` INT DEFAULT NULL,
`salario` DECIMAL (10,2) DEFAULT NULL,
`f_fin_cal` DATE DEFAULT NULL,
`prc_normaliz` DECIMAL (10,2) DEFAULT NULL,
`prc_anualiz` DECIMAL (10,2) DEFAULT NULL,
`check_equi` ENUM ('SI','NO') DEFAULT NULL,
`salario_base_eq` DECIMAL (10,2) DEFAULT NULL,
`salario_base_ef` DECIMAL (10,2) DEFAULT NULL,
`grupo_cotizacion_seg_social` INT DEFAULT NULL,
`ano_registro` DATE NOT NULL,
`id_ano_datos`INT NOT NULL,
  CONSTRAINT fk_ano_datos_datos_empleados FOREIGN KEY (id_ano_datos) REFERENCES ano_datos(id_ano_datos) ON DELETE CASCADE,
INDEX idx_ano_datos_datos_empleados(id_ano_datos)
)ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Estructura para la tabla CLIENTE_MEDIDAD para que seleccione medidas de areas que ya selecciono.
--
-- --------------------------------------------------------
CREATE TABLE `cliente_medida` (
  `id_cliente_medida` INT AUTO_INCREMENT PRIMARY KEY,
  `id_areas_contratadas` INT NOT NULL,   -- referencia al área seleccionada (cliente + área)
  `id_medida` INT NOT NULL,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cliente_medida_areas_contratadas FOREIGN KEY (id_areas_contratadas) REFERENCES areas_contratadas(id_areas_contratadas) ON DELETE CASCADE,
  CONSTRAINT fk_cliente_medida_medida FOREIGN KEY (id_medida) REFERENCES medida(id_medida) ON DELETE RESTRICT,

  UNIQUE KEY uq_areascontratadas_medida (id_areas_contratadas, id_medida),
  INDEX idx_cliente_medida_areascontratadas (id_areas_contratadas),
  INDEX idx_cliente_medida_medida (id_medida)
) ENGINE=InnoDB;

--
-- Estructura para la tabla AREA (Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral) 
--

CREATE TABLE `area_ejercicio`(
`id_ejercicio`INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
`medida`text,
`solicita_mujeres`INT NOT NULL DEFAULT 0,
`solicita_hombres`INT NOT NULL DEFAULT 0,
`concede_mujeres`INT NOT NULL DEFAULT 0,
`concede_hombres`INT NOT NULL DEFAULT 0,
`id_cliente_medida`INT NOT NULL,
CONSTRAINT fk_ejercicio_cliente_medida FOREIGN KEY (id_cliente_medida) REFERENCES cliente_medida(id_cliente_medida) ON DELETE RESTRICT,
INDEX idx_area_ejercicio_plan (id_cliente_medida))ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Estructura para la tabla AREA (Infrarrepresentación femenina ) 
--

CREATE TABLE `area_infra`(
`id_infra`INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
`plantilla_mujeres`INT NOT NULL DEFAULT 0 ,
`plantilla_hombres`INT NOT NULL DEFAULT 0, 
`id_cliente_medida`INT NOT NULL,
CONSTRAINT fk_infra_cliente_medida FOREIGN KEY (id_cliente_medida) REFERENCES cliente_medida(id_cliente_medida) ON DELETE RESTRICT,
INDEX idx_area_infra_plan (id_cliente_medida))ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Estructura para la tabla AREA(Retribuciones y auditoría salarial ) 
--

CREATE TABLE `area_retribuciones`(
`id_retribuciones`INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
`permisos`VARCHAR(255),
`num_mujeres`INT NOT NULL DEFAULT 0,
`num_hombres`INT NOT NULL DEFAULT 0,
`id_cliente_medida`INT NOT NULL,
CONSTRAINT fk_retribuciones_cliente_medida FOREIGN KEY (id_cliente_medida) REFERENCES cliente_medida(id_cliente_medida) ON DELETE RESTRICT,
INDEX idx_area_retribuciones_plan (id_cliente_medida))ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Estructura para la tabla AREA (Prevención del acoso sexual y por razón de sexo ) 
--

CREATE TABLE `area_acoso`(
`id_acoso`INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
`incidente`VARCHAR(255) NOT NULL,
`procedimiento`VARCHAR(255) NOT NULL,
`grado_incidencia`VARCHAR(255) NOT NULL,
`fecha_alta`DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
`acciones`VARCHAR(255) DEFAULT NULL,
`id_cliente_medida`INT NOT NULL,
CONSTRAINT fk_acoso_cliente_medida FOREIGN KEY (id_cliente_medida) REFERENCES cliente_medida(id_cliente_medida) ON DELETE RESTRICT,
INDEX idx_area_acoso_plan (id_cliente_medida))ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Estructura para la tabla AREA (Violencia de género) 
--

CREATE TABLE `area_violencia`(
`id_violencia`INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
`acciones`VARCHAR(255) NOT NULL,
`observaciones`VARCHAR(255) NOT NULL,
`fecha_alta`DATE NOT NULL,
`solicita_mujeres`INT NOT NULL DEFAULT 0,
`id_cliente_medida`INT NOT NULL,
CONSTRAINT fk_violencia_cliente_medida FOREIGN KEY (id_cliente_medida) REFERENCES cliente_medida(id_cliente_medida) ON DELETE RESTRICT,
INDEX idx_area_violencia_plan (id_cliente_medida))ENGINE=InnoDB;

-- --------------------------------------------------------
-- 
-- Tabla area_Responsable_de_igualdad
-- 

CREATE TABLE `area_responsable_igualdad` (
`id_responsable_de_igualdad`INT AUTO_INCREMENT PRIMARY KEY,
 `nombre`VARCHAR(100) NOT NULL,
 `email`VARCHAR(255) NOT NULL,
`id_areas_contratadas`INT NOT NULL,
CONSTRAINT fk_responsable_areascontratadas FOREIGN KEY (id_areas_contratadas) REFERENCES areas_contratadas(id_areas_contratadas) ON DELETE CASCADE,
INDEX idx_area_responsable_areascontratadas (id_areas_contratadas)
)ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Estructura para la tabla Proceso_de_seleccion_y_contratacion
--

CREATE TABLE `area_seleccion`(
`id_seleccion`INT AUTO_INCREMENT PRIMARY KEY,
`puesto_actual`VARCHAR(100) NOT NULL,
`fecha_alta`DATE NOT NULL,
`responsable`VARCHAR(100) NOT NULL,
`responsable_Int_Ext`VARCHAR(100) NOT NULL,
`crgo_responsable`ENUM('Masculino','Femenino') NOT NULL,
`gnro_seleccionado`ENUM('Masculino','Femenino') NOT NULL,
`c_mujeres`INT NOT NULL,
`c_hombres`INT NOT NULL,
`criterio_seleccion`VARCHAR(100) NOT NULL,
`id_cliente_medida`INT NOT NULL,
CONSTRAINT fk_seleccion_cliente_medida FOREIGN KEY (id_cliente_medida) REFERENCES cliente_medida(id_cliente_medida) ON DELETE RESTRICT,
INDEX idx_area_seleccion_plan (id_cliente_medida))ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Tablas de area CALIFICAICON PROFESIONAL
-- Son todo graficos hay que mirarlo
--

CREATE TABLE `area_clasificacion`(
 `id_clasificacion`INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
 `clasificacion`VARCHAR(100) NOT NULL,
 `promocion`VARCHAR(100) NOT NULL,
 `seleccion`VARCHAR(100) NOT NULL,
 `formacion`VARCHAR(100) NOT NULL,
 `id_cliente_medida`INT NOT NULL,
CONSTRAINT fk_clasificacion_cliente_medida FOREIGN KEY (id_cliente_medida) REFERENCES cliente_medida(id_cliente_medida) ON DELETE RESTRICT,
INDEX idx_area_clasificacion_plan (id_cliente_medida))ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Tablas de area FORMACION
--

CREATE TABLE `area_formacion`(
`id_formacion`INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
 `nombre`VARCHAR(100) NOT NULL,
 `fecha_inicio`DATE NOT NULL,
 `fecha_fin`DATE NOT NULL,
 `laboral`ENUM('Dentro','Fuera') NOT NULL,
 `modalidad`VARCHAR(100) NOT NULL,
 `voluntaria_obligatoria`ENUM('Voluntaria','Obligatoria') NOT NULL,
 `n_horas`INT NOT NULL,
 `n_hombres`INT NOT NULL,
 `n_mujeres`INT NOT NULL,
 `informado_plantilla`VARCHAR(100) NOT NULL,
 `criterio_seleccion`VARCHAR(100) NOT NULL,
`id_cliente_medida`INT NOT NULL,
CONSTRAINT fk_formacion_cliente_medida FOREIGN KEY (id_cliente_medida) REFERENCES cliente_medida(id_cliente_medida) ON DELETE RESTRICT,
INDEX idx_area_formacion_plan (id_cliente_medida))ENGINE=InnoDB;


-- --------------------------------------------------------

--
-- Tablas de AREA PROMOCION Y ASCENSO PERSONAL
--

CREATE TABLE `area_promocion_ascenso_personal` (
     `id_promocion` INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
    `puesto_origen` VARCHAR(100) NOT NULL,
    `puesto_destino` VARCHAR(100) NOT NULL,
    `aumento_economico` INT NOT NULL,
   `n_candidaturas` INT NOT NULL,
   `n_hombres` INT NOT NULL,
    `n_mujeres` INT NOT NULL,
    `responsable` VARCHAR(100) NOT NULL,
    `cargo_responsable` VARCHAR(100) NOT NULL,
    `genero_responsable` ENUM('Masculino','Femenino') NOT NULL,
    `genero_promocionado` ENUM('Masculino','Femenino') NOT NULL,
    `interna_externa` ENUM('Interna','Externa'),
    `contrato_inicial` VARCHAR(100) NOT NULL,
    `contrato_final` VARCHAR(100) NOT NULL,
    `tipo_promocion` VARCHAR(100) NOT NULL,
    `fecha_de_alta` DATE NOT NULL,
    `porcentaje_jornada` INT NOT NULL,
    `disfruta_conciliacion` BOOLEAN, 
    `criterio` VARCHAR(100) NOT NULL,
    `id_cliente_medida` INT NOT NULL,
CONSTRAINT fk_promocion_ascenso_plan FOREIGN KEY (id_cliente_medida) REFERENCES cliente_medida(id_cliente_medida) ON DELETE RESTRICT,
INDEX idx_area_promocion_ascenso_plan (id_cliente_medida))ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Tablas de AREA CONDICIONES DE TRABAJO
--


CREATE TABLE `area_condiciones_trabajo`(
 `id_condiciones`INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
`n_conversiones_contrato`VARCHAR(100) NOT NULL,
`n_jornadas_ampliadas`VARCHAR(100) NOT NULL,
 `evaluacion_condiciones_trabajo`VARCHAR(100) NOT NULL,
 `muestreo`VARCHAR(100) NOT NULL,
 `contrataciones_realizadas`INT(11) NOT NULL,
 `id_cliente_medida`INT NOT NULL,
CONSTRAINT fk_condiciones_plan FOREIGN KEY (id_cliente_medida) REFERENCES cliente_medida(id_cliente_medida) ON DELETE RESTRICT,
INDEX idx_area_condiciones_plan (id_cliente_medida))ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Tablas de AREA DE SALUD LABORAL
--

CREATE TABLE `area_salud`(
`id_salud`INT AUTO_INCREMENT PRIMARY KEY,
`nombre`VARCHAR(100) NOT NULL,
`procedencia`VARCHAR(255) NOT NULL,
`observaciones`VARCHAR(100),
 `id_cliente_medida`INT NOT NULL,
CONSTRAINT fk_salud_plan FOREIGN KEY (id_cliente_medida) REFERENCES cliente_medida(id_cliente_medida) ON DELETE RESTRICT,
INDEX idx_area_salud_plan (id_cliente_medida))ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Estructura para la tabla CONTRATO
--
CREATE TABLE `contrato`(
`id_contrato`INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
`nombre`ENUM ('CONTRATO','PLAN') NOT NULL
)ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Estructura para la tabla BAJAS
--

CREATE TABLE `bajas`(
`id_bajas`INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
`tipo`ENUM('TEMPORALES' , 'DEFINITIVAS'),
`id_ano_datos`INT NOT  NULL,
`id_empresa` INT NOT NULL,
  CONSTRAINT Fk_ano_bajas FOREIGN KEY (id_ano_datos) REFERENCES ano_datos(iD_ano_datos) ON DELETE CASCADE,
  CONSTRAINT fk_bajas_empresa FOREIGN KEY (id_empresa) REFERENCES empresa(id_empresa) ON DELETE CASCADE
)ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Estructura para la tabla BAJA TEMPORALES
--
CREATE TABLE `baja_temporales`(
`id_temporales`INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
`motivo`VARCHAR(255) NOT NULL,
`tipo`ENUM('Enfermedad Común','Accidente Laboral','Riesgo embarazo','COVID') NOT NULL,
`num_mujeres`INT NOT NULL DEFAULT 0,
`num_hombres`INT NOT NULL DEFAULT 0, 
`id_ano_datos`INT NOT  NULL,
`id_empresa` INT NOT NULL,
  CONSTRAINT fk_anotemporales_bajas FOREIGN KEY (id_ano_datos) REFERENCES ano_datos(iD_ano_datos) ON DELETE CASCADE,
  CONSTRAINT fk_bajastemporales_empresa FOREIGN KEY (id_empresa) REFERENCES empresa(id_empresa) ON DELETE CASCADE,
`id_bajas`INT NOT NULL,
CONSTRAINT fk_temporales_bajas FOREIGN KEY (id_bajas) REFERENCES bajas(id_bajas)ON DELETE CASCADE)ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Estructura para la tabla BAJA DEFINITIVAS
--

CREATE TABLE `baja_definitivas`(
`id_definitivas`INT PRIMARY KEY AUTO_INCREMENT NOT NULL,
`motivo`VARCHAR(255) DEFAULT NULL,
`tipo`ENUM('Despido','Fallecimiento','Finalización contrato','Jubilación','No superación de periodo de prueba','Baja voluntaria') NOT NULL,
`num_mujeres`INT NOT NULL DEFAULT 0,
`num_hombres`INT NOT NULL DEFAULT 0,
`id_ano_datos`INT NOT  NULL,
`id_empresa` INT NOT NULL,
  CONSTRAINT fk_anodefinitivas_bajas FOREIGN KEY (id_ano_datos) REFERENCES ano_datos(iD_ano_datos) ON DELETE CASCADE,
  CONSTRAINT fk_bajasdefinitivas_empresa FOREIGN KEY (id_empresa) REFERENCES empresa(id_empresa) ON DELETE CASCADE,
`id_bajas`INT NOT NULL,
CONSTRAINT fk_definitivas_bajas FOREIGN KEY (id_bajas) REFERENCES bajas(id_bajas)ON DELETE CASCADE)ENGINE=InnoDB;



-- --------------------------------------------------------

--
-- Estructura para la tabla EXCENDENCIAS
--

CREATE TABLE `area_excedencias`(
`id_excedencias`INT AUTO_INCREMENT PRIMARY KEY,
`motivo`VARCHAR(100) DEFAULT NULL,
`tipo`ENUM('Excedencias Voluntarias','Excedencias Cuidado Menores','Excedencias Cuidado de Personas Mayores') NOT NULL,
`n_mujeres`INT DEFAULT 0,
`n_hombres`INT DEFAULT 0,
`id_ano_datos`INT NOT NULL,
`id_empresa` INT NOT NULL,
  CONSTRAINT fk_ano_excedencias FOREIGN KEY (id_ano_datos) REFERENCES ano_datos(iD_ano_datos) ON DELETE CASCADE,
  CONSTRAINT fk_excedencias_empresa FOREIGN KEY (id_empresa) REFERENCES empresa(id_empresa) ON DELETE CASCADE,
    INDEX idx_excedencias_empresa (id_ano_datos))ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Estructura para la tabla Formacion
--

CREATE TABLE `area_formaciones`(
`id_formaciones`INT AUTO_INCREMENT PRIMARY KEY,
`tipo`VARCHAR(100) DEFAULT NULL,
`n_mujeres`INT DEFAULT 0,
`n_hombres`INT DEFAULT 0,
`id_ano_datos`INT NOT NULL,
`id_empresa` INT NOT NULL,
  CONSTRAINT fk_formaciones_ano FOREIGN KEY (id_ano_datos) REFERENCES ano_datos(id_ano_datos) ON DELETE CASCADE,
  CONSTRAINT fk_formaciones_empresa FOREIGN KEY (id_empresa) REFERENCES empresa(id_empresa) ON DELETE CASCADE,
    INDEX idx_formaciones_ano_empresa (id_ano_datos))ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Estructura para la tabla Permisos Retribuidos
--

CREATE TABLE `area_Permisos_retribuidos`(
`id_permisos_retribuidos`INT AUTO_INCREMENT PRIMARY KEY,
`motivo`VARCHAR(100) DEFAULT NULL,
`tipo` ENUM('Lactancia','Nacimiento') NOT NULL,
`n_mujeres`INT DEFAULT 0,
`n_hombres`INT DEFAULT 0,
`id_ano_datos`INT  NOT NULL,
`id_empresa` INT NOT NULL,
  CONSTRAINT fk_permisos_retribuidos_empresa FOREIGN KEY (id_empresa) REFERENCES empresa(id_empresa) ON DELETE CASCADE,
  CONSTRAINT fk_permisos_ano FOREIGN KEY (id_ano_datos) REFERENCES ano_datos(id_ano_datos) ON DELETE CASCADE,
    INDEX idx_permisos_retribuidos_empresa (id_ano_datos))ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Estructura para la tabla reducciones_jornada
--

CREATE TABLE `area_reducciones_jornada`(
`id_permisos`INT AUTO_INCREMENT PRIMARY KEY,
`reduccion_jornada`VARCHAR(100) NOT NULL,
`n_mujeres`INT DEFAULT 0,
`n_hombres`INT DEFAULT 0,
`id_ano_datos`INT NOT NULL,
  CONSTRAINT Fk_reducciones_empresa FOREIGN KEY (id_ano_datos) REFERENCES ano_datos(id_ano_datos) ON DELETE CASCADE,
   INDEX idx_reducciones_empresa (id_ano_datos))ENGINE=InnoDB;
   -- --------------------------------------------------------

--
-- Estructura para la tabla adaptaciones_jornada
--

CREATE TABLE `area_adaptaciones_jornada`(
`id_permisos`INT AUTO_INCREMENT PRIMARY KEY,
`adaptacion`VARCHAR(100) NOT NULL,
`n_mujeres`INT DEFAULT 0,
`n_hombres`INT DEFAULT 0,
`id_ano_datos`INT NOT NULL,
  CONSTRAINT fk_adaptaciones_empresa FOREIGN KEY (id_ano_datos) REFERENCES ano_datos(id_ano_datos) ON DELETE CASCADE,
   INDEX idx_adaptaciones_empresa (id_ano_datos))ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Estructura Cuestionario Cualitativo
--
-- --------------------------------------------------------

--
-- Estructura para la tabla cuestionario_seleccion_personal
--

CREATE TABLE `cuestionario_seleccion_personal`(
`id_cuestionario_seleccion_personal`INT AUTO_INCREMENT PRIMARY KEY,
`factores_determinantes`VARCHAR(255) DEFAULT NULL,
`incorporacion_nuevo_personal`VARCHAR(255) DEFAULT NULL,
`publicacion_interna`VARCHAR(255) DEFAULT NULL,
`personas_responsables`VARCHAR(255) DEFAULT NULL,
`caracteristicas_candidaturas`VARCHAR(255) DEFAULT NULL,
`entrevista_salida`VARCHAR(255) DEFAULT NULL,
`sistema_reclutamiento`VARCHAR(255) DEFAULT NULL,
`definicion_perfiles`VARCHAR(255) DEFAULT NULL,
`metodos_seleccion`VARCHAR(255) DEFAULT NULL,
`ultima_decision`VARCHAR(255) DEFAULT NULL,
`barreras_internas_externas`VARCHAR(255) DEFAULT NULL,
`id_ano_datos`INT  NOT NULL,
`id_empresa` INT NOT NULL,
  CONSTRAINT fk_cuestionario_seleccion_personal_empresa FOREIGN KEY (id_empresa) REFERENCES empresa(id_empresa) ON DELETE CASCADE,
  CONSTRAINT fk_seleccion_personal_ano FOREIGN KEY (id_ano_datos) REFERENCES ano_datos(id_ano_datos) ON DELETE CASCADE,
    INDEX idx_cuestionario_seleccion_personal_empresa (id_ano_datos))ENGINE=InnoDB;
SELECT * FROM cuestionario_seleccion_personal;
-- --------------------------------------------------------

--
-- Estructura para la tabla cuestionario_promocion_profesional
--

CREATE TABLE `cuestionario_promocion_profesional`(
`id_promocion_profesional`INT AUTO_INCREMENT PRIMARY KEY,
`metodologia`VARCHAR(255) DEFAULT NULL,
`metodologia_evaluacion`VARCHAR(255) DEFAULT NULL,
`personas_intervienen`VARCHAR(255) DEFAULT NULL,
`formacion_ligada`VARCHAR(255) DEFAULT NULL,
`acciones_fomentar`VARCHAR(255) DEFAULT NULL,
`requisitos`VARCHAR(255) DEFAULT NULL,
`planes_carrera`VARCHAR(255) DEFAULT NULL,
`comunicacion_vacantes`VARCHAR(255) DEFAULT NULL,
`dificultades_promocion`VARCHAR(255) DEFAULT NULL,
`id_ano_datos`INT  NOT NULL,
`id_empresa` INT NOT NULL,
  CONSTRAINT fk_cuestionario_promocion_profesional_empresa FOREIGN KEY (id_empresa) REFERENCES empresa(id_empresa) ON DELETE CASCADE,
  CONSTRAINT fk_promocion_profesional_ano FOREIGN KEY (id_ano_datos) REFERENCES ano_datos(id_ano_datos) ON DELETE CASCADE,
    INDEX idx_cuestionario_promocion_profesional_empresa (id_ano_datos))ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Estructura para la tabla cuestionario_formacion
--

CREATE TABLE `cuestionario_formacion`(
`id_cuestionario_formacion`INT AUTO_INCREMENT PRIMARY KEY,
`deteccion_formativas`VARCHAR(255) DEFAULT NULL,
`difusion_ofertas`VARCHAR(255) DEFAULT NULL,
`puede_solicitar`VARCHAR(255) DEFAULT NULL,
`compensacion_fuera`VARCHAR(255) DEFAULT NULL,
`posibilidad_formacion`VARCHAR(255) DEFAULT NULL,
`formacion_mujeres`VARCHAR(255) DEFAULT NULL,
`existencia_plan`VARCHAR(255) DEFAULT NULL,
`asisten_igualmente`VARCHAR(255) DEFAULT NULL,
`criterios_seleccion`VARCHAR(255) DEFAULT NULL,
`impartacion_fuera`VARCHAR(255) DEFAULT NULL,
`ayudas_formacion`VARCHAR(255) DEFAULT NULL,
`formacion_igualdad`VARCHAR(255) DEFAULT NULL,
`coste_medio`VARCHAR(255) DEFAULT NULL,
`formacion_reciclaje`VARCHAR(255) DEFAULT NULL,
`id_ano_datos`INT  NOT NULL,
`id_empresa` INT NOT NULL,
  CONSTRAINT fk_cuestionario_formacion_empresa FOREIGN KEY (id_empresa) REFERENCES empresa(id_empresa) ON DELETE CASCADE,
  CONSTRAINT fk_cuestionario_formacion_ano FOREIGN KEY (id_ano_datos) REFERENCES ano_datos(id_ano_datos) ON DELETE CASCADE,
    INDEX idx_cuestionario_formacion_empresa (id_ano_datos))ENGINE=InnoDB;
    -- --------------------------------------------------------

--
-- Estructura para la tabla cuestionario_conciliacion_corresponsabilidad
--

CREATE TABLE `cuestionario_conciliacion_corresponsabilidad`(
`id_conciliacion_corresponsabilidad`INT AUTO_INCREMENT PRIMARY KEY,
`ordenacion_tiempo`VARCHAR(255) DEFAULT NULL,
`quienes_utilizan`VARCHAR(255) DEFAULT NULL,
`reduccion_jornada`VARCHAR(255) DEFAULT NULL,
`mecanismos_disponibles`VARCHAR(255) DEFAULT NULL,
`cuantas_personas`VARCHAR(255) DEFAULT NULL,
`canales_informacion`VARCHAR(255) DEFAULT NULL,
`id_ano_datos`INT  NOT NULL,
`id_empresa` INT NOT NULL,
  CONSTRAINT fk_cuestionario_conciliacion_corresponsabilidad_empresa FOREIGN KEY (id_empresa) REFERENCES empresa(id_empresa) ON DELETE CASCADE,
  CONSTRAINT fk_cuestionario_conciliacion_corresponsabilidad_ano FOREIGN KEY (id_ano_datos) REFERENCES ano_datos(id_ano_datos) ON DELETE CASCADE,
    INDEX idx_cuestionario_conciliacion_corresponsabilidad_empresa (id_ano_datos))ENGINE=InnoDB;

    -- --------------------------------------------------------

--
-- Estructura para la tabla cuestionario_infrarrepresentacion_femenina
--

CREATE TABLE `cuestionario_infrarrepresentacion_femenina`(
`id_infrarrepresentacion_femenina`INT AUTO_INCREMENT PRIMARY KEY,
`barreras_internas`VARCHAR(255) DEFAULT NULL,
`hay_mujeres`VARCHAR(255) DEFAULT NULL,
`id_ano_datos`INT  NOT NULL,
`id_empresa` INT NOT NULL,
  CONSTRAINT fk_cuestionario_infrarrepresentacion_femenina_empresa FOREIGN KEY (id_empresa) REFERENCES empresa(id_empresa) ON DELETE CASCADE,
  CONSTRAINT fk_cuestionario_infrarrepresentacion_femenina_ano FOREIGN KEY (id_ano_datos) REFERENCES ano_datos(id_ano_datos) ON DELETE CASCADE,
    INDEX idx_cuestionario_infrarrepresentacion_femenina_empresa (id_ano_datos))ENGINE=InnoDB;
    
-- --------------------------------------------------------

--
-- Estructura para la tabla cuestionario_salud_laboral
--

CREATE TABLE `cuestionario_salud_laboral`(
`id_salud_laboral`INT AUTO_INCREMENT PRIMARY KEY,
`seguridad_salud`VARCHAR(255) DEFAULT NULL,
`medidas_linea`VARCHAR(255) DEFAULT NULL,
`incluido_perspectiva`VARCHAR(255) DEFAULT NULL,
`permite_desconexion`VARCHAR(255) DEFAULT NULL,
`id_ano_datos`INT  NOT NULL,
`id_empresa` INT NOT NULL,
  CONSTRAINT fk_cuestionario_salud_laboral_empresa FOREIGN KEY (id_empresa) REFERENCES empresa(id_empresa) ON DELETE CASCADE,
  CONSTRAINT fk_cuestionario_salud_laboral_ano FOREIGN KEY (id_ano_datos) REFERENCES ano_datos(id_ano_datos) ON DELETE CASCADE,
    INDEX idx_cuestionario_salud_laboral_empresa (id_ano_datos))ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Estructura para la tabla cuestionario_prevencion_acoso_sexual
--

CREATE TABLE `cuestionario_prevencion_acoso_sexual`(
`id_prevencion_acoso_sexual`INT AUTO_INCREMENT PRIMARY KEY,
`conocen_acoso`VARCHAR(255) DEFAULT NULL,
`protocolo_prevencion`VARCHAR(255) DEFAULT NULL,
`medidas_sensibilizacion`VARCHAR(255) DEFAULT NULL,
`id_ano_datos`INT  NOT NULL,
`id_empresa` INT NOT NULL,
  CONSTRAINT fk_cuestionario_prevencion_acoso_sexual_empresa FOREIGN KEY (id_empresa) REFERENCES empresa(id_empresa) ON DELETE CASCADE,
  CONSTRAINT fk_cuestionario_prevencion_acoso_sexual_ano FOREIGN KEY (id_ano_datos) REFERENCES ano_datos(id_ano_datos) ON DELETE CASCADE,
    INDEX idx_cuestionario_prevencion_acoso_sexual_empresa (id_ano_datos))ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Estructura para la tabla cuestionario_violencia_genero
--

CREATE TABLE `cuestionario_violencia_genero`(
`id_violencia_genero`INT AUTO_INCREMENT PRIMARY KEY,
`conocimiento_contratada`VARCHAR(255) DEFAULT NULL,
`prevision_progama`VARCHAR(255) DEFAULT NULL,
`id_ano_datos`INT  NOT NULL,
`id_empresa` INT NOT NULL,
  CONSTRAINT fk_cuestionario_violencia_genero_empresa FOREIGN KEY (id_empresa) REFERENCES empresa(id_empresa) ON DELETE CASCADE,
  CONSTRAINT fk_cuestionario_violencia_genero_ano FOREIGN KEY (id_ano_datos) REFERENCES ano_datos(id_ano_datos) ON DELETE CASCADE,
    INDEX idx_cuestionario_violencia_genero_empresa (id_ano_datos))ENGINE=InnoDB;
    
-- --------------------------------------------------------

--
-- Estructura para la tabla cuestionario_comunicacion_identidad_corporativa
--

CREATE TABLE `cuestionario_comunicacion_identidad_corporativa`(
`id_comunicacion_identidad_corporativa`INT AUTO_INCREMENT PRIMARY KEY,
`canales_comunicacion`VARCHAR(255) DEFAULT NULL,
`campanas_comunicacion`VARCHAR(255) DEFAULT NULL,
`imagen_empresa`VARCHAR(255) DEFAULT NULL,
`existencia_comunicacion`VARCHAR(255) DEFAULT NULL,
`frecuencia`VARCHAR(255) DEFAULT NULL,
`lenguaje_imagen`VARCHAR(255) DEFAULT NULL,
`objetivos`VARCHAR(255) DEFAULT NULL,
`filosofia`VARCHAR(255) DEFAULT NULL,
`procesos_calidad`VARCHAR(255) DEFAULT NULL,
`responsabilidad_social`VARCHAR(255) DEFAULT NULL,
`id_ano_datos`INT  NOT NULL,
`id_empresa` INT NOT NULL,
  CONSTRAINT fk_cuestionario_comunicacion_identidad_corporativa_empresa FOREIGN KEY (id_empresa) REFERENCES empresa(id_empresa) ON DELETE CASCADE,
  CONSTRAINT fk_cuestionario_comunicacion_identidad_corporativa_ano FOREIGN KEY (id_ano_datos) REFERENCES ano_datos(id_ano_datos) ON DELETE CASCADE,
    INDEX idx_cuestionario_comunicacion_identidad_corporativa_empresa (id_ano_datos))ENGINE=InnoDB;

-- --------------------------------------------------------

--
-- Estructura para la tabla archivo_registro_retributivo
-- tipo archivo importante

CREATE TABLE `archivos`(
  `id_archivo` INT AUTO_INCREMENT PRIMARY KEY,
  `tipo` ENUM('IGUALDAD','SELECCION','SALUD','REGISTRO_RETRIBUTIVO','COMUNICACION','LGTBI','TOMA DE DATOS','CUADRO PORCENTAJES','WORD_FINAL') NOT NULL,
  `asunto` VARCHAR(255) DEFAULT NULL,
  `nombre_original` VARCHAR(255) NOT NULL,
  `nombre_guardado` VARCHAR(255) NOT NULL,
  `ruta_relativa` VARCHAR(255) NOT NULL,
  `tamano_bytes` BIGINT NOT NULL DEFAULT 0,
  `mime` VARCHAR(120) NULL,
  `sha256` CHAR(64) NULL,
  `subido_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `id_cliente_medida` INT,
  `id_empresa` INT,
  CONSTRAINT fk_archivo_cliente_medida FOREIGN KEY (id_cliente_medida) REFERENCES cliente_medida(id_cliente_medida) ON DELETE CASCADE,
  CONSTRAINT fk_archivos_empresa FOREIGN KEY (id_empresa) REFERENCES empresa(id_empresa) ON DELETE CASCADE,
  INDEX idx_archivos_cliente_medida (id_cliente_medida),
  INDEX idx_archivos_tipo (tipo),
  INDEX idx_archivos_cliente_medida_fecha (id_cliente_medida, subido_en)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS archivo_descarga_log (
  id_descarga INT AUTO_INCREMENT PRIMARY KEY,
  id_empresa INT NOT NULL,
  id_usuario INT NOT NULL,
  tipo_descarga VARCHAR(60) NOT NULL,
  archivo VARCHAR(255) NULL,
  descargado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_descarga_empresa (id_empresa),
  INDEX idx_descarga_usuario (id_usuario),
  INDEX idx_descarga_tipo (tipo_descarga)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- --------------------------------------------------------

--
--
-- Insertar roles

INSERT INTO rol (nombre) VALUES
('ADMINISTRADOR'),
('TECNICO'),
('CLIENTE');

-- --------------------------------------------------------

--
--
-- Insertar usuarios de prueba

INSERT INTO usuario (
  nombre_usuario, apellidos, email, telefono, direccion, localidad, password, rol_id
) VALUES
(
  'admin', NULL, 'admin@igualdad.local', NULL, NULL, NULL,
  '$2y$10$Eh5qPCYyxVoLzuWQK9RQh.uy0Q/SPcki94SGko7DV9Ephp0jL4BAu',
  (SELECT id FROM rol WHERE nombre='ADMINISTRADOR' LIMIT 1)
),
(
  'tecnico', NULL, 'tecnico@igualdad.local', NULL, NULL, NULL,
  '$2y$10$VLXfptHN9s8O/HewHX9Oj.0BVP02y80Qtu/SOwBPzgt.g3qElCHm.',
  (SELECT id FROM rol WHERE nombre='TECNICO' LIMIT 1)
),
(
  'cliente', NULL, 'cliente@igualdad.local', NULL, NULL, NULL,
  '$2y$10$5Xf3bUDpX..efGXRnVryn.wRMt1bGpc1ZxXTJonYjH8Q3Y7In1EJe',
  (SELECT id FROM rol WHERE nombre='CLIENTE' LIMIT 1)
);



-- 10 empresas de ejemplo
-- Nota: id_usuario lo dejo en NULL para no romper la FK (fk_usuario_empresa).
-- Si quieres asociarlas a un usuario concreto, cambia NULL por un id_usuario que exista en tu tabla usuario.

INSERT INTO empresa (
  razon_social, nif, domicilio_social, forma_juridica, ano_constitucional,
  responsable, cargo, contacto, email, telefono,
  sector, convenio,
  personas_mujeres, personas_hombres, personas_total, centros_trabajo,
  recogida_informacion, vigencia_plan, id_usuario
) VALUES
('Indra Sistemas S.A.', 'A28599033', 'Av. de Bruselas 35, 28108 Alcobendas (Madrid)', 'Sociedad Anónima', '1992',
 'Laura Martínez', 'Directora RRHH', 'rrhh@indra-ejemplo.com', 'contacto@indra-ejemplo.com', '910000001',
 'Tecnología', 'Convenio TIC', 220, 380, 600, 4,
 'Encuestas internas y HRIS', '2025-2028', NULL),

('Iberdrola Energía S.A.U.', 'A95758389', 'Plaza Euskadi 5, 48009 Bilbao', 'Sociedad Anónima Unipersonal', '1901',
 'Carlos Gómez', 'Responsable de Personas', 'personas@iberdrola-ejemplo.com', 'info@iberdrola-ejemplo.com', '944000002',
 'Energía', 'Convenio Energía', 450, 650, 1100, 8,
 'Entrevistas y registros', '2024-2027', NULL),

('Grupo Ilunion S.L.', 'B85123456', 'C/ Albacete 3, 28027 Madrid', 'Sociedad Limitada', '1988',
 'Marta Ruiz', 'Gerente', 'marta.ruiz@ilunion-ejemplo.com', 'contacto@ilunion-ejemplo.com', '913000003',
 'Servicios', 'Convenio Limpieza', 120, 80, 200, 3,
 'Análisis documental', '2026-2029', NULL),

('Limpiezas Moratinos S.L.', 'B90234567', 'Pol. Ind. Norte, Nave 12, 41020 Sevilla', 'Sociedad Limitada', '2006',
 'Antonio Pérez', 'Administrador', 'antonio.perez@moratinos-ejemplo.com', 'info@moratinos-ejemplo.com', '955000004',
 'Limpieza', 'Convenio Limpieza', 35, 25, 60, 1,
 'Partes de trabajo y encuestas', '2025-2027', NULL),

('Empresa Ejemplo Ficticio 3', 'B55512345', 'Pol. Ind. Norte, Nave 12, 41020 Sevilla', 'Sociedad Limitada', '2006',
 'Antonio Pérez', 'Administrador', 'pepito.perez@moratinos-ejemplo.com', 'info@moratinos-ejemplo.com', '955000004',
 'Limpieza', 'Convenio Limpieza', 35, 25, 60, 1,
 'Partes de trabajo y encuestas', '2025-2027', NULL),

('Consulting Siglo XXI S.L.', 'B76543210', 'C/ Gran Vía 28, 28013 Madrid', 'Sociedad Limitada', '2011',
 'Elena Sánchez', 'CEO', 'elena.sanchez@consultingxxi-ejemplo.com', 'hola@consultingxxi-ejemplo.com', '911000005',
 'Consultoría', 'Convenio Oficinas', 18, 22, 40, 1,
 'Revisión de políticas', '2026-2028', NULL),

('Transporte Atlántico S.A.', 'A12345678', 'Av. del Puerto 10, 36201 Vigo', 'Sociedad Anónima', '1999',
 'Javier Castro', 'Director Operaciones', 'javier.castro@transatlantico-ejemplo.com', 'operaciones@transatlantico-ejemplo.com', '986000006',
 'Logística', 'Convenio Transporte', 40, 110, 150, 2,
 'Auditoría interna', '2025-2028', NULL),

('Farmacia Central Madrid S.L.', 'B33445566', 'C/ Atocha 15, 28012 Madrid', 'Sociedad Limitada', '2017',
 'Lucía Navarro', 'Titular', 'lucia.navarro@farmaciacentral-ejemplo.com', 'contacto@farmaciacentral-ejemplo.com', '914000007',
 'Sanidad', 'Convenio Comercio', 12, 6, 18, 1,
 'Registro horario y encuestas', '2026-2027', NULL),

('Construcciones Sierra Norte S.A.', 'A55667788', 'C/ Obra Nueva 7, 47001 Valladolid', 'Sociedad Anónima', '2003',
 'Roberto Molina', 'Jefe de Obra', 'roberto.molina@sierranorte-ejemplo.com', 'info@sierranorte-ejemplo.com', '983000008',
 'Construcción', 'Convenio Construcción', 15, 65, 80, 2,
 'Partes de obra', '2024-2026', NULL),

('Hostelería Costa Azul S.L.', 'B77889900', 'Paseo Marítimo 1, 29620 Torremolinos', 'Sociedad Limitada', '2014',
 'Sara León', 'Directora', 'sara.leon@costaazul-ejemplo.com', 'reservas@costaazul-ejemplo.com', '952000009',
 'Hostelería', 'Convenio Hostelería', 55, 35, 90, 1,
 'Encuestas de clima', '2026-2028', NULL),

('Educación Futuro S.Coop.', 'F11223344', 'C/ Escuela 9, 50001 Zaragoza', 'Sociedad Cooperativa', '2019',
 'Nuria Vidal', 'Coordinadora', 'nuria.vidal@educacionfuturo-ejemplo.com', 'contacto@educacionfuturo-ejemplo.com', '976000010',
 'Educación', 'Convenio Enseñanza', 28, 12, 40, 1,
 'Reuniones y actas', '2025-2027', NULL);
 
 -- INSERTS PARA LA BASE DE DATOS IGUALDAD AREAS

-- ========================================================
-- ÁREAS DEL PLAN
-- ========================================================

INSERT INTO area_plan (nombre)
SELECT 'Responsable de igualdad' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM area_plan WHERE nombre = 'Responsable de igualdad');

INSERT INTO area_plan (nombre)
SELECT 'Proceso de selección y contratación' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM area_plan WHERE nombre = 'Proceso de selección y contratación');

INSERT INTO area_plan (nombre)
SELECT 'Clasificación profesional' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM area_plan WHERE nombre = 'Clasificación profesional');

INSERT INTO area_plan (nombre)
SELECT 'Formación' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM area_plan WHERE nombre = 'Formación');

INSERT INTO area_plan (nombre)
SELECT 'Promoción y ascenso personal' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM area_plan WHERE nombre = 'Promoción y ascenso personal');

INSERT INTO area_plan (nombre)
SELECT 'Condiciones de trabajo' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM area_plan WHERE nombre = 'Condiciones de trabajo');

INSERT INTO area_plan (nombre)
SELECT 'Salud laboral' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM area_plan WHERE nombre = 'Salud laboral');

INSERT INTO area_plan (nombre)
SELECT 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM area_plan WHERE nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral');

INSERT INTO area_plan (nombre)
SELECT 'Infrarrepresentación femenina' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM area_plan WHERE nombre = 'Infrarrepresentación femenina');

INSERT INTO area_plan (nombre)
SELECT 'Retribuciones y auditoría salarial' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM area_plan WHERE nombre = 'Retribuciones y auditoría salarial');

INSERT INTO area_plan (nombre)
SELECT 'Prevención del acoso sexual y por razón de sexo' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM area_plan WHERE nombre = 'Prevención del acoso sexual y por razón de sexo');

INSERT INTO area_plan (nombre)
SELECT 'Violencia de género' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM area_plan WHERE nombre = 'Violencia de género');

INSERT INTO area_plan (nombre)
SELECT 'Comunicación y de sensibilización' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM area_plan WHERE nombre = 'Comunicación y de sensibilización');

INSERT INTO area_plan (nombre)
SELECT 'Colectivo LGTBI' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM area_plan WHERE nombre = 'Colectivo LGTBI');

-- ========================================================
-- MEDIDAS - RESPONSABLE DE IGUALDAD
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Designar una persona responsable de velar por igualdad...',
'Designación de responsable'
FROM area_plan ap
WHERE ap.nombre = 'Responsable de igualdad'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Designar una persona responsable de velar por igualdad...');

-- ========================================================
-- MEDIDAS - PROCESO DE SELECCIÓN Y CONTRATACIÓN
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Medida de acción positiva en selección de personal...',
'Revisión y descripción de los puestos de trabajo. Impacto en la contratación de nuevo personal.'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Medida de acción positiva en selección de personal...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Redactar una guía de selección con perspectiva género...',
'Redacción guía de procedimiento'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Redactar una guía de selección con perspectiva género...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Revisar ofertas de empleo con términos no sexistas...',
'Análisis de un muestreo'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Revisar ofertas de empleo con términos no sexistas...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Elaborar un guion de preguntas con perspectiva género...',
'Documento'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Elaborar un guion de preguntas con perspectiva género...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Desarrollar documento procedimiento selección perspectiva género...',
'Documento del procedimiento'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Desarrollar documento procedimiento selección perspectiva género...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Incluir mensajes invitando mujeres puestos masculinizados...',
'Análisis de un muestreo'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Incluir mensajes invitando mujeres puestos masculinizados...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Revisar descripciones sin competencias sesgadas género...',
'Análisis de muestreo'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Revisar descripciones sin competencias sesgadas género...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Ampliar fuentes reclutamiento para contratación mujeres...',
'Fuentes empleadas'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Ampliar fuentes reclutamiento para contratación mujeres...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Participación mujeres en procesos selección puestos...',
'Nº de mujeres y hombres en los procesos de selección'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Participación mujeres en procesos selección puestos...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Prioridad mujer en igualdad condiciones idoneidad...',
'Nº de candidaturas y nº de personas que acceden desagregado por sexo y puesto'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Prioridad mujer en igualdad condiciones idoneidad...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Candidatura vacantes priorizando personal interno...',
'Nº de solicitudes y nº de vacantes cubiertas por contratación interna y nº de vacantes cubiertas por contratación externa desagregadas por sexo. Explicación en aquellos casos en los que se ha recurrido a la externa'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Candidatura vacantes priorizando personal interno...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Información dificultades búsqueda personas sexo...',
'Informe de las dificultades encontradas en la búsqueda'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Información dificultades búsqueda personas sexo...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Representación equilibrada trabajadores áreas puestos...',
'Análisis de muestreo'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Representación equilibrada trabajadores áreas puestos...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Participación mujer menos 10% plantilla...',
'Número de mujeres y hombres en los procesos de selección en puestos masculinizados. Nº de mujeres en ternas finales para puestos masculinizados.'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Participación mujer menos 10% plantilla...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Facilitar información distribución hombres mujeres...',
'Datos de distribución de la plantilla departamento y puesto, tipo de contrato y jornada desagregados por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Facilitar información distribución hombres mujeres...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Información subrogaciones nuevas contrataciones desagregadas...',
'Nº de nuevas contrataciones desagregadas por sexo, tipo de contrato, jornada y turno en los diferentes puestos. Nº de subrogaciones desagregadas por sexo, tipo de contrato, jornada y turno en los diferentes puestos. (Desagregando subrogaciones y nuevas contrataciones).'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Información subrogaciones nuevas contrataciones desagregadas...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Informar plantillas tiempo parcial vacantes...',
'Impacto en la contratación de nuevo personal.'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Informar plantillas tiempo parcial vacantes...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Cubrir puestos jornada con personal interno...',
'Contrataciones realizadas por este procedimiento desagregadas por sexo'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Cubrir puestos jornada con personal interno...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Eliminar sesgos género procesos selección candidaturas...',
'Análisis de una muestra y guion elaborado: se enviará como propuesta en la primera reunión de seguimiento anual.'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Eliminar sesgos género procesos selección candidaturas...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Redactar procedimiento selección perspectiva género...',
'Redactar el procedimiento.'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Redactar procedimiento selección perspectiva género...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Informar empresas proveedoras política selección...',
'Listado de número de empresas informadas sobre número de concursos y proveedores.'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Informar empresas proveedoras política selección...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Terna final sexo infrarrepresentado puestos...',
'Número de candidaturas por sexo en la terna final: a igualdad de condiciones se debe cumplir y si no justificar.'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Terna final sexo infrarrepresentado puestos...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Colaboraciones organismos formación captar mujeres...',
'Colaboraciones establecidas y número de mujeres incorporadas por esta vía a puestos masculinizados'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Colaboraciones organismos formación captar mujeres...');

-- ========================================================
-- MEDIDAS - CLASIFICACIÓN PROFESIONAL
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Revisar documentos clasificación con términos neutros...',
'Denominaciones neutras. Documentos revisados y modificados.'
FROM area_plan ap
WHERE ap.nombre = 'Clasificación profesional'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Revisar documentos clasificación con términos neutros...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Evaluación valoración puestos trabajo perspectiva género...',
'Resultado de la evaluación de puestos de trabajo e identificación de los puestos de igual valor.'
FROM area_plan ap
WHERE ap.nombre = 'Clasificación profesional'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Evaluación valoración puestos trabajo perspectiva género...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Revisión descripción sistemática puestos trabajo...',
'Revisión y actualización (si procede) de la valoración de puestos de trabajo. Verificar si se ha realizado, o qué grado de desarrollo tiene, la revisión de la clasificación profesional.'
FROM area_plan ap
WHERE ap.nombre = 'Clasificación profesional'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Revisión descripción sistemática puestos trabajo...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Evaluación periódica encuadramiento profesional igualdad...',
'Informe explicativo. Nº de personas afectadas'
FROM area_plan ap
WHERE ap.nombre = 'Clasificación profesional'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Evaluación periódica encuadramiento profesional igualdad...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Utilizar términos neutros denominación clasificación profesional...',
'Denominaciones neutras y sistema de clasificación profesional utilizado en la empresa.'
FROM area_plan ap
WHERE ap.nombre = 'Clasificación profesional'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Utilizar términos neutros denominación clasificación profesional...');

-- ========================================================
-- MEDIDAS - FORMACIÓN
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Formar en igualdad al personal encargado...',
'Contenido de los cursos, modalidad de impartición y criterios de selección de participantes. Nº de horas y nº de personas formadas desagregado por sexo'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Formar en igualdad al personal encargado...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Formar en igualdad a toda la plantilla...',
'Contenido de los cursos, nº de horas y nº de personas formadas desagregado'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Formar en igualdad a toda la plantilla...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Incluir plan formación igualdad oportunidades acoso...',
'Número de personas formadas, desagregadas por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Incluir plan formación igualdad oportunidades acoso...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Adecuar herramientas formación perfil personas participantes...',
'Material utilizado en la sesión/acción formativa.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Adecuar herramientas formación perfil personas participantes...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Impartir formación en igualdad específica RLPT...',
'Contenidos de los cursos, nº de horas y nº de personas formadas desagregado por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Impartir formación en igualdad específica RLPT...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Revisar comisión modificar contenidos módulos formación...',
'Revisión de contenidos'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Revisar comisión modificar contenidos módulos formación...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Promover participación mujeres formativas actividades masculinizadas...',
'Contenido de la campaña,  número de mujeres a las que se aplica y nº de interesadas'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Promover participación mujeres formativas actividades masculinizadas...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Formar igualdad prevención acoso violencia género...',
'Contenido de los cursos, modalidad de impartición y criterios de selección de participantes. Nº de horas y nº de personas formadas desagregado por sexo'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Formar igualdad prevención acoso violencia género...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Difundir acciones formativas llegue toda plantilla...',
'Medio de difusión y nº de personas a las que llega.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Difundir acciones formativas llegue toda plantilla...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Proponer acciones reciclaje profesional reincorporación empresa...',
'Nº de veces que se aplica'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Proponer acciones reciclaje profesional reincorporación empresa...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Incrementar participación mujeres formación puestos masculinizados...',
'Porcentaje de participación de mujeres y hombres en esta formación'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Incrementar participación mujeres formación puestos masculinizados...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Desarrollar programas formativos capacidades directivas mujeres...',
'Programas desarrollados y número de mujeres que participan en los mismos. Número de horas'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Desarrollar programas formativos capacidades directivas mujeres...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Incorporar módulos igualdad formación personal dirección...',
'Contenidos de los módulos y nº de personas y horas desagregado por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Incorporar módulos igualdad formación personal dirección...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Caso existir RLPT impartir formación igualdad específica...',
'Contenidos de los módulos y nº de personas y horas desagregado por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Caso existir RLPT impartir formación igualdad específica...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Incluir módulos igualdad manual acogida formación...',
'Contenido de los módulos'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Incluir módulos igualdad manual acogida formación...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Revisar criterios acceso formación distintos itinerarios...',
'Nº de veces que se aplica. Revisión de los criterios'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Revisar criterios acceso formación distintos itinerarios...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar formación dentro jornada laboral facilitar...',
'Número de formaciones dentro y fuera de la jornada y según la modalidad del curso (online, presencial y/o mixta) desagregadas por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar formación dentro jornada laboral facilitar...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Informar comisión seguimiento evolución formativa plantilla...',
'Informe de formación.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Informar comisión seguimiento evolución formativa plantilla...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Fijar objetivo incrementar participación mujeres formación...',
'En el caso de no alcanzarse el objetivo se informará de la causa.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Fijar objetivo incrementar participación mujeres formación...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Identificar personal asumir funciones directiva desarrollar...',
'Programas desarrollados y número de mujeres que participan en los mismos. Número de horas.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Identificar personal asumir funciones directiva desarrollar...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar formación igualdad para toda plantilla...',
'Número de horas y personas formadas desagregadas por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar formación igualdad para toda plantilla...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Revisar criterios acceso cursos plan formación...',
'Número de veces que se aplica. Revisión de los criterios.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Revisar criterios acceso cursos plan formación...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Difundir plan formación llegue toda plantilla...',
'Medio de difusión del plan y número de personas a las que llega.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Difundir plan formación llegue toda plantilla...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar formación jornada laboral compatibilidad responsabilidades...',
'Número de formaciones dentro y fuera de la jornada desagregadas por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar formación jornada laboral compatibilidad responsabilidades...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Diseñar programas formativos identificación talento impulsar...',
'Desarrollo y contenidos. Participantes. Mujeres que promocionan.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Diseñar programas formativos identificación talento impulsar...');

-- ========================================================
-- MEDIDAS - PROMOCIÓN Y ASCENSO PERSONAL
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Reforzar criterio promociones realicen internamente...',
'Nº de promociones internas con relación al nº de contrataciones externas para las que han surgido vacantes de promoción desagregadas por sexo y puesto'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Reforzar criterio promociones realicen internamente...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realización seguimiento anual promociones desagregadas sexo...',
'Nº de promociones desagregadas por sexo y puesto de procedencia y al que acceden'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realización seguimiento anual promociones desagregadas sexo...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Establecer procedimiento promoción perfil competencias adecuadas...',
'Procedimiento elaborado. Medios por los que se difunde. Nº de mujeres y hombres a quienes llega.'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Establecer procedimiento promoción perfil competencias adecuadas...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Garantizar jornada diferente no obstáculo mandos...',
'Nº de promociones desagregadas por sexo, indicando grupo profesional y puesto funcional de origen y de destino, tipo de contrato, modalidad de jornada, y el tipo de promoción'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Garantizar jornada diferente no obstáculo mandos...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Garantizar medidas conciliación no impedimento promoción...',
'Número de personas promocionadas con disfrute de medidas de conciliación desagregado por sexo'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Garantizar medidas conciliación no impedimento promoción...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Igualdad condiciones preferencia mujeres ascenso...',
'Nº de veces que se aplica y grupos'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Igualdad condiciones preferencia mujeres ascenso...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Garantizar promociones contará menos 35% candidaturas...',
'Nº de promociones que afectan a mujeres'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Garantizar promociones contará menos 35% candidaturas...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Actualizar anualmente registro nivel estudios formación...',
'Registro del nivel de estudios de la plantilla desagregado por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Actualizar anualmente registro nivel estudios formación...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Conseguir representación significativa mujeres procesos...',
'En el caso de no alcanzarse el porcentaje se informará de la causa.'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Conseguir representación significativa mujeres procesos...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Reducir porcentaje diferencia contratación indefinida tiempo...',
'Comparativa del nº de contratos indefinidos y temporales, a tiempo completo y a tiempo parcial. Explicación justificativa en el caso de que no se haya producido reducción.'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Reducir porcentaje diferencia contratación indefinida tiempo...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Visibilizar mujeres promocionan dentro empresa...',
'Número de acciones de visualización de las mujeres promocionadas. Contenido de estas. Canales utilizados.'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Visibilizar mujeres promocionan dentro empresa...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Procesos promoción puestos mujer infrarrepresentada...',
'Informe de las razones del descarte de dichas candidaturas.'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Procesos promoción puestos mujer infrarrepresentada...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Garantizar mujeres mismas oportunidades hombres...',
'Verificar si se ha modificado el procedimiento de promoción para incorporar la perspectiva de género en el mismo.'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Garantizar mujeres mismas oportunidades hombres...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Garantizar personas acojan derechos conciliación no...',
'Número de medidas propuestas y puestas en marcha.'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Garantizar personas acojan derechos conciliación no...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Establecer procedimiento difusión vacantes promoción asegure...',
'Procedimiento establecido. Medios de difusión y número de personas a las que llegan por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Establecer procedimiento difusión vacantes promoción asegure...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Priorizar criterio promociones realicen internamente...',
'Número de promociones internas con relación al número de contrataciones externas para las que han surgido vacantes de promoción desagregadas por sexo y puesto.'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Priorizar criterio promociones realicen internamente...');

-- ========================================================
-- MEDIDAS - CONDICIONES DE TRABAJO
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Uniformidad adecuada desempeño funciones puesto...',
'Uniformes con patronaje femenino y masculino que no responda a estereotipos de género'
FROM area_plan ap
WHERE ap.nombre = 'Condiciones de trabajo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Uniformidad adecuada desempeño funciones puesto...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Crear una guía sobre reuniones eficaces...',
'Guía'
FROM area_plan ap
WHERE ap.nombre = 'Condiciones de trabajo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Crear una guía sobre reuniones eficaces...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar entrevista salida personas causan baja...',
'Muestreo'
FROM area_plan ap
WHERE ap.nombre = 'Condiciones de trabajo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar entrevista salida personas causan baja...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Informar plantilla tiempo parcial vacantes tiempo...',
'Nº de conversiones de contrato'
FROM area_plan ap
WHERE ap.nombre = 'Condiciones de trabajo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Informar plantilla tiempo parcial vacantes tiempo...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Cubrir puestos jornada personal interno sexo...',
'Nº de jornadas ampliadas'
FROM area_plan ap
WHERE ap.nombre = 'Condiciones de trabajo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Cubrir puestos jornada personal interno sexo...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Compromiso conversión menos 10% jornadas...',
'Nº de conversiones de contrato'
FROM area_plan ap
WHERE ap.nombre = 'Condiciones de trabajo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Compromiso conversión menos 10% jornadas...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Análisis periódico condiciones trabajo plantilla...',
'Evaluación de las condiciones de trabajo de la plantilla con perspectiva de género.'
FROM area_plan ap
WHERE ap.nombre = 'Condiciones de trabajo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Análisis periódico condiciones trabajo plantilla...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Facilitar cuadrantes trabajo antelación mínima días...',
'Muestreo'
FROM area_plan ap
WHERE ap.nombre = 'Condiciones de trabajo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Facilitar cuadrantes trabajo antelación mínima días...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Establecer principio vacantes tiempo completo...',
'Contrataciones realizadas por este procedimiento desagregadas por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Condiciones de trabajo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Establecer principio vacantes tiempo completo...');

-- ========================================================
-- MEDIDAS - SALUD LABORAL
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Disponer informe siniestralidad desagregado sexos...',
'Datos de siniestralidad por sexos y categoría'
FROM area_plan ap
WHERE ap.nombre = 'Salud laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Disponer informe siniestralidad desagregado sexos...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar difundir protocolo prevención riesgos embarazo...',
'Elaboración y difusión del protocolo. Número de difusiones'
FROM area_plan ap
WHERE ap.nombre = 'Salud laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar difundir protocolo prevención riesgos embarazo...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar seguimiento cumplimiento normas protección embarazo...',
'Nº de veces que se aplica el protocolo y resultados'
FROM area_plan ap
WHERE ap.nombre = 'Salud laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar seguimiento cumplimiento normas protección embarazo...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Considerar variables relacionadas sexo recogida datos...',
'Informe'
FROM area_plan ap
WHERE ap.nombre = 'Salud laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Considerar variables relacionadas sexo recogida datos...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Disponer resultados informe siniestralidad desagregado...',
'Datos de siniestralidad por sexos y categoría.'
FROM area_plan ap
WHERE ap.nombre = 'Salud laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Disponer resultados informe siniestralidad desagregado...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar vigilancia salud periódica riesgos inherentes...',
'Verificar la comunicación a todas las mujeres de la plantilla. Modificaciones realizadas en los reconocimientos médicos para tener en cuenta la perspectiva de género.'
FROM area_plan ap
WHERE ap.nombre = 'Salud laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar vigilancia salud periódica riesgos inherentes...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Reconocimientos médicos realizados empresa perspectiva género...',
'Reconocimientos efectuados'
FROM area_plan ap
WHERE ap.nombre = 'Salud laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Reconocimientos médicos realizados empresa perspectiva género...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Disponer informe siniestralidad considerando variables sexo...',
'Datos de siniestralidad por sexos y categoría. Incorporación de la perspectiva de género.'
FROM area_plan ap
WHERE ap.nombre = 'Salud laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Disponer informe siniestralidad considerando variables sexo...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Incorporar perspectiva género campañas seguridad bienestar...',
'Incorporación de la perspectiva de género.'
FROM area_plan ap
WHERE ap.nombre = 'Salud laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Incorporar perspectiva género campañas seguridad bienestar...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Solicitar empresas garanticen espacio mobiliario adecuado...',
'Informe sobre espacios disponibles en los centros de trabajo. Número de espacios habilitados por centro.'
FROM area_plan ap
WHERE ap.nombre = 'Salud laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Solicitar empresas garanticen espacio mobiliario adecuado...');

-- ========================================================
-- MEDIDAS - EJERCICIO CORRESPONSABLE VIDA PERSONAL FAMILIAR LABORAL
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Disponer menos 2 días laborables control médico...',
'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Disponer menos 2 días laborables control médico...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Siempre posibilidad cambio turno movilidad geográfica...',
'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Siempre posibilidad cambio turno movilidad geográfica...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Reservar puesto trabajo tiempo excedencia cuidado...',
'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Reservar puesto trabajo tiempo excedencia cuidado...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Facilitar proceso solicitud adaptación horaria jornada...',
'Proceso/canal que se ha establecido para la solicitud. Número de solicitudes y veces que se aplica. Número de solicitudes denegadas y motivos de la denegación.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Facilitar proceso solicitud adaptación horaria jornada...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Conceder permisos retribuidos tiempo imprescindible reproducción...',
'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Conceder permisos retribuidos tiempo imprescindible reproducción...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Flexibilizar permiso hospitalización manera discontinua...',
'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Flexibilizar permiso hospitalización manera discontinua...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Establecer permiso retribuido tiempo tutorías matrículas...',
'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Establecer permiso retribuido tiempo tutorías matrículas...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Conceder permisos retribuidos acompañar pareja clases...',
'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Conceder permisos retribuidos acompañar pareja clases...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Considerar retribuidos permisos acompañar consultas médicas...',
'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Considerar retribuidos permisos acompañar consultas médicas...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Difundir medidas conciliación corresponsabilidad conjunto plantilla...',
'Número de medidas propuestas, comunicadas y puestas en marcha. Evolución en el uso de las medidas de conciliación y corresponsabilidad por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Difundir medidas conciliación corresponsabilidad conjunto plantilla...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Través encuestas clima laboral seguimiento necesidades...',
'Número de medidas propuestas, comunicadas y puestas en marcha. Evolución en el uso de las medidas de conciliación y corresponsabilidad por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Través encuestas clima laboral seguimiento necesidades...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Personas trabajadoras sentencia divorcio preferencia adaptar...',
'Número de medidas propuestas, comunicadas y puestas en marcha. Evolución en el uso de las medidas de conciliación y corresponsabilidad por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Personas trabajadoras sentencia divorcio preferencia adaptar...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Día por objetivos mejora 1 día extra...',
'Número de medidas propuestas, comunicadas y puestas en marcha. Evolución en el uso de las medidas de conciliación y corresponsabilidad por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Día por objetivos mejora 1 día extra...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Plus de viajes compensa económicamente días libres...',
'Número de medidas propuestas, comunicadas y puestas en marcha. Evolución en el uso de las medidas de conciliación y corresponsabilidad por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Plus de viajes compensa económicamente días libres...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Cambio turno movilidad padres madres guarda...',
'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Cambio turno movilidad padres madres guarda...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Acumulación lactancia podrá hacerse jornadas completas...',
'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Acumulación lactancia podrá hacerse jornadas completas...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Elaborar documento recoja medidas conciliación requisitos...',
'Revisar el documento. Nº de difusiones, solicitudes y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Elaborar documento recoja medidas conciliación requisitos...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar campañas informativas sensibilización trabajadores hombres...',
'Publicación de la guía y número de personas a las que se entrega'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar campañas informativas sensibilización trabajadores hombres...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Posibilitar unión permiso nacimiento hombres mujeres...',
'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Posibilitar unión permiso nacimiento hombres mujeres...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Extender derechos conciliación parejas hecho...',
'Aplicación de la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Extender derechos conciliación parejas hecho...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Suspensión contrato trabajo parto jornada completa...',
'Número de solicitudes rechazadas y motivación.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Suspensión contrato trabajo parto jornada completa...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Ofrecer jornadas continuas padres madres niños...',
'Número de solicitudes rechazadas y motivación.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Ofrecer jornadas continuas padres madres niños...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Aplicar flexibilidad entrada salida menos 1...',
'Número de solicitudes y veces que se aplica. Número de rechazadas y motivación de estas.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Aplicar flexibilidad entrada salida menos 1...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Posibilidad solicitar reducción adaptación jornada temporalmente...',
'Número de solicitudes denegadas y motivos de la denegación.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Posibilidad solicitar reducción adaptación jornada temporalmente...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Facilitar ausencia persona trabajadora casos emergencia...',
'Aplicación de la medida.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Facilitar ausencia persona trabajadora casos emergencia...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Casos ambos progenitores trabajen empresa equilibrar...',
'Número de solicitudes rechazadas y motivación.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Casos ambos progenitores trabajen empresa equilibrar...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Casos reducción jornadas menos 15% no...',
'Número de solicitudes rechazadas y motivación.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Casos reducción jornadas menos 15% no...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Trabajadores trabajadoras sentencia divorcio preferencia...',
'Número de veces que se solicita y número de veces que se aplica la medida.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Trabajadores trabajadoras sentencia divorcio preferencia...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Garantizar personas acojan derechos conciliación carrera...',
'Número de personas promocionadas y que se acogen a medidas de conciliación.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Garantizar personas acojan derechos conciliación carrera...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Desarrollo programa teletrabajo flexible valorarán puestos...',
'Número de personas que lo solicita, número de aceptados y número de rechazadas.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Desarrollo programa teletrabajo flexible valorarán puestos...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Posibilitar realización distribución irregular jornada...',
'Número de solicitudes rechazadas y motivación.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Posibilitar realización distribución irregular jornada...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Testar anualmente medidas conciliación implantadas valorar...',
'Realización de encuestas a toda la plantilla.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Testar anualmente medidas conciliación implantadas valorar...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Garantiza incentivos gratificaciones no perjudicados ejercicio...',
'Informe comparativo sobre los incentivos y gratificaciones del año anterior al disfrute del derecho y del año en el que se disfruta.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Garantiza incentivos gratificaciones no perjudicados ejercicio...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Acumulación lactancia podrá hacerse jornadas conforme...',
'Número de veces que se solicita y número de veces que se aplica la medida.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Acumulación lactancia podrá hacerse jornadas conforme...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Unificar lactancia vacaciones tras permiso nacimiento...',
'Número de veces que se solicita y número de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Unificar lactancia vacaciones tras permiso nacimiento...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Posibilitar unión permiso nacimiento hombres mujeres...',
'Número de veces que se solicita y número de veces que se aplica la medida.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Posibilitar unión permiso nacimiento hombres mujeres...');

-- ========================================================
-- MEDIDAS - INFRARREPRESENTACIÓN FEMENINA
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realización análisis políticas personal prácticas promoción...',
'Informe'
FROM area_plan ap
WHERE ap.nombre = 'Infrarrepresentación femenina'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realización análisis políticas personal prácticas promoción...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Revisión periódica equilibrio sexo plantilla ocupación...',
'Distribución de la plantilla por puestos desagregada por sexo'
FROM area_plan ap
WHERE ap.nombre = 'Infrarrepresentación femenina'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Revisión periódica equilibrio sexo plantilla ocupación...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Buscar candidaturas mujeres áreas dptos masculinizados...',
'Distribución de la plantilla por puestos desagregada por sexo'
FROM area_plan ap
WHERE ap.nombre = 'Infrarrepresentación femenina'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Buscar candidaturas mujeres áreas dptos masculinizados...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Valorar carácter anual promociones desagregadas sexo...',
'Número de medidas propuestas y puestas en marcha. Número de seguimientos de las promociones que incluya datos, desagregados por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Infrarrepresentación femenina'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Valorar carácter anual promociones desagregadas sexo...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Incrementar durante vigencia plan incorporación mujeres...',
'Comparativa anual.'
FROM area_plan ap
WHERE ap.nombre = 'Infrarrepresentación femenina'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Incrementar durante vigencia plan incorporación mujeres...');

-- ========================================================
-- MEDIDAS - RETRIBUCIONES Y AUDITORÍA SALARIAL
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar registro salarial anualmente auditoría salarial...',
'Documentación'
FROM area_plan ap
WHERE ap.nombre = 'Retribuciones y auditoría salarial'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar registro salarial anualmente auditoría salarial...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Caso detectarse desigualdades realizará plan contenga...',
'Medida acordada'
FROM area_plan ap
WHERE ap.nombre = 'Retribuciones y auditoría salarial'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Caso detectarse desigualdades realizará plan contenga...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Revisar perspectiva género criterios complementos bonus...',
'Datos'
FROM area_plan ap
WHERE ap.nombre = 'Retribuciones y auditoría salarial'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Revisar perspectiva género criterios complementos bonus...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Actualizar estudio brecha salarial revisión registro...',
'Estudio y revisión de la brecha salarial'
FROM area_plan ap
WHERE ap.nombre = 'Retribuciones y auditoría salarial'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Actualizar estudio brecha salarial revisión registro...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Garantizar objetividad conceptos definen estructura salarial...',
'Auditoría salarial'
FROM area_plan ap
WHERE ap.nombre = 'Retribuciones y auditoría salarial'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Garantizar objetividad conceptos definen estructura salarial...');

-- ========================================================
-- MEDIDAS - PREVENCIÓN ACOSO SEXUAL Y POR RAZÓN SEXO
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Revisión procedimiento actuación prevención acoso sexual...',
'Revisión del protocolo'
FROM area_plan ap
WHERE ap.nombre = 'Prevención del acoso sexual y por razón de sexo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Revisión procedimiento actuación prevención acoso sexual...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Formación específica personas integran comisión investigación...',
'Número de cursos/horas y número de participantes. Contenido de los cursos'
FROM area_plan ap
WHERE ap.nombre = 'Prevención del acoso sexual y por razón de sexo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Formación específica personas integran comisión investigación...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Formación prevención acoso sexual razón sexo...',
'Número de cursos/horas y número de participantes. Contenido de los cursos'
FROM area_plan ap
WHERE ap.nombre = 'Prevención del acoso sexual y por razón de sexo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Formación prevención acoso sexual razón sexo...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar acciones específicas sensibilización plantilla...',
'Acciones y contenido de las mismas.'
FROM area_plan ap
WHERE ap.nombre = 'Prevención del acoso sexual y por razón de sexo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar acciones específicas sensibilización plantilla...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Presentación comisión seguimiento informe anual procesos...',
'Elaboración del informe. Nº de procesos y conclusiones.'
FROM area_plan ap
WHERE ap.nombre = 'Prevención del acoso sexual y por razón de sexo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Presentación comisión seguimiento informe anual procesos...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar actuaciones información contenido procedimiento...',
'Número y tipo de actuaciones de información del protocolo.'
FROM area_plan ap
WHERE ap.nombre = 'Prevención del acoso sexual y por razón de sexo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar actuaciones información contenido procedimiento...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Pone disposición personas empleadas departamento jurídico...',
'Tratamiento y resolución de las posibles denuncias recibidas por acoso sexual y por razón de sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Prevención del acoso sexual y por razón de sexo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Pone disposición personas empleadas departamento jurídico...');

-- ========================================================
-- MEDIDAS - VIOLENCIA DE GÉNERO
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Elaborar guía violencia género difundirla plantilla...',
'Muestra de comunicaciones'
FROM area_plan ap
WHERE ap.nombre = 'Violencia de género'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Elaborar guía violencia género difundirla plantilla...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Acreditación situación víctima violencia género agresión...',
'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Violencia de género'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Acreditación situación víctima violencia género agresión...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Campaña sensibilización importancia prevenir violencia género...',
'Muestreo'
FROM area_plan ap
WHERE ap.nombre = 'Violencia de género'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Campaña sensibilización importancia prevenir violencia género...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Para hacer efectiva protección víctima violencia género...',
'Número de veces que se solicita y número de veces que se aplica el derecho a reducción de jornada o reordenación del tiempo de trabajo.'
FROM area_plan ap
WHERE ap.nombre = 'Violencia de género'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Para hacer efectiva protección víctima violencia género...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar formación materia prevención detección atención...',
'Formaciones realizadas'
FROM area_plan ap
WHERE ap.nombre = 'Violencia de género'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar formación materia prevención detección atención...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Fomentar colaboración agentes empleo procesos selección...',
'Número de veces que se recurre a agentes de empleo para facilitar la contratación de mujeres víctimas de violencia de género.'
FROM area_plan ap
WHERE ap.nombre = 'Violencia de género'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Fomentar colaboración agentes empleo procesos selección...');

-- ========================================================
-- MEDIDAS - COMUNICACIÓN Y SENSIBILIZACIÓN
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Informar empresas colaboradoras proveedoras compañía compromiso...',
'Número de veces'
FROM area_plan ap
WHERE ap.nombre = 'Comunicación y de sensibilización'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Informar empresas colaboradoras proveedoras compañía compromiso...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Incluir acogida nuevas incorporaciones información específica...',
'Documento'
FROM area_plan ap
WHERE ap.nombre = 'Comunicación y de sensibilización'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Incluir acogida nuevas incorporaciones información específica...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Difundir existencia empresa persona responsable igualdad...',
'Nº de personas informadas'
FROM area_plan ap
WHERE ap.nombre = 'Comunicación y de sensibilización'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Difundir existencia empresa persona responsable igualdad...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Utilizar campañas publicitarias logotipos reconocimientos acrediten...',
'Documento'
FROM area_plan ap
WHERE ap.nombre = 'Comunicación y de sensibilización'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Utilizar campañas publicitarias logotipos reconocimientos acrediten...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Sensibilizar campaña especial Día Internacional Violencia...',
'Campaña y contenido'
FROM area_plan ap
WHERE ap.nombre = 'Comunicación y de sensibilización'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Sensibilizar campaña especial Día Internacional Violencia...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Colaborar instituto mujeres organismo competente campañas...',
'Campaña y contenido'
FROM area_plan ap
WHERE ap.nombre = 'Comunicación y de sensibilización'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Colaborar instituto mujeres organismo competente campañas...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Difundir mediante folleto informativo canales comunicación...',
'Contenido'
FROM area_plan ap
WHERE ap.nombre = 'Comunicación y de sensibilización'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Difundir mediante folleto informativo canales comunicación...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar campaña día nacional conciliación corresponsabilidad...',
'Contenido'
FROM area_plan ap
WHERE ap.nombre = 'Comunicación y de sensibilización'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar campaña día nacional conciliación corresponsabilidad...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Visibilizar uso permisos medidas conciliación corresponsabilidad...',
'Campaña y contenido'
FROM area_plan ap
WHERE ap.nombre = 'Comunicación y de sensibilización'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Visibilizar uso permisos medidas conciliación corresponsabilidad...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Formar sensibilizar personal encargado medios comunicación...',
'Formaciones realizadas.'
FROM area_plan ap
WHERE ap.nombre = 'Comunicación y de sensibilización'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Formar sensibilizar personal encargado medios comunicación...');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Introducir página web espacio específico informar...',
'Creación de la sección y contenidos.'
FROM area_plan ap
WHERE ap.nombre = 'Comunicación y de sensibilización'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Introducir página web espacio específico informar...');

-- ========================================================
-- MEDIDAS - COLECTIVO LGTBI
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Implementación medidas protocolo normativa vigente...',
'Verificación de las medidas implementadas'
FROM area_plan ap
WHERE ap.nombre = 'Colectivo LGTBI'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Implementación medidas protocolo normativa vigente...');

-- ------------------------------
-- Cheks


