-- --------------------------------------------------------

--
-- Insertar roles
-- 

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
SELECT ap.id_plan, 'Designar una persona responsable (y una suplente) de velar por la igualdad de trato y oportunidades dentro del organigrama de la empresa, con formación específica en la materia que gestione el Plan, participe en su implementación, desarrolle y supervise los contenidos, unifique criterios de igualdad en los procesos de selección, promoción y demás contenidos que se acuerden en el Plan e informe a la Comisión de Seguimiento.
',
'Designación de responsable'
FROM area_plan ap
WHERE ap.nombre = 'Responsable de igualdad'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Designar una persona responsable (y una suplente) de velar por la igualdad de trato y oportunidades dentro del organigrama de la empresa, con formación específica en la materia que gestione el Plan, participe en su implementación, desarrolle y supervise los contenidos, unifique criterios de igualdad en los procesos de selección, promoción y demás contenidos que se acuerden en el Plan e informe a la Comisión de Seguimiento.
');

-- ========================================================
-- MEDIDAS - PROCESO DE SELECCIÓN Y CONTRATACIÓN
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Como medida de acción positiva durante el proceso de selección de personal en igualdad de condiciones e idoneidad se tendrá en cuenta como un elemento más de decisión el porcentaje de representación de ambos sexos en el grupo y nivel al que pertenezca el puesto a cubrir.
',
'Revisión y descripción de los puestos de trabajo. Impacto en la contratación de nuevo personal.'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Como medida de acción positiva durante el proceso de selección de personal en igualdad de condiciones e idoneidad se tendrá en cuenta como un elemento más de decisión el porcentaje de representación de ambos sexos en el grupo y nivel al que pertenezca el puesto a cubrir.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Redactar una Guía de selección de personal con perspectiva de género que contenga las directrices que deben seguirse para evitar cualquier tipo de discriminación en el proceso de selección, estableciendo entrevistas objetivas y no discriminatorias asegurando que las preguntas se relacionan directamente con los requerimientos del trabajo y no con la situación personal o familiar.
',
'Redacción guía de procedimiento'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Redactar una Guía de selección de personal con perspectiva de género que contenga las directrices que deben seguirse para evitar cualquier tipo de discriminación en el proceso de selección, estableciendo entrevistas objetivas y no discriminatorias asegurando que las preguntas se relacionan directamente con los requerimientos del trabajo y no con la situación personal o familiar.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Revisar que, en las ofertas de empleo, la denominación, descripción y requisitos de acceso, se utilizan términos e imágenes no sexistas, conteniendo la denominación en neutro o en femenino y masculino. Incluyendo en las ofertas de empleo de puestos masculinizados mensajes que inviten a las mujeres a presentar su candidatura
',
'Análisis de un muestreo'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Revisar que, en las ofertas de empleo, la denominación, descripción y requisitos de acceso, se utilizan términos e imágenes no sexistas, conteniendo la denominación en neutro o en femenino y masculino. Incluyendo en las ofertas de empleo de puestos masculinizados mensajes que inviten a las mujeres a presentar su candidatura
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Elaborar un guion de preguntas para las entrevistas con perspectiva de género.
',
'Documento'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Elaborar un guion de preguntas para las entrevistas con perspectiva de género.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Desarrollar un documento sobre el procedimiento de selección con perspectiva de género.
',
'Documento del procedimiento'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Desarrollar un documento sobre el procedimiento de selección con perspectiva de género.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Incluir en las ofertas de empleo de puestos masculinizados mensajes que inviten a las mujeres a presentar su candidatura (ejemplo: “buscamos operarias y operarios”, “buscamos mujeres y hombres que cumplan los siguientes requisitos”)
',
'Análisis de un muestreo'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Incluir en las ofertas de empleo de puestos masculinizados mensajes que inviten a las mujeres a presentar su candidatura (ejemplo: “buscamos operarias y operarios”, “buscamos mujeres y hombres que cumplan los siguientes requisitos”)
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Revisar en las descripciones de puestos que en los requisitos no existan competencias sesgadas hacia un género u otro (ejemplo de sesgo en las descripciones competenciales: fuerza física, amplia disponibilidad, buena presencia…).
',
'Análisis de muestreo'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Revisar en las descripciones de puestos que en los requisitos no existan competencias sesgadas hacia un género u otro (ejemplo de sesgo en las descripciones competenciales: fuerza física, amplia disponibilidad, buena presencia…).
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Ampliar las fuentes de reclutamiento para fomentar la contratación de mujeres, especialmente, para aquellos puestos y/o departamentos donde estén infrarrepresentadas, por ejemplo a través de centros de formación.
',
'Fuentes empleadas'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Ampliar las fuentes de reclutamiento para fomentar la contratación de mujeres, especialmente, para aquellos puestos y/o departamentos donde estén infrarrepresentadas, por ejemplo a través de centros de formación.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Para cada puesto ofertado en aquellos donde no hay ninguna mujer, se procurará que al menos una mujer participe en el proceso de selección y otra en la terna final del proceso.
',
'Nº de mujeres y hombres en los procesos de selección'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Para cada puesto ofertado en aquellos donde no hay ninguna mujer, se procurará que al menos una mujer participe en el proceso de selección y otra en la terna final del proceso.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Aplicar el principio de que, en igualdad de condiciones de idoneidad y competencia, acceda una mujer en aquellos puestos donde esté infrarrepresentada.
',
'Nº de candidaturas y nº de personas que acceden desagregado por sexo y puesto'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Aplicar el principio de que, en igualdad de condiciones de idoneidad y competencia, acceda una mujer en aquellos puestos donde esté infrarrepresentada.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Garantizar que el personal pueda presentar su candidatura para cubrir vacantes de puestos/funciones, priorizando al personal interno y al sexo menos representado frente a la contratación externa, al igual que las candidaturas de personal a tiempo parcial para tiempo completo.
',
'Nº de solicitudes y nº de vacantes cubiertas por contratación interna y nº de vacantes cubiertas por contratación externa desagregadas por sexo. Explicación en aquellos casos en los que se ha recurrido a la externa'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Garantizar que el personal pueda presentar su candidatura para cubrir vacantes de puestos/funciones, priorizando al personal interno y al sexo menos representado frente a la contratación externa, al igual que las candidaturas de personal a tiempo parcial para tiempo completo.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Proporcionar a la Comisión de seguimiento información de las posibles dificultades en la búsqueda de personas de determinado sexo para cubrir puestos vacantes, según el puesto y departamento concreto así como de los posibles acuerdos con diferentes organismos y/o entidades que se pudieran establecer.
',
'Informe de las dificultades encontradas en la búsqueda'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Proporcionar a la Comisión de seguimiento información de las posibles dificultades en la búsqueda de personas de determinado sexo para cubrir puestos vacantes, según el puesto y departamento concreto así como de los posibles acuerdos con diferentes organismos y/o entidades que se pudieran establecer.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Lograr una representación equilibrada de trabajadores y trabajadoras en las distintas áreas de actividad y puestos, incrementando la presencia de mujeres donde están infrarrepresentadas.
',
'Análisis de muestreo'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Lograr una representación equilibrada de trabajadores y trabajadoras en las distintas áreas de actividad y puestos, incrementando la presencia de mujeres donde están infrarrepresentadas.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Para cada puesto ofertado en aquellos donde haya menos del 10% de mujeres en plantilla, se procurará que al menos una mujer participe en el proceso de selección. Además, se procurará que, al menos, una de las mujeres que participe se encuentre en la terna final de las candidaturas (entrevista personal en el caso de existir).
',
'Número de mujeres y hombres en los procesos de selección en puestos masculinizados. Nº de mujeres en ternas finales para puestos masculinizados.'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Para cada puesto ofertado en aquellos donde haya menos del 10% de mujeres en plantilla, se procurará que al menos una mujer participe en el proceso de selección. Además, se procurará que, al menos, una de las mujeres que participe se encuentre en la terna final de las candidaturas (entrevista personal en el caso de existir).
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Facilitar anualmente a la Comisión de seguimiento la información de la distribución de hombres y mujeres según área departamento, puesto, tipo de contrato y jornada.
',
'Datos de distribución de la plantilla departamento y puesto, tipo de contrato y jornada desagregados por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Facilitar anualmente a la Comisión de seguimiento la información de la distribución de hombres y mujeres según área departamento, puesto, tipo de contrato y jornada.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Recoger información sobre las subrogaciones y nuevas contrataciones desagregada por sexo, según el tipo de contrato, turno, jornada y puesto.
',
'Nº de nuevas contrataciones desagregadas por sexo, tipo de contrato, jornada y turno en los diferentes puestos. Nº de subrogaciones desagregadas por sexo, tipo de contrato, jornada y turno en los diferentes puestos. (Desagregando subrogaciones y nuevas contrataciones).'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Recoger información sobre las subrogaciones y nuevas contrataciones desagregada por sexo, según el tipo de contrato, turno, jornada y puesto.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Informar a las plantillas a tiempo parcial de las vacantes a tiempo completo, a través de los medios de comunicación de la empresa. Incorporar en procesos de vacantes para puestos a tiempo completo, el principio de que, en condiciones equivalentes de idoneidad, accederán las mujeres contratadas a tiempo parcial.
',
'Impacto en la contratación de nuevo personal.'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Informar a las plantillas a tiempo parcial de las vacantes a tiempo completo, a través de los medios de comunicación de la empresa. Incorporar en procesos de vacantes para puestos a tiempo completo, el principio de que, en condiciones equivalentes de idoneidad, accederán las mujeres contratadas a tiempo parcial.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Cubrir los puestos de mayor jornada preferentemente con personal interno del sexo infrarrepresentado, de manera que, de producirse una contratación externa (final) sea ésta la de menor número de horas.
',
'Contrataciones realizadas por este procedimiento desagregadas por sexo'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Cubrir los puestos de mayor jornada preferentemente con personal interno del sexo infrarrepresentado, de manera que, de producirse una contratación externa (final) sea ésta la de menor número de horas.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, '"Eliminar los sesgos de género en los procesos de selección de candidaturas.
Revisar los documentos de los procesos de selección para que no haya cuestiones no relacionadas con el currículum y/o con el ejercicio del puesto (estado civil, número de hijos, etc.) y elaborar un guion de preguntas para las entrevistas con perspectiva de género que evite posibles sesgos de género.
"
',
'Análisis de una muestra y guion elaborado: se enviará como propuesta en la primera reunión de seguimiento anual.'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = '"Eliminar los sesgos de género en los procesos de selección de candidaturas.
Revisar los documentos de los procesos de selección para que no haya cuestiones no relacionadas con el currículum y/o con el ejercicio del puesto (estado civil, número de hijos, etc.) y elaborar un guion de preguntas para las entrevistas con perspectiva de género que evite posibles sesgos de género.
"
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Redactar un procedimiento de selección con perspectiva de género y, en su caso, actualizarlo.
',
'Redactar el procedimiento.'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Redactar un procedimiento de selección con perspectiva de género y, en su caso, actualizarlo.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Informar a las empresas proveedoras de personal de la política de selección establecida según el principio de igualdad e incorporar la exigencia de actuar con los mismos criterios de igualdad.
',
'Listado de número de empresas informadas sobre número de concursos y proveedores.'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Informar a las empresas proveedoras de personal de la política de selección establecida según el principio de igualdad e incorporar la exigencia de actuar con los mismos criterios de igualdad.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'En los procesos de selección y promoción se intentará contar con al menos una persona del sexo infrarrepresentado entre la terna final de candidaturas, especialmente en la cobertura de puestos de responsabilidad donde exista infrarrepresentación femenina.  Aplicar el principio de que, en igualdad de condiciones de idoneidad y competencia, accederá al puesto vacante una mujer cuando se trate de puestos, departamentos y/o actividades masculinizados de la empresa.
',
'Número de candidaturas por sexo en la terna final: a igualdad de condiciones se debe cumplir y si no justificar.'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'En los procesos de selección y promoción se intentará contar con al menos una persona del sexo infrarrepresentado entre la terna final de candidaturas, especialmente en la cobertura de puestos de responsabilidad donde exista infrarrepresentación femenina.  Aplicar el principio de que, en igualdad de condiciones de idoneidad y competencia, accederá al puesto vacante una mujer cuando se trate de puestos, departamentos y/o actividades masculinizados de la empresa.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Establecer colaboraciones con organismos de formación para captar mujeres que quieran ocupar puestos en sectores masculinizados. 
',
'Colaboraciones establecidas y número de mujeres incorporadas por esta vía a puestos masculinizados'
FROM area_plan ap
WHERE ap.nombre = 'Proceso de selección y contratación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Establecer colaboraciones con organismos de formación para captar mujeres que quieran ocupar puestos en sectores masculinizados. 
');

