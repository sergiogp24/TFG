# PHPUnit Tests

Este directorio contiene los tests automatizados para el proyecto Igualdad.

## Estructura

```
tests/
├── bootstrap.php           # Configuración inicial de tests
├── Unit/                   # Tests unitarios
│   ├── AuthTest.php       # Tests de autenticación
│   ├── FileValidationTest.php
│   └── EqualityCalculationTest.php
└── Feature/               # Tests de características (por implementar)
```

## Ejecutar Tests

### Todos los tests:
```bash
./vendor/bin/phpunit
```

### Tests de un archivo específico:
```bash
./vendor/bin/phpunit tests/Unit/AuthTest.php
```

### Con cobertura de código:
```bash
./vendor/bin/phpunit --coverage-html coverage/
```

## Notas

- Los tests necesitan que `composer install` haya sido ejecutado
- La base de datos debe estar disponible para algunos tests de integración
- Ver `phpunit.xml` para configuración completa

## Por Implementar

- [ ] Tests de integración con base de datos
- [ ] Tests de login/logout flow
- [ ] Tests de subida de archivos
- [ ] Tests de generación de Word
- [ ] Tests de API de chat
