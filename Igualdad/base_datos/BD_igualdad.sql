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
SELECT * FROM empresa;

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
SELECT * FROM datos_empleados;

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
SELECT * FROM baja_temporales;
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
  `tipo` ENUM('IGUALDAD','SELECCION','SALUD','REGISTRO_RETRIBUTIVO','COMUNICACION','LGTBI','TOMA DE DATOS','CUADRO PORCENTAJES','WORD_FINAL','GENERADO WORD') NOT NULL,
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
  CONSTRAINT fk_archivo_cliente_medida FOREIGN KEY (id_cliente_medida) REFERENCES cliente_medida(id_cliente_medida) ON DELETE SET NULL,
  CONSTRAINT fk_archivos_empresa FOREIGN KEY (id_empresa) REFERENCES empresa(id_empresa) ON DELETE CASCADE,
  INDEX idx_archivos_cliente_medida (id_cliente_medida),
  INDEX idx_archivos_tipo (tipo),
  INDEX idx_archivos_cliente_medida_fecha (id_cliente_medida, subido_en)
) ENGINE=InnoDB;
Select * from archivos;

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
select * from archivo_descarga_log;
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
 