-- ========================================================
-- MEDIDAS - CLASIFICACIÓN PROFESIONAL
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Revisar los documentos de clasificación de la empresa y utilizar términos neutros en la denominación y clasificación profesional, procurando no denominarlos en femenino ni masculino.
',
'Denominaciones neutras. Documentos revisados y modificados.'
FROM area_plan ap
WHERE ap.nombre = 'Clasificación profesional'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Revisar los documentos de clasificación de la empresa y utilizar términos neutros en la denominación y clasificación profesional, procurando no denominarlos en femenino ni masculino.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar una evaluación/valoración de los puestos de trabajo objetiva que mida la importancia relativa de un puesto dentro de la organización con perspectiva de género para garantizar la ausencia de discriminación directa e indirecta entre sexos, identificando puestos de igual valor
',
'Resultado de la evaluación de puestos de trabajo e identificación de los puestos de igual valor.'
FROM area_plan ap
WHERE ap.nombre = 'Clasificación profesional'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar una evaluación/valoración de los puestos de trabajo objetiva que mida la importancia relativa de un puesto dentro de la organización con perspectiva de género para garantizar la ausencia de discriminación directa e indirecta entre sexos, identificando puestos de igual valor
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, '     Realizar una revisión y descripción sistemática de los puestos de trabajo, tareas, funciones y clasificación profesional, analizando los posibles sesgos de género, para la fijación de la política retributiva.
',
'Revisión y actualización (si procede) de la valoración de puestos de trabajo. Verificar si se ha realizado, o qué grado de desarrollo tiene, la revisión de la clasificación profesional.'
FROM area_plan ap
WHERE ap.nombre = 'Clasificación profesional'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = '     Realizar una revisión y descripción sistemática de los puestos de trabajo, tareas, funciones y clasificación profesional, analizando los posibles sesgos de género, para la fijación de la política retributiva.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Establecer una evaluación periódica del encuadramiento profesional que permita corregir las situaciones que puedan estar motivadas por una minusvaloración del trabajo de las mujeres.
',
'Informe explicativo. Nº de personas afectadas'
FROM area_plan ap
WHERE ap.nombre = 'Clasificación profesional'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Establecer una evaluación periódica del encuadramiento profesional que permita corregir las situaciones que puedan estar motivadas por una minusvaloración del trabajo de las mujeres.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Utilizar términos neutros en la denominación y clasificación profesional, procurando no denominarlos en femenino ni masculino, y revisar que la clasificación profesional se ajusta al principio de igualdad.
',
'Denominaciones neutras y sistema de clasificación profesional utilizado en la empresa.'
FROM area_plan ap
WHERE ap.nombre = 'Clasificación profesional'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Utilizar términos neutros en la denominación y clasificación profesional, procurando no denominarlos en femenino ni masculino, y revisar que la clasificación profesional se ajusta al principio de igualdad.
');

-- ========================================================
-- MEDIDAS - FORMACIÓN
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Formar en igualdad al personal encargado de la selección, contratación, promoción, formación, comunicación y asignación de las retribuciones, con el objetivo de garantizar la igualdad de trato y oportunidades entre mujeres y hombres en los procesos, evitar actitudes discriminatorias y para que los candidatos y candidatas sean valorados/as únicamente por sus cualificaciones, competencias, conocimientos y experiencias, e informar del contenido concreto a la comisión de seguimiento, de la estrategia y calendarios de impartición de los cursos, además de los criterios de selección.
',
'Contenido de los cursos, modalidad de impartición y criterios de selección de participantes. Nº de horas y nº de personas formadas desagregado por sexo'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Formar en igualdad al personal encargado de la selección, contratación, promoción, formación, comunicación y asignación de las retribuciones, con el objetivo de garantizar la igualdad de trato y oportunidades entre mujeres y hombres en los procesos, evitar actitudes discriminatorias y para que los candidatos y candidatas sean valorados/as únicamente por sus cualificaciones, competencias, conocimientos y experiencias, e informar del contenido concreto a la comisión de seguimiento, de la estrategia y calendarios de impartición de los cursos, además de los criterios de selección.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Formar en igualdad a toda la plantilla con el fin de sensibilizar sobre la igualdad de oportunidades.
',
'Contenido de los cursos, nº de horas y nº de personas formadas desagregado'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Formar en igualdad a toda la plantilla con el fin de sensibilizar sobre la igualdad de oportunidades.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Incluir en el Plan de Formación, cursos en Igualdad de Oportunidades y acoso sexual, realizando talleres de sensibilización. Realizar las acciones formativas, preferentemente, en horario laboral.
',
'Número de personas formadas, desagregadas por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Incluir en el Plan de Formación, cursos en Igualdad de Oportunidades y acoso sexual, realizando talleres de sensibilización. Realizar las acciones formativas, preferentemente, en horario laboral.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Adecuar las herramientas de la formación al perfil de las personas participantes, incorporando personal para asesoramiento de nuevas tecnologías si se considera necesario
',
'Material utilizado en la sesión/acción formativa.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Adecuar las herramientas de la formación al perfil de las personas participantes, incorporando personal para asesoramiento de nuevas tecnologías si se considera necesario
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Impartir formación en igualdad específica para las personas de la RLPT
',
'Contenidos de los cursos, nº de horas y nº de personas formadas desagregado por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Impartir formación en igualdad específica para las personas de la RLPT
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Revisar en la Comisión de seguimiento, y modificar en su caso, los contenidos de los módulos y cursos de formación en igualdad de oportunidades.
',
'Revisión de contenidos'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Revisar en la Comisión de seguimiento, y modificar en su caso, los contenidos de los módulos y cursos de formación en igualdad de oportunidades.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Promover, a través de una campaña de difusión interna, la participación de mujeres en acciones formativas relacionadas con actividades masculinizadas en la empresa, y garantizar a las trabajadoras que lo soliciten como medida de acción positiva.
',
'Contenido de la campaña,  número de mujeres a las que se aplica y nº de interesadas'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Promover, a través de una campaña de difusión interna, la participación de mujeres en acciones formativas relacionadas con actividades masculinizadas en la empresa, y garantizar a las trabajadoras que lo soliciten como medida de acción positiva.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Formar en igualdad, prevención del acoso sexual y/o por razón de sexo y concienciación sobre violencia de género  a las nuevas incorporaciones
',
'Contenido de los cursos, modalidad de impartición y criterios de selección de participantes. Nº de horas y nº de personas formadas desagregado por sexo'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Formar en igualdad, prevención del acoso sexual y/o por razón de sexo y concienciación sobre violencia de género  a las nuevas incorporaciones
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Difundir las acciones formativas para que llegue a toda la plantilla, incluidas aquellas personas en situación de baja, excedencia, vacaciones, etc.
',
'Medio de difusión y nº de personas a las que llega.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Difundir las acciones formativas para que llegue a toda la plantilla, incluidas aquellas personas en situación de baja, excedencia, vacaciones, etc.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Proponer acciones formativas de reciclaje profesional a quienes se reincorporan en la Empresa a la finalización de la suspensión de contrato, por nacimiento, excedencias y bajas de larga duración.
',
'Nº de veces que se aplica'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Proponer acciones formativas de reciclaje profesional a quienes se reincorporan en la Empresa a la finalización de la suspensión de contrato, por nacimiento, excedencias y bajas de larga duración.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Incrementar la participación de mujeres en la formación específica de puestos masculinizados, con el objetivo de alcanzar, como mínimo, el 50% de participación de las mujeres.
',
'Porcentaje de participación de mujeres y hombres en esta formación'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Incrementar la participación de mujeres en la formación específica de puestos masculinizados, con el objetivo de alcanzar, como mínimo, el 50% de participación de las mujeres.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Desarrollar programas formativos en capacidades directivas a mujeres con potencial para asumir funciones directivas.
',
'Programas desarrollados y número de mujeres que participan en los mismos. Número de horas'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Desarrollar programas formativos en capacidades directivas a mujeres con potencial para asumir funciones directivas.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Incorporar módulos específicos de igualdad de trato y oportunidades entre mujeres y hombres en la formación del personal de dirección, jefaturas, cuadros y responsables de RRHH que estén implicados de una manera directa en la contratación, formación, promoción, clasificación profesional, asignación retributiva, comunicación e información de los trabajadores y las trabajadoras.
',
'Contenidos de los módulos y nº de personas y horas desagregado por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Incorporar módulos específicos de igualdad de trato y oportunidades entre mujeres y hombres en la formación del personal de dirección, jefaturas, cuadros y responsables de RRHH que estén implicados de una manera directa en la contratación, formación, promoción, clasificación profesional, asignación retributiva, comunicación e información de los trabajadores y las trabajadoras.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'En caso de existir RLPT, impartir formación en igualdad específica para las personas que la integren.
',
'Contenidos de los módulos y nº de personas y horas desagregado por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'En caso de existir RLPT, impartir formación en igualdad específica para las personas que la integren.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Incluir módulos de igualdad en el manual de acogida y en la formación dirigida a la nueva plantilla, incluido el personal incorporado por subrogación, y en la formación destinada a reciclaje.
',
'Contenido de los módulos'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Incluir módulos de igualdad en el manual de acogida y en la formación dirigida a la nueva plantilla, incluido el personal incorporado por subrogación, y en la formación destinada a reciclaje.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Revisar los criterios de acceso a la formación y establecer la posibilidad de que la persona trabajadora pueda inscribirse y realizar acciones formativas distintas a las del itinerario formativo predeterminado en su puesto (tanto los relacionados con su actividad, como los que la empresa ponga en marcha para el desarrollo profesional de la plantilla, que tengan valor profesional como incentivo al desarrollo profesional).
',
'Nº de veces que se aplica. Revisión de los criterios'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Revisar los criterios de acceso a la formación y establecer la posibilidad de que la persona trabajadora pueda inscribirse y realizar acciones formativas distintas a las del itinerario formativo predeterminado en su puesto (tanto los relacionados con su actividad, como los que la empresa ponga en marcha para el desarrollo profesional de la plantilla, que tengan valor profesional como incentivo al desarrollo profesional).
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar la formación, dentro de la jornada laboral, para facilitar su compatibilidad con las responsabilidades familiares y personales.
',
'Número de formaciones dentro y fuera de la jornada y según la modalidad del curso (online, presencial y/o mixta) desagregadas por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar la formación, dentro de la jornada laboral, para facilitar su compatibilidad con las responsabilidades familiares y personales.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Informar a la Comisión de Seguimiento de la evolución formativa de la plantilla con carácter anual, sobre el plan de formación, fechas de impartición, contenido, participación de hombres y mujeres, según el grupo profesional, departamento, puesto y según el tipo de curso y número de horas.
',
'Informe de formación.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Informar a la Comisión de Seguimiento de la evolución formativa de la plantilla con carácter anual, sobre el plan de formación, fechas de impartición, contenido, participación de hombres y mujeres, según el grupo profesional, departamento, puesto y según el tipo de curso y número de horas.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Fijar el objetivo de incrementar la participación de mujeres en la formación específica de puestos masculinizados, con el objetivo de alcanzar, como mínimo, el 35% de participación de las mujeres, a lo largo de todo el Plan. En  caso de no lograrlo deberá ser justificado.
',
'En el caso de no alcanzarse el objetivo se informará de la causa.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Fijar el objetivo de incrementar la participación de mujeres en la formación específica de puestos masculinizados, con el objetivo de alcanzar, como mínimo, el 35% de participación de las mujeres, a lo largo de todo el Plan. En  caso de no lograrlo deberá ser justificado.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Identificar al personal que pueda asumir funciones directiva y desarrollar programas formativos en capacidades directivas a mujeres.
',
'Programas desarrollados y número de mujeres que participan en los mismos. Número de horas.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Identificar al personal que pueda asumir funciones directiva y desarrollar programas formativos en capacidades directivas a mujeres.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar formación en igualdad para toda la plantilla, prestando atención a que reciban esta formación las nuevas contrataciones.
',
'Número de horas y personas formadas desagregadas por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar formación en igualdad para toda la plantilla, prestando atención a que reciban esta formación las nuevas contrataciones.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Revisar los criterios de acceso a los distintos cursos del plan de formación y establecer la posibilidad de que la persona trabajadora pueda inscribirse y realizar acciones formativas distintas a las del itinerario formativo predeterminado en su puesto (tanto los relacionados con su actividad, como los que la empresa ponga en marcha para el desarrollo profesional de la plantilla, que tengan   valor   profesional   como   incentivo   al   desarrollo profesional).
',
'Número de veces que se aplica. Revisión de los criterios.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Revisar los criterios de acceso a los distintos cursos del plan de formación y establecer la posibilidad de que la persona trabajadora pueda inscribirse y realizar acciones formativas distintas a las del itinerario formativo predeterminado en su puesto (tanto los relacionados con su actividad, como los que la empresa ponga en marcha para el desarrollo profesional de la plantilla, que tengan   valor   profesional   como   incentivo   al   desarrollo profesional).
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Difundir el plan de formación para que llegue a toda la plantilla, incluidas aquellas personas en situación de baja, excedencia, vacaciones, etc.
',
'Medio de difusión del plan y número de personas a las que llega.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Difundir el plan de formación para que llegue a toda la plantilla, incluidas aquellas personas en situación de baja, excedencia, vacaciones, etc.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar la formación, dentro de la jornada laboral, para facilitar su   compatibilidad con las responsabilidades familiares y personales.
',
'Número de formaciones dentro y fuera de la jornada desagregadas por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar la formación, dentro de la jornada laboral, para facilitar su   compatibilidad con las responsabilidades familiares y personales.
');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Diseñar programas formativos y de identificación del talento para impulsar la promoción y desarrollo específico de mujeres en la empresa en los puestos en los que están poco o nada representadas.
',
'Desarrollo y contenidos. Participantes. Mujeres que promocionan.'
FROM area_plan ap
WHERE ap.nombre = 'Formación'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Diseñar programas formativos y de identificación del talento para impulsar la promoción y desarrollo específico de mujeres en la empresa en los puestos en los que están poco o nada representadas.
');

-- ========================================================
-- MEDIDAS - PROMOCIÓN Y ASCENSO PERSONAL
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Reforzar el criterio de que las promociones se realicen internamente, solo acudiendo a contratación externa en el caso de no existir los perfiles buscados dentro de la empresa.', 'Nº de promociones internas con relación al nº de contrataciones externas para las que han surgido vacantes de promoción desagregadas por sexo y puesto'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Reforzar el criterio de que las promociones se realicen internamente, solo acudiendo a contratación externa en el caso de no existir los perfiles buscados dentro de la empresa.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realización de un seguimiento anual de las promociones desagregadas por sexo, indicando grupo profesional y puesto funcional de origen y de destino, tipo de contrato, modalidad de jornada, y el tipo de promoción para su traslado a la Comisión de seguimiento.', 'Nº de promociones desagregadas por sexo y puesto de procedencia y al que acceden'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realización de un seguimiento anual de las promociones desagregadas por sexo, indicando grupo profesional y puesto funcional de origen y de destino, tipo de contrato, modalidad de jornada, y el tipo de promoción para su traslado a la Comisión de seguimiento.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Establecer un procedimiento de promoción donde se asegure que el perfil requerido y las competencias y requisitos solicitados son los adecuados, sin sobrecualificaciones y que no existan competencias sesgadas hacia un sexo u otro (eliminando a ser posible la experiencia en aquellos casos que supone una barrera a la presentación de candidaturas femeninas).', 'Procedimiento elaborado. Medios por los que se difunde. Nº de mujeres y hombres a quienes llega.'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Establecer un procedimiento de promoción donde se asegure que el perfil requerido y las competencias y requisitos solicitados son los adecuados, sin sobrecualificaciones y que no existan competencias sesgadas hacia un sexo u otro (eliminando a ser posible la experiencia en aquellos casos que supone una barrera a la presentación de candidaturas femeninas).');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Garantizar que cualquier tipo de jornada diferente a la completa no sea un obstáculo para acceder a puestos de coordinación/mandos, haciendo un seguimiento específico de las promociones internas del grupo de personas con jornada parcial o reducida.', 'Nº de promociones desagregadas por sexo, indicando grupo profesional y puesto funcional de origen y de destino, tipo de contrato, modalidad de jornada, y el tipo de promoción'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Garantizar que cualquier tipo de jornada diferente a la completa no sea un obstáculo para acceder a puestos de coordinación/mandos, haciendo un seguimiento específico de las promociones internas del grupo de personas con jornada parcial o reducida.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Garantizar que las medidas de conciliación de la vida laboral, personal y familiar no son un impedimento para la promoción profesional.', 'Número de personas promocionadas con disfrute de medidas de conciliación desagregado por sexo'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Garantizar que las medidas de conciliación de la vida laboral, personal y familiar no son un impedimento para la promoción profesional.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'En igualdad de condiciones de idoneidad y competencia, tendrán preferencia las mujeres en el ascenso a puestos donde están infrarrepresentadas', 'Nº de veces que se aplica y grupos'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'En igualdad de condiciones de idoneidad y competencia, tendrán preferencia las mujeres en el ascenso a puestos donde están infrarrepresentadas');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Garantizar que en las promociones internas se contará con al menos un 35% de candidaturas del sexo infrarrepresentado. En el caso de que no sea posible se informará de las barreras encontradas en la presentación de candidaturas.', 'Nº de promociones que afectan a mujeres'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Garantizar que en las promociones internas se contará con al menos un 35% de candidaturas del sexo infrarrepresentado. En el caso de que no sea posible se informará de las barreras encontradas en la presentación de candidaturas.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Actualizar anualmente un registro que permita conocer el nivel de estudios y formación de la plantilla, desagregado por sexo y puesto.', 'Registro del nivel de estudios de la plantilla desagregado por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Actualizar anualmente un registro que permita conocer el nivel de estudios y formación de la plantilla, desagregado por sexo y puesto.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Con el fin de conseguir una representación significativa de mujeres en los procesos de promoción para los puestos operativos que se establezcan (responsables de equipo, inspectores/as, coordinadores/as…) se procederá a reservar inicialmente un 50% de las plazas del proceso para personal femenino.', 'En el caso de no alcanzarse el porcentaje se informará de la causa.'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Con el fin de conseguir una representación significativa de mujeres en los procesos de promoción para los puestos operativos que se establezcan (responsables de equipo, inspectores/as, coordinadores/as…) se procederá a reservar inicialmente un 50% de las plazas del proceso para personal femenino.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Reducir el porcentaje de diferencia en la contratación indefinida y a tiempo completo entre mujeres y hombres, 3% a lo largo de la vigencia del plan', 'Comparativa del nº de contratos indefinidos y temporales, a tiempo completo y a tiempo parcial. Explicación justificativa en el caso de que no se haya producido reducción.'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Reducir el porcentaje de diferencia en la contratación indefinida y a tiempo completo entre mujeres y hombres, 3% a lo largo de la vigencia del plan');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Visibilizar a las mujeres que promocionan dentro de la empresa.', 'Número de acciones de visualización de las mujeres promocionadas. Contenido de estas. Canales utilizados.'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Visibilizar a las mujeres que promocionan dentro de la empresa.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'En los procesos de promoción de puestos donde la mujer está infrarrepresentada, cuando se descarte a las candidatas femeninas se realizará un informe donde se debe indicar las razones por las que se ha descartado dicha candidatura.', 'Informe de las razones del descarte de dichas candidaturas.'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'En los procesos de promoción de puestos donde la mujer está infrarrepresentada, cuando se descarte a las candidatas femeninas se realizará un informe donde se debe indicar las razones por las que se ha descartado dicha candidatura.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Garantizar que las mujeres tienen las mismas oportunidades que los hombres de ocupar puestos de responsabilidad.', 'Verificar si se ha modificado el procedimiento de promoción para incorporar la perspectiva de género en el mismo.'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Garantizar que las mujeres tienen las mismas oportunidades que los hombres de ocupar puestos de responsabilidad.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Garantizar que las personas que se acojan a cualquiera de los derechos relacionados con la conciliación de la vida familiar y laboral (permisos, reducciones de jornada…), no vean frenado el desarrollo de su carrera profesional ni sus posibilidades de promoción ni retribución.', 'Número de medidas propuestas y puestas en marcha.'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Garantizar que las personas que se acojan a cualquiera de los derechos relacionados con la conciliación de la vida familiar y laboral (permisos, reducciones de jornada…), no vean frenado el desarrollo de su carrera profesional ni sus posibilidades de promoción ni retribución.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Establecer un procedimiento de difusión de las vacantes de promoción que asegure que las mismas llegan a toda la plantilla por los distintos canales empleados por la empresa.', 'Procedimiento establecido. Medios de difusión y número de personas a las que llegan por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Establecer un procedimiento de difusión de las vacantes de promoción que asegure que las mismas llegan a toda la plantilla por los distintos canales empleados por la empresa.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Priorizar el criterio de que las promociones se realicen internamente, solo acudiendo a contratación externa en el caso de no existir los perfiles buscados dentro de la empresa.', 'Número de promociones internas con relación al número de contrataciones externas para las que han surgido vacantes de promoción desagregadas por sexo y puesto.'
FROM area_plan ap
WHERE ap.nombre = 'Promoción y ascenso personal'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Priorizar el criterio de que las promociones se realicen internamente, solo acudiendo a contratación externa en el caso de no existir los perfiles buscados dentro de la empresa.');

-- ========================================================
-- MEDIDAS - CONDICIONES DE TRABAJO
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'La uniformidad se adecuará para el desempeño de las funciones del puesto, teniendo en cuenta las condiciones físicas de cada sexo pero sin que responda a estereotipos de género ni atente contra la dignidad de la persona, y cumpliendo con los criterios recogidos en el convenio colectivo de aplicación', 'Uniformes con patronaje femenino y masculino que no responda a estereotipos de género'
FROM area_plan ap
WHERE ap.nombre = 'Condiciones de trabajo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'La uniformidad se adecuará para el desempeño de las funciones del puesto, teniendo en cuenta las condiciones físicas de cada sexo pero sin que responda a estereotipos de género ni atente contra la dignidad de la persona, y cumpliendo con los criterios recogidos en el convenio colectivo de aplicación');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Crear una guía sobre reuniones eficaces con uso de medios telemáticos, convocatorias, duración y aprovechamiento', 'Guía'
FROM area_plan ap
WHERE ap.nombre = 'Condiciones de trabajo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Crear una guía sobre reuniones eficaces con uso de medios telemáticos, convocatorias, duración y aprovechamiento');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar una entrevista de salida a las personas que causan baja, para conocer los motivos de las bajas voluntarias desagregadas por sexo.', 'Muestreo'
FROM area_plan ap
WHERE ap.nombre = 'Condiciones de trabajo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar una entrevista de salida a las personas que causan baja, para conocer los motivos de las bajas voluntarias desagregadas por sexo.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Informar a la plantilla a tiempo parcial, de las vacantes a tiempo completo y/ o de aumento de jornada, a través de los medios de comunicación de la empresa (por centro de trabajo o distinto centro según se acuerde) y verificar que dicha comunicación se ha realizado y llega tanto a mujeres como a hombres.', 'Nº de conversiones de contrato'
FROM area_plan ap
WHERE ap.nombre = 'Condiciones de trabajo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Informar a la plantilla a tiempo parcial, de las vacantes a tiempo completo y/ o de aumento de jornada, a través de los medios de comunicación de la empresa (por centro de trabajo o distinto centro según se acuerde) y verificar que dicha comunicación se ha realizado y llega tanto a mujeres como a hombres.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Cubrir los puestos de mayor jornada preferentemente con personal interno del sexo infrarrepresentado, de manera que, de producirse una contratación externa (final) sea ésta la de menor número de horas.', 'Nº de jornadas ampliadas'
FROM area_plan ap
WHERE ap.nombre = 'Condiciones de trabajo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Cubrir los puestos de mayor jornada preferentemente con personal interno del sexo infrarrepresentado, de manera que, de producirse una contratación externa (final) sea ésta la de menor número de horas.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Compromiso de conversión de al menos un 10% de las jornadas parciales del género que más tiene este tipo de contratación en completas a lo largo de la vigencia del plan.', 'Nº de conversiones de contrato'
FROM area_plan ap
WHERE ap.nombre = 'Condiciones de trabajo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Compromiso de conversión de al menos un 10% de las jornadas parciales del género que más tiene este tipo de contratación en completas a lo largo de la vigencia del plan.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Análisis periódico de las condiciones de trabajo de la plantilla con perspectiva de género, revisando que se respete en todo momento el principio de igualdad y de no discriminación', 'Evaluación de las condiciones de trabajo de la plantilla con perspectiva de género.'
FROM area_plan ap
WHERE ap.nombre = 'Condiciones de trabajo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Análisis periódico de las condiciones de trabajo de la plantilla con perspectiva de género, revisando que se respete en todo momento el principio de igualdad y de no discriminación');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Se facilitarán los cuadrantes de trabajo con una antelación mínima de 15 días de antelación.', 'Muestreo'
FROM area_plan ap
WHERE ap.nombre = 'Condiciones de trabajo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Se facilitarán los cuadrantes de trabajo con una antelación mínima de 15 días de antelación.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Establecer el principio en las vacantes a tiempo completo de que, en condiciones equivalentes de idoneidad, se contratará a la persona perteneciente al género con mayor número de contrataciones a tiempo parcial.', 'Contrataciones realizadas por este procedimiento desagregadas por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Condiciones de trabajo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Establecer el principio en las vacantes a tiempo completo de que, en condiciones equivalentes de idoneidad, se contratará a la persona perteneciente al género con mayor número de contrataciones a tiempo parcial.');

-- ========================================================
-- MEDIDAS - SALUD LABORAL
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Disponer de un informe de siniestralidad desagregado por sexos y por categoría', 'Datos de siniestralidad por sexos y categoría'
FROM area_plan ap
WHERE ap.nombre = 'Salud laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Disponer de un informe de siniestralidad desagregado por sexos y por categoría');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Se realizará y se difundirá el protocolo de prevención de riesgos en situación de embarazo y lactancia natural', 'Elaboración y difusión del protocolo. Número de difusiones'
FROM area_plan ap
WHERE ap.nombre = 'Salud laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Se realizará y se difundirá el protocolo de prevención de riesgos en situación de embarazo y lactancia natural');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Se realizará un seguimiento del cumplimiento de las normas de protección del embarazo y lactancia natural y se informará a la Comisión de seguimiento', 'Nº de veces que se aplica el protocolo y resultados'
FROM area_plan ap
WHERE ap.nombre = 'Salud laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Se realizará un seguimiento del cumplimiento de las normas de protección del embarazo y lactancia natural y se informará a la Comisión de seguimiento');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Considerar las variables relacionadas con el sexo, tanto en los sistemas de recogida de datos, como en el estudio e investigación generales en las evaluaciones en materia de prevención de riesgos laborales (incluidos los psicosociales), con el objetivo de detectar y prevenir posibles situaciones en las que los daños derivados del trabajo puedan aparecer vinculados con el sexo, como por ejemplo aquellos relacionados con la menopausia dada la edad de la plantilla.', 'Informe'
FROM area_plan ap
WHERE ap.nombre = 'Salud laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Considerar las variables relacionadas con el sexo, tanto en los sistemas de recogida de datos, como en el estudio e investigación generales en las evaluaciones en materia de prevención de riesgos laborales (incluidos los psicosociales), con el objetivo de detectar y prevenir posibles situaciones en las que los daños derivados del trabajo puedan aparecer vinculados con el sexo, como por ejemplo aquellos relacionados con la menopausia dada la edad de la plantilla.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Disponer de los resultados del informe de siniestralidad desagregado por sexos y por categoría', 'Datos de siniestralidad por sexos y categoría.'
FROM area_plan ap
WHERE ap.nombre = 'Salud laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Disponer de los resultados del informe de siniestralidad desagregado por sexos y por categoría');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar una vigilancia de la salud periódica en función de los riesgos inherentes al trabajo, garantizando la protección de las personas trabajadoras especialmente sensibles a los riesgos derivados del trabajo.', 'Verificar la comunicación a todas las mujeres de la plantilla. Modificaciones realizadas en los reconocimientos médicos para tener en cuenta la perspectiva de género.'
FROM area_plan ap
WHERE ap.nombre = 'Salud laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar una vigilancia de la salud periódica en función de los riesgos inherentes al trabajo, garantizando la protección de las personas trabajadoras especialmente sensibles a los riesgos derivados del trabajo.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Los reconocimientos médicos que realiza la empresa tendrán en cuenta la perspectiva de género', 'Reconocimientos efectuados'
FROM area_plan ap
WHERE ap.nombre = 'Salud laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Los reconocimientos médicos que realiza la empresa tendrán en cuenta la perspectiva de género');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Disponer de un informe de siniestralidad desagregado por sexos y por categoría, considerando las variables relacionadas con el sexo, tanto en los sistemas de recogida de datos, como en el estudio e investigación generales en las evaluaciones en materia de prevención de riesgos laborales (incluidos los psicosociales), con el objetivo de detectar y prevenir posibles situaciones en las que los daños derivados del trabajo puedan aparecer vinculados con el sexo, como por ejemplo aquellos relacionados con la menopausia dad la edad de la plantilla.', 'Datos de siniestralidad por sexos y categoría. Incorporación de la perspectiva de género.'
FROM area_plan ap
WHERE ap.nombre = 'Salud laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Disponer de un informe de siniestralidad desagregado por sexos y por categoría, considerando las variables relacionadas con el sexo, tanto en los sistemas de recogida de datos, como en el estudio e investigación generales en las evaluaciones en materia de prevención de riesgos laborales (incluidos los psicosociales), con el objetivo de detectar y prevenir posibles situaciones en las que los daños derivados del trabajo puedan aparecer vinculados con el sexo, como por ejemplo aquellos relacionados con la menopausia dad la edad de la plantilla.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Incorporar la perspectiva de género en la elaboración de campañas sobre seguridad y bienestar.', 'Incorporación de la perspectiva de género.'
FROM area_plan ap
WHERE ap.nombre = 'Salud laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Incorporar la perspectiva de género en la elaboración de campañas sobre seguridad y bienestar.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Solicitar a las empresas que garanticen que donde no exista un espacio y/o mobiliario adecuado en los centros para los preceptivos descansos de la plantilla y las embarazadas y para el periodo de lactancia natural cuando se requiera, se ha habilitado este.', 'Informe sobre espacios disponibles en los centros de trabajo. Número de espacios habilitados por centro.'
FROM area_plan ap
WHERE ap.nombre = 'Salud laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Solicitar a las empresas que garanticen que donde no exista un espacio y/o mobiliario adecuado en los centros para los preceptivos descansos de la plantilla y las embarazadas y para el periodo de lactancia natural cuando se requiera, se ha habilitado este.');

-- ========================================================
-- MEDIDAS - EJERCICIO CORRESPONSABLE VIDA PERSONAL FAMILIAR LABORAL
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Disponer de al menos 2 días laborables para la realización de control médico necesario del/la menor durante los 12 primeros meses de vida.', 'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Disponer de al menos 2 días laborables para la realización de control médico necesario del/la menor durante los 12 primeros meses de vida.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Siempre que haya posibilidad, cambio de turno y/o movilidad geográfica para personas que tengan a su cargo familiares dependientes.', 'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Siempre que haya posibilidad, cambio de turno y/o movilidad geográfica para personas que tengan a su cargo familiares dependientes.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Reservar el puesto de trabajo durante todo el tiempo de excedencia por cuidado de personas dependientes (menores o mayores, durante un periodo máximo de dos años).', 'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Reservar el puesto de trabajo durante todo el tiempo de excedencia por cuidado de personas dependientes (menores o mayores, durante un periodo máximo de dos años).');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Facilitar un proceso para la solicitud de adaptación horaria de la jornada, donde se incluya el compromiso de los responsables por facilitar dicha adaptación (siempre que las condiciones del centro de trabajo lo permitan) de forma que se evite en lo posible tener que acudir a reducir la jornada laboral, para atender a las cargas familiares (en caso de tener al cuidado a ascendientes dependientes y descendientes hasta el primer grado de consanguineidad o afinidad). Este procedimiento contemplará los plazos de solicitud y tramitación. Se prestará especial atención a las solicitudes de padres y madres con descendientes menores de 1 año, con el fin de evitar las reducciones de jornada.', 'Proceso/canal que se ha establecido para la solicitud. Número de solicitudes y veces que se aplica. Número de solicitudes denegadas y motivos de la denegación.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Facilitar un proceso para la solicitud de adaptación horaria de la jornada, donde se incluya el compromiso de los responsables por facilitar dicha adaptación (siempre que las condiciones del centro de trabajo lo permitan) de forma que se evite en lo posible tener que acudir a reducir la jornada laboral, para atender a las cargas familiares (en caso de tener al cuidado a ascendientes dependientes y descendientes hasta el primer grado de consanguineidad o afinidad). Este procedimiento contemplará los plazos de solicitud y tramitación. Se prestará especial atención a las solicitudes de padres y madres con descendientes menores de 1 año, con el fin de evitar las reducciones de jornada.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Conceder permisos retribuidos por el tiempo imprescindible para los trabajadores o trabajadoras en tratamiento de técnicas de reproducción asistida.', 'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Conceder permisos retribuidos por el tiempo imprescindible para los trabajadores o trabajadoras en tratamiento de técnicas de reproducción asistida.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Flexibilizar el permiso de hospitalización, pudiendo ejercerse de manera discontinua mientras dure la hospitalización o reposo domiciliario del hecho causante.', 'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Flexibilizar el permiso de hospitalización, pudiendo ejercerse de manera discontinua mientras dure la hospitalización o reposo domiciliario del hecho causante.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Establecer un permiso retribuido del tiempo necesario para las tutorías y matrículas relacionadas con la escolarización del centro de estudios obligatorios de las y los menores, cuando no puedan realizarse fuera de la jornada de trabajo, y con un límite de 10 horas anuales.', 'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Establecer un permiso retribuido del tiempo necesario para las tutorías y matrículas relacionadas con la escolarización del centro de estudios obligatorios de las y los menores, cuando no puedan realizarse fuera de la jornada de trabajo, y con un límite de 10 horas anuales.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Conceder permisos retribuidos para las personas trabajadoras, para acompañar a su pareja sentimental, cónyuge o pareja de hecho a las clases de preparación al parto y exámenes prenatales, durante el tiempo imprescindible siempre y cuando no puedan realizarse fuera de la jornada de trabajo y mediante la entrega de los correspondientes justificantes que sean solicitados por la empresa.', 'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Conceder permisos retribuidos para las personas trabajadoras, para acompañar a su pareja sentimental, cónyuge o pareja de hecho a las clases de preparación al parto y exámenes prenatales, durante el tiempo imprescindible siempre y cuando no puedan realizarse fuera de la jornada de trabajo y mediante la entrega de los correspondientes justificantes que sean solicitados por la empresa.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Considerar como retribuidos los permisos para acompañar a consultas médicas a menores, o mayores de 65 años y personas dependientes, con criterios debidamente justificados y sólo por el tiempo indispensable con un máximo de 15 horas anuales. Superadas estas horas será permisos no retribuido o recuperable.', 'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Considerar como retribuidos los permisos para acompañar a consultas médicas a menores, o mayores de 65 años y personas dependientes, con criterios debidamente justificados y sólo por el tiempo indispensable con un máximo de 15 horas anuales. Superadas estas horas será permisos no retribuido o recuperable.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Difundir las medidas de conciliación y corresponsabilidad al conjunto de la plantilla a través de medios digitales.', 'Número de medidas propuestas, comunicadas y puestas en marcha. Evolución en el uso de las medidas de conciliación y corresponsabilidad por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Difundir las medidas de conciliación y corresponsabilidad al conjunto de la plantilla a través de medios digitales.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'A través de encuestas de clima laboral, en caso de realizarlas, se hará un seguimiento para conocer las necesidades de conciliación del personal.', 'Número de medidas propuestas, comunicadas y puestas en marcha. Evolución en el uso de las medidas de conciliación y corresponsabilidad por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'A través de encuestas de clima laboral, en caso de realizarlas, se hará un seguimiento para conocer las necesidades de conciliación del personal.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Las personas trabajadoras que por sentencia judicial de divorcio o convenio regulador tengan establecidos unos determinados periodos de tenencia de los hijos menores que coincidan con periodo laboral, así como las personas con dependientes mayores a su cargo tendrán preferencia para adaptar sus vacaciones en los periodos que estas se disfruten.', 'Número de medidas propuestas, comunicadas y puestas en marcha. Evolución en el uso de las medidas de conciliación y corresponsabilidad por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Las personas trabajadoras que por sentencia judicial de divorcio o convenio regulador tengan establecidos unos determinados periodos de tenencia de los hijos menores que coincidan con periodo laboral, así como las personas con dependientes mayores a su cargo tendrán preferencia para adaptar sus vacaciones en los periodos que estas se disfruten.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Día por objetivos: Se establece una mejora de 1 día extra cada cuatrimestre del año a disfrutar en los siguientes 4 meses, siempre que se cumplan una serie de objetivos cuantitativos.', 'Número de medidas propuestas, comunicadas y puestas en marcha. Evolución en el uso de las medidas de conciliación y corresponsabilidad por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Día por objetivos: Se establece una mejora de 1 día extra cada cuatrimestre del año a disfrutar en los siguientes 4 meses, siempre que se cumplan una serie de objetivos cuantitativos.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Plus de viajes: La FLM compensa, económicamente y en días libres, a quienes tienen que realizar desplazamientos y viajes en el desarrollo de su trabajo. Además, se dispondrá de 1 día de permiso, a disfrutar el día siguiente del retorno, para viajes realizados en días laborables, independientemente de la duración del mismo (con un mínimo de dos días). En caso de que el viaje se realice en sábado o domingo, se dispondrá de 1 ó 2 días libres correspondientes a los días de trabajo en sábado o domingo.', 'Número de medidas propuestas, comunicadas y puestas en marcha. Evolución en el uso de las medidas de conciliación y corresponsabilidad por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Plus de viajes: La FLM compensa, económicamente y en días libres, a quienes tienen que realizar desplazamientos y viajes en el desarrollo de su trabajo. Además, se dispondrá de 1 día de permiso, a disfrutar el día siguiente del retorno, para viajes realizados en días laborables, independientemente de la duración del mismo (con un mínimo de dos días). En caso de que el viaje se realice en sábado o domingo, se dispondrá de 1 ó 2 días libres correspondientes a los días de trabajo en sábado o domingo.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Cambio de turno o movilidad geográfica para padres o madres cuya guarda o custodia legal recaiga exclusivamente en un progenitor, de acuerdo a lo establecido en el régimen de visitas, siempre y cuando sea posible por la organización/estructura del servicio.', 'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Cambio de turno o movilidad geográfica para padres o madres cuya guarda o custodia legal recaiga exclusivamente en un progenitor, de acuerdo a lo establecido en el régimen de visitas, siempre y cuando sea posible por la organización/estructura del servicio.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'La acumulación de lactancia se podrá hacer en jornadas completas de 3 días más de lo que estipula el permiso.', 'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'La acumulación de lactancia se podrá hacer en jornadas completas de 3 días más de lo que estipula el permiso.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Elaborar un documento que recoja todas las medidas de conciliación y los requisitos para solicitarlas. También se establecerán los criterios para su aprobación', 'Revisar el documento. Nº de difusiones, solicitudes y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Elaborar un documento que recoja todas las medidas de conciliación y los requisitos para solicitarlas. También se establecerán los criterios para su aprobación');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar campañas informativas y de sensibilización (jornadas, folletos...) específicamente dirigidas a los trabajadores hombres sobre las medidas de conciliación existentes, haciendo hincapié en el permiso de lactancia', 'Publicación de la guía y número de personas a las que se entrega'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar campañas informativas y de sensibilización (jornadas, folletos...) específicamente dirigidas a los trabajadores hombres sobre las medidas de conciliación existentes, haciendo hincapié en el permiso de lactancia');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Posibilitar la unión del permiso de nacimiento para hombres y mujeres a las vacaciones tanto del año en curso, como del año anterior, en caso de que haya finalizado el año natural.', 'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Posibilitar la unión del permiso de nacimiento para hombres y mujeres a las vacaciones tanto del año en curso, como del año anterior, en caso de que haya finalizado el año natural.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Extender los derechos de conciliación a las parejas de hecho.', 'Aplicación de la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Extender los derechos de conciliación a las parejas de hecho.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'La suspensión del contrato de trabajo, transcurridas las primeras 6 semanas inmediatamente posteriores al parto, podrá disfrutarse en régimen de jornada completa o de jornada parcial a decisión de la persona trabajadora.', 'Número de solicitudes rechazadas y motivación.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'La suspensión del contrato de trabajo, transcurridas las primeras 6 semanas inmediatamente posteriores al parto, podrá disfrutarse en régimen de jornada completa o de jornada parcial a decisión de la persona trabajadora.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Ofrecer jornadas continuas a padres/madres de niños de menos de 1 año con el fin de evitar reducciones de jornada.', 'Número de solicitudes rechazadas y motivación.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Ofrecer jornadas continuas a padres/madres de niños de menos de 1 año con el fin de evitar reducciones de jornada.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Aplicar una flexibilidad de entrada/salida al menos 1 hora a toda la plantilla (en función de las características de cada centro de trabajo), pudiendo ampliarse hasta un máximo de 1,5 horas para padres/madres de hijos/hijas hasta 12 años o con dependientes con discapacidad de más del 33%. Siempre y cuando se cuente con la aceptación de la empresa cliente.', 'Número de solicitudes y veces que se aplica. Número de rechazadas y motivación de estas.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Aplicar una flexibilidad de entrada/salida al menos 1 hora a toda la plantilla (en función de las características de cada centro de trabajo), pudiendo ampliarse hasta un máximo de 1,5 horas para padres/madres de hijos/hijas hasta 12 años o con dependientes con discapacidad de más del 33%. Siempre y cuando se cuente con la aceptación de la empresa cliente.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Posibilidad de solicitar reducción de jornada y/o adaptación de la misma temporalmente por estudios. Una vez transcurrido el plazo solicitado, la persona volverá a su jornada habitual.', 'Número de solicitudes denegadas y motivos de la denegación.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Posibilidad de solicitar reducción de jornada y/o adaptación de la misma temporalmente por estudios. Una vez transcurrido el plazo solicitado, la persona volverá a su jornada habitual.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Facilitar la ausencia de la persona trabajadora en casos de emergencia familiar.', 'Aplicación de la medida.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Facilitar la ausencia de la persona trabajadora en casos de emergencia familiar.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'En los casos en los que ambos progenitores trabajen en la empresa, equilibrar los turnos de trabajo dando facilidad para que uno de ellos pueda elegir el turno.', 'Número de solicitudes rechazadas y motivación.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'En los casos en los que ambos progenitores trabajen en la empresa, equilibrar los turnos de trabajo dando facilidad para que uno de ellos pueda elegir el turno.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'En los casos de reducción de jornadas del menos del 15%, esta reducción no afectará a las retribuciones variables vinculadas actualmente al programa de objetivos comerciales que se calcularán por el objetivo variable asignado al 100%. Se aplicará a todos los colectivos a los que se amplíe este programa.', 'Número de solicitudes rechazadas y motivación.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'En los casos de reducción de jornadas del menos del 15%, esta reducción no afectará a las retribuciones variables vinculadas actualmente al programa de objetivos comerciales que se calcularán por el objetivo variable asignado al 100%. Se aplicará a todos los colectivos a los que se amplíe este programa.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Los trabajadores y trabajadoras que por sentencia judicial de divorcio o convenio regulador tengan establecidos unos determinados periodos de tenencia de los hijos que coincidan con periodo laboral, tendrán preferencia para adaptar sus vacaciones a dichos periodos fijados en la sentencia o convenio.', 'Número de veces que se solicita y número de veces que se aplica la medida.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Los trabajadores y trabajadoras que por sentencia judicial de divorcio o convenio regulador tengan establecidos unos determinados periodos de tenencia de los hijos que coincidan con periodo laboral, tendrán preferencia para adaptar sus vacaciones a dichos periodos fijados en la sentencia o convenio.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Garantizar que las personas que se acojan a cualquiera de los derechos relacionados con la conciliación de la vida familiar y laboral no vean frenado el desarrollo de su carrera profesional ni sus posibilidades de promoción ni retribución.', 'Número de personas promocionadas y que se acogen a medidas de conciliación.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Garantizar que las personas que se acojan a cualquiera de los derechos relacionados con la conciliación de la vida familiar y laboral no vean frenado el desarrollo de su carrera profesional ni sus posibilidades de promoción ni retribución.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Desarrollo de programa de teletrabajo flexible, en el que se valorarán aquellos puestos que pueden o no ser tele trabajables, pudiéndose aplicar el teletrabajo para días u horas en los términos que se establezcan en el programa', 'Número de personas que lo solicita, número de aceptados y número de rechazadas.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Desarrollo de programa de teletrabajo flexible, en el que se valorarán aquellos puestos que pueden o no ser tele trabajables, pudiéndose aplicar el teletrabajo para días u horas en los términos que se establezcan en el programa');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Posibilitar la realización de una distribución irregular o no diaria de la jornada reducida, con especial atención para familias monoparentales o divorciados/as en régimen de custodia compartida, pudiendo incluso acumularla en días completos, contando con el mutuo acuerdo con el responsable del departamento.', 'Número de solicitudes rechazadas y motivación.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Posibilitar la realización de una distribución irregular o no diaria de la jornada reducida, con especial atención para familias monoparentales o divorciados/as en régimen de custodia compartida, pudiendo incluso acumularla en días completos, contando con el mutuo acuerdo con el responsable del departamento.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Testar anualmente las medidas de conciliación implantadas con el fin de valorar su satisfacción y valorar nuevas medidas aún no incluidas.', 'Realización de encuestas a toda la plantilla.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Testar anualmente las medidas de conciliación implantadas con el fin de valorar su satisfacción y valorar nuevas medidas aún no incluidas.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Se garantiza que los incentivos y gratificaciones no se verán perjudicados por el ejercicio de los derechos de conciliación a que se acojan las personas trabajadoras', 'Informe comparativo sobre los incentivos y gratificaciones del año anterior al disfrute del derecho y del año en el que se disfruta.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Se garantiza que los incentivos y gratificaciones no se verán perjudicados por el ejercicio de los derechos de conciliación a que se acojan las personas trabajadoras');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'La acumulación de lactancia se podrá hacer en jornadas completas conforme al máximo legal.', 'Número de veces que se solicita y número de veces que se aplica la medida.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'La acumulación de lactancia se podrá hacer en jornadas completas conforme al máximo legal.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Unificar lactancia y vacaciones tras el permiso por nacimiento.', 'Número de veces que se solicita y número de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Unificar lactancia y vacaciones tras el permiso por nacimiento.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Posibilitar la unión del permiso de nacimiento para hombres y mujeres a las vacaciones tanto del año en curso, como del año anterior, en caso de que haya finalizado el año natural.', 'Número de veces que se solicita y número de veces que se aplica la medida.'
FROM area_plan ap
WHERE ap.nombre = 'Ejercicio corresponsable de los derechos de la vida personal, familiar y laboral'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Posibilitar la unión del permiso de nacimiento para hombres y mujeres a las vacaciones tanto del año en curso, como del año anterior, en caso de que haya finalizado el año natural.');

-- ========================================================
-- MEDIDAS - INFRARREPRESENTACIÓN FEMENINA
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realización de un análisis de las políticas de personal y de las prácticas de promoción vigentes en la empresa, con el fin de detectar barreras que dificulten la plena igualdad entre mujeres y hombres', 'Informe'
FROM area_plan ap
WHERE ap.nombre = 'Infrarrepresentación femenina'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realización de un análisis de las políticas de personal y de las prácticas de promoción vigentes en la empresa, con el fin de detectar barreras que dificulten la plena igualdad entre mujeres y hombres');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Revisión periódica del equilibrio por sexo de la plantilla y la ocupación de mujeres y hombres en los distintos puestos', 'Distribución de la plantilla por puestos desagregada por sexo'
FROM area_plan ap
WHERE ap.nombre = 'Infrarrepresentación femenina'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Revisión periódica del equilibrio por sexo de la plantilla y la ocupación de mujeres y hombres en los distintos puestos');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Buscar candidaturas de mujeres en áreas o dptos. masculinizados, acudiendo a centros, entidades u organismos formativos que nos faciliten ampliar las fuentes de reclutamiento para fomentar la contratación de mujeres en puestos y departamentos donde estén infrarrepresentadas (CV de mujeres para que los tengamos en cuenta para futuras vacantes).', 'Distribución de la plantilla por puestos desagregada por sexo'
FROM area_plan ap
WHERE ap.nombre = 'Infrarrepresentación femenina'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Buscar candidaturas de mujeres en áreas o dptos. masculinizados, acudiendo a centros, entidades u organismos formativos que nos faciliten ampliar las fuentes de reclutamiento para fomentar la contratación de mujeres en puestos y departamentos donde estén infrarrepresentadas (CV de mujeres para que los tengamos en cuenta para futuras vacantes).');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Valorar con carácter anual las promociones desagregadas por sexo, en caso de haber promoción ese año.', 'Número de medidas propuestas y puestas en marcha. Número de seguimientos de las promociones que incluya datos, desagregados por sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Infrarrepresentación femenina'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Valorar con carácter anual las promociones desagregadas por sexo, en caso de haber promoción ese año.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Incrementar durante la vigencia del plan la incorporación de mujeres en puestos y áreas en los que está infrarrepresentada.', 'Comparativa anual.'
FROM area_plan ap
WHERE ap.nombre = 'Infrarrepresentación femenina'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Incrementar durante la vigencia del plan la incorporación de mujeres en puestos y áreas en los que está infrarrepresentada.');

-- ========================================================
-- MEDIDAS - RETRIBUCIONES Y AUDITORÍA SALARIAL
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar un registro salarial anualmente y una auditoría salarial al menos una vez durante la vigencia del plan. Englobarán toda la plantilla y analizarán las retribuciones medias y medianas de las mujeres y de los hombres, por y puestos, incluyendo los salarios, los complementos salariales y las percepciones extrasalariales. Esta información deberá estar desagregada en atención a la naturaleza de la retribución, incluyendo salario base, cada uno de los complementos y cada una de las percepciones extrasalariales, especificando de modo diferenciado cada percepción desglose de la totalidad de los conceptos salariales y extra salariales, así como los criterios para su percepción.', 'Documentación'
FROM area_plan ap
WHERE ap.nombre = 'Retribuciones y auditoría salarial'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar un registro salarial anualmente y una auditoría salarial al menos una vez durante la vigencia del plan. Englobarán toda la plantilla y analizarán las retribuciones medias y medianas de las mujeres y de los hombres, por y puestos, incluyendo los salarios, los complementos salariales y las percepciones extrasalariales. Esta información deberá estar desagregada en atención a la naturaleza de la retribución, incluyendo salario base, cada uno de los complementos y cada una de las percepciones extrasalariales, especificando de modo diferenciado cada percepción desglose de la totalidad de los conceptos salariales y extra salariales, así como los criterios para su percepción.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'En caso de detectarse desigualdades, se realizará un plan que contenga medidas correctoras, asignando el mismo nivel retributivo a funciones de igual valor. Se considerará brecha las diferencias salariales que superen el 10%', 'Medida acordada'
FROM area_plan ap
WHERE ap.nombre = 'Retribuciones y auditoría salarial'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'En caso de detectarse desigualdades, se realizará un plan que contenga medidas correctoras, asignando el mismo nivel retributivo a funciones de igual valor. Se considerará brecha las diferencias salariales que superen el 10%');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Revisar, con perspectiva de género, los criterios de los complementos y bonus, atendiendo a su proporcionalidad y teniendo en cuenta que no suponga discriminaciones para casos como las reducciones de jornada', 'Datos'
FROM area_plan ap
WHERE ap.nombre = 'Retribuciones y auditoría salarial'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Revisar, con perspectiva de género, los criterios de los complementos y bonus, atendiendo a su proporcionalidad y teniendo en cuenta que no suponga discriminaciones para casos como las reducciones de jornada');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Actualizar el estudio de brecha salarial, con la revisión del registro retributivo', 'Estudio y revisión de la brecha salarial'
FROM area_plan ap
WHERE ap.nombre = 'Retribuciones y auditoría salarial'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Actualizar el estudio de brecha salarial, con la revisión del registro retributivo');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Garantizar la objetividad de todos los conceptos que se definen en la estructura salarial de la empresa', 'Auditoría salarial'
FROM area_plan ap
WHERE ap.nombre = 'Retribuciones y auditoría salarial'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Garantizar la objetividad de todos los conceptos que se definen en la estructura salarial de la empresa');

-- ========================================================
-- MEDIDAS - PREVENCIÓN ACOSO SEXUAL Y POR RAZÓN SEXO
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Revisión del procedimiento de actuación y prevención del acoso sexual y/o por razón de sexo, orientación sexual e identidad de género, y testeo de su funcionamiento, así como establecer las correcciones necesarias si se detectasen deficiencias en su funcionamiento', 'Revisión del protocolo'
FROM area_plan ap
WHERE ap.nombre = 'Prevención del acoso sexual y por razón de sexo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Revisión del procedimiento de actuación y prevención del acoso sexual y/o por razón de sexo, orientación sexual e identidad de género, y testeo de su funcionamiento, así como establecer las correcciones necesarias si se detectasen deficiencias en su funcionamiento');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Formación específica a todas las personas que integran la Comisión de Investigación para asumir las funciones asociadas a la misma.', 'Número de cursos/horas y número de participantes. Contenido de los cursos'
FROM area_plan ap
WHERE ap.nombre = 'Prevención del acoso sexual y por razón de sexo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Formación específica a todas las personas que integran la Comisión de Investigación para asumir las funciones asociadas a la misma.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Formación en prevención del acoso sexual y por razón de sexo de toda la plantilla', 'Número de cursos/horas y número de participantes. Contenido de los cursos'
FROM area_plan ap
WHERE ap.nombre = 'Prevención del acoso sexual y por razón de sexo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Formación en prevención del acoso sexual y por razón de sexo de toda la plantilla');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar acciones específicas de sensibilización para toda la plantilla en aquellos centros en los que haya habido casos.', 'Acciones y contenido de las mismas.'
FROM area_plan ap
WHERE ap.nombre = 'Prevención del acoso sexual y por razón de sexo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar acciones específicas de sensibilización para toda la plantilla en aquellos centros en los que haya habido casos.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Presentación a la Comisión de Seguimiento de un informe anual sobre los procesos iniciados por acoso sexual o por razón de sexo, orientación sexual e identidad de género así como el número de denuncias archivadas por centro de trabajo, con las conclusiones de los procesos.', 'Elaboración del informe. Nº de procesos y conclusiones.'
FROM area_plan ap
WHERE ap.nombre = 'Prevención del acoso sexual y por razón de sexo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Presentación a la Comisión de Seguimiento de un informe anual sobre los procesos iniciados por acoso sexual o por razón de sexo, orientación sexual e identidad de género así como el número de denuncias archivadas por centro de trabajo, con las conclusiones de los procesos.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar actuaciones de información sobre el contenido y procedimiento establecido en el Protocolo.', 'Número y tipo de actuaciones de información del protocolo.'
FROM area_plan ap
WHERE ap.nombre = 'Prevención del acoso sexual y por razón de sexo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar actuaciones de información sobre el contenido y procedimiento establecido en el Protocolo.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Se pone a disposición de las personas empleadas al departamento jurídico en caso de acoso y/o agresión.', 'Tratamiento y resolución de las posibles denuncias recibidas por acoso sexual y por razón de sexo.'
FROM area_plan ap
WHERE ap.nombre = 'Prevención del acoso sexual y por razón de sexo'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Se pone a disposición de las personas empleadas al departamento jurídico en caso de acoso y/o agresión.');

-- ========================================================
-- MEDIDAS - VIOLENCIA DE GÉNERO
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Elaborar una guía de violencia de género y difundirla a toda la plantilla a través de los medios de comunicación interna de los derechos reconocidos a las mujeres víctimas de violencia de género y de las mejoras que pudieran existir por aplicación de los convenios colectivos y/o incluidas en el Plan de Igualdad.', 'Muestra de comunicaciones'
FROM area_plan ap
WHERE ap.nombre = 'Violencia de género'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Elaborar una guía de violencia de género y difundirla a toda la plantilla a través de los medios de comunicación interna de los derechos reconocidos a las mujeres víctimas de violencia de género y de las mejoras que pudieran existir por aplicación de los convenios colectivos y/o incluidas en el Plan de Igualdad.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'La acreditación de la situación de víctima de violencia de género y víctima de agresión sexual se podrá dar por diferentes medios: sentencia judicial, denuncia, orden de protección, atestado de las fuerzas y cuerpos de segundad del Estado, informe médico o psicológico elaborado por un profesional colegiado, informe de los servicios públicos (servicios sociales, sanitarios, centros de salud mental, equipos de atención integral a la víctima...) o el informe de los servicios de acogida entre otros, tal y como se recoge en el RDL 9/2018', 'Nº de veces que se solicita y nº de veces que se aplica la medida'
FROM area_plan ap
WHERE ap.nombre = 'Violencia de género'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'La acreditación de la situación de víctima de violencia de género y víctima de agresión sexual se podrá dar por diferentes medios: sentencia judicial, denuncia, orden de protección, atestado de las fuerzas y cuerpos de segundad del Estado, informe médico o psicológico elaborado por un profesional colegiado, informe de los servicios públicos (servicios sociales, sanitarios, centros de salud mental, equipos de atención integral a la víctima...) o el informe de los servicios de acogida entre otros, tal y como se recoge en el RDL 9/2018');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Campaña de sensibilización sobre la importancia de prevenir la violencia de género en la empresa, haciéndolo coincidir con el 25 de noviembre, Día Internacional para la eliminación de la Violencia contra la mujer.', 'Muestreo'
FROM area_plan ap
WHERE ap.nombre = 'Violencia de género'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Campaña de sensibilización sobre la importancia de prevenir la violencia de género en la empresa, haciéndolo coincidir con el 25 de noviembre, Día Internacional para la eliminación de la Violencia contra la mujer.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Para hacer efectiva la protección de la víctima de violencia de género o su derecho a la asistencia social integral, generar el derecho a la reducción de jornada con la disminución proporcional de la retribución.', 'Número de veces que se solicita y número de veces que se aplica el derecho a reducción de jornada o reordenación del tiempo de trabajo.'
FROM area_plan ap
WHERE ap.nombre = 'Violencia de género'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Para hacer efectiva la protección de la víctima de violencia de género o su derecho a la asistencia social integral, generar el derecho a la reducción de jornada con la disminución proporcional de la retribución.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar formación en materia de prevención, detección y atención a la violencia de género para las personas que sean referentes en la empresa y que estén designadas en el protocolo de actuación ante mujeres víctimas de violencia de género.', 'Formaciones realizadas'
FROM area_plan ap
WHERE ap.nombre = 'Violencia de género'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar formación en materia de prevención, detección y atención a la violencia de género para las personas que sean referentes en la empresa y que estén designadas en el protocolo de actuación ante mujeres víctimas de violencia de género.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Fomentar la colaboración con agentes de empleo (Asociaciones, Fundaciones, ONGs, etc.), en aquellos procesos de selección de búsqueda externa para facilitar la contratación de mujeres víctimas de violencia de género.', 'Número de veces que se recurre a agentes de empleo para facilitar la contratación de mujeres víctimas de violencia de género.'
FROM area_plan ap
WHERE ap.nombre = 'Violencia de género'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Fomentar la colaboración con agentes de empleo (Asociaciones, Fundaciones, ONGs, etc.), en aquellos procesos de selección de búsqueda externa para facilitar la contratación de mujeres víctimas de violencia de género.');

-- ========================================================
-- MEDIDAS - COMUNICACIÓN Y SENSIBILIZACIÓN
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Informar a las empresas colaboradoras y proveedoras de la compañía de su compromiso con la igualdad de oportunidades.', 'Número de veces'
FROM area_plan ap
WHERE ap.nombre = 'Comunicación y de sensibilización'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Informar a las empresas colaboradoras y proveedoras de la compañía de su compromiso con la igualdad de oportunidades.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Incluir en la acogida de nuevas incorporaciones información específica sobre el Plan de Igualdad, protocolo de prevención del acoso sexual y por razón de sexo', 'Documento'
FROM area_plan ap
WHERE ap.nombre = 'Comunicación y de sensibilización'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Incluir en la acogida de nuevas incorporaciones información específica sobre el Plan de Igualdad, protocolo de prevención del acoso sexual y por razón de sexo');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Difundir la existencia, dentro de la empresa de una persona responsable de igualdad y de sus funciones, facilitando una dirección de correo electrónico y un teléfono a disposición del personal de la empresa para aquellas dudas, sugerencias o quejas relacionadas con el plan de igualdad', 'Nº de personas informadas'
FROM area_plan ap
WHERE ap.nombre = 'Comunicación y de sensibilización'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Difundir la existencia, dentro de la empresa de una persona responsable de igualdad y de sus funciones, facilitando una dirección de correo electrónico y un teléfono a disposición del personal de la empresa para aquellas dudas, sugerencias o quejas relacionadas con el plan de igualdad');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Utilizar en las campañas publicitarias los logotipos y reconocimientos que acrediten que la empresa cuenta con un plan de igualdad.', 'Documento'
FROM area_plan ap
WHERE ap.nombre = 'Comunicación y de sensibilización'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Utilizar en las campañas publicitarias los logotipos y reconocimientos que acrediten que la empresa cuenta con un plan de igualdad.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Sensibilizar en la campaña especial del Día Internacional contra la Violencia de Género (25N).', 'Campaña y contenido'
FROM area_plan ap
WHERE ap.nombre = 'Comunicación y de sensibilización'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Sensibilizar en la campaña especial del Día Internacional contra la Violencia de Género (25N).');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Colaborar con el Instituto de las Mujeres u organismo competente en su momento, en las distintas campañas.', 'Campaña y contenido'
FROM area_plan ap
WHERE ap.nombre = 'Comunicación y de sensibilización'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Colaborar con el Instituto de las Mujeres u organismo competente en su momento, en las distintas campañas.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Difundir mediante un folleto informativo y/o a través de los canales habituales de comunicación de la empresa los derechos y medidas de conciliación recogidos en la norma y comunicar los disponibles en la empresa que mejoran la legislación', 'Contenido'
FROM area_plan ap
WHERE ap.nombre = 'Comunicación y de sensibilización'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Difundir mediante un folleto informativo y/o a través de los canales habituales de comunicación de la empresa los derechos y medidas de conciliación recogidos en la norma y comunicar los disponibles en la empresa que mejoran la legislación');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Realizar una campaña el día nacional de la conciliación y corresponsabilidad familiar (23 de marzo), promoviendo el compromiso de la empresa con esta materia y la igualdad, para promover esta cultura entre todo el personal.', 'Contenido'
FROM area_plan ap
WHERE ap.nombre = 'Comunicación y de sensibilización'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Realizar una campaña el día nacional de la conciliación y corresponsabilidad familiar (23 de marzo), promoviendo el compromiso de la empresa con esta materia y la igualdad, para promover esta cultura entre todo el personal.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Visibilizar el uso de los permisos y medidas de conciliación y corresponsabilidad', 'Campaña y contenido'
FROM area_plan ap
WHERE ap.nombre = 'Comunicación y de sensibilización'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Visibilizar el uso de los permisos y medidas de conciliación y corresponsabilidad');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Formar y sensibilizar al personal encargado de los medios de comunicación de la empresa (página web, relaciones con prensa, etc.) en materia de igualdad y utilización del lenguaje e imágenes no sexistas.', 'Formaciones realizadas.'
FROM area_plan ap
WHERE ap.nombre = 'Comunicación y de sensibilización'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Formar y sensibilizar al personal encargado de los medios de comunicación de la empresa (página web, relaciones con prensa, etc.) en materia de igualdad y utilización del lenguaje e imágenes no sexistas.');

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Introducir en la página web un espacio específico para informar sobre la política de igualdad de oportunidades entre mujeres y hombres en la empresa.', 'Creación de la sección y contenidos.'
FROM area_plan ap
WHERE ap.nombre = 'Comunicación y de sensibilización'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Introducir en la página web un espacio específico para informar sobre la política de igualdad de oportunidades entre mujeres y hombres en la empresa.');

-- ========================================================
-- MEDIDAS - COLECTIVO LGTBI
-- ========================================================

INSERT INTO medida (id_plan, descripcion, indicador)
SELECT ap.id_plan, 'Implementación de las medidas y protocolo según lo dispuesto en la normativa vigente', 'Verificación de las medidas implementadas'
FROM area_plan ap
WHERE ap.nombre = 'Colectivo LGTBI'
AND NOT EXISTS (SELECT 1 FROM medida m WHERE m.id_plan = ap.id_plan AND m.descripcion = 'Implementación de las medidas y protocolo según lo dispuesto en la normativa vigente');