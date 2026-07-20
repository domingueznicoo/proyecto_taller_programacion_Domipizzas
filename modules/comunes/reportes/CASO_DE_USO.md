Informes
Elaborar Informes varios

Descripción Básica
Permitir a usuarios autorizados consultar y exportar informes operativos de Compras y Producción, filtrando por movimiento (proceso), rango de fechas y estado. Los informes son solo lectura: derivan datos de documentos ya registrados y no crean ni modifican transacciones. La salida es PDF en una nueva pestaña del navegador.

Actores Relacionados
•	Jefe de Compras (permiso REPORTES_COMPRAS — informes del módulo Compras).
•	Jefe de Producción (permiso REPORTES_PRODUCCION — informes del módulo Producción).
•	Administrador (permiso REPORTES_GENERALES — informes de Compras y Producción).
•	Sistema (secundario).

Pre Condición
•	Usuario autenticado con permiso REPORTES_COMPRAS, REPORTES_PRODUCCION o REPORTES_GENERALES.
•	Conexión estable a la base de datos.
•	Existencia de registros en el proceso y rango de fechas seleccionados (opcional para obtener resultados).

Flujo de Eventos
Flujo Básico
•	El usuario accede al menú Reportes Generales.
•	El sistema valida sesión y permisos; muestra el formulario de filtros (Movimiento, Estado, Desde, Hasta).
•	El usuario selecciona Movimiento según su perfil:
•	Compras (REPORTES_COMPRAS o REPORTES_GENERALES): pedido, presupuesto, orden, factura, notas, ajustes, libro de compras.
•	Producción (REPORTES_PRODUCCION o REPORTES_GENERALES): pedido de producción, orden de producción, pedido de materia prima, reposición, control de producción, productos terminados, control de calidad, pérdidas.
•	El sistema carga los estados disponibles para el movimiento elegido (excepto libro de compras y productos terminados).
•	El usuario selecciona Estado (incluye REPORTE TOTAL para todos los estados, cuando aplica).
•	El usuario define rango de fechas Desde y Hasta (obligatorio).
•	El usuario pulsa Generar informe.
•	El sistema valida parámetros y enruta vía router_informes.php:
•	Compras → generadores existentes por módulo (generar_informe.php / libro_compras.php).
•	Producción → generador_produccion.php.
•	El sistema ejecuta la consulta de solo lectura y abre el PDF. Si no hay filas, redirige con aviso de sin resultados.

Operaciones complementarias
•	Imprimir: desde el visor PDF del navegador.
•	Ordenar / paginar en pantalla, exportar Excel o CSV, filtros por sucursal/cliente/proveedor/producto: no implementados en esta versión.

Flujo Alternativo
•	Sin resultados en el rango → aviso «No se encontraron registros»; no se emite PDF vacío.
•	Fecha Desde posterior a Hasta → rechazo en cliente y servidor.
•	Movimiento o estado no seleccionado cuando es obligatorio → rechazo con alerta.
•	Usuario sin permiso para el movimiento → bloqueo al enrutar o al abrir el generador.

Postcondiciones
•	Ningún documento transaccional es creado, modificado ni anulado.
•	El usuario obtiene un informe PDF consultivo o un aviso de ausencia de datos.

Descripción de las Tablas
Compras
•	pedido_compra, pedido_detalle_compra
•	presupuesto_compra, presupuesto_detalle_compra
•	orden_compra, orden_detalle_compra
•	factura_compra, factura_detalle_compra, iva_compra
•	nota_compra, nota_detalle_compra
•	nota_remision_compra, nota_remision_detalle_compra
•	ajustes, ajustes_detalle
•	cuentas_pagar

Producción
•	pedido_produccion, pedido_detalle_produccion
•	orden_produccion, orden_detalle_produccion
•	pedido_materia_produccion, pedido_materia_detalle_produccion
•	reposicion_materia, reposicion_materia_detalle
•	control_produccion, control_produccion_detalle
•	producto_terminado, productos_terminados_detalle
•	control_calidad_produccion, control_calidad_detalle
•	perdidas, perdidas_detalle, tipo_perdida

Referenciales comunes
•	proveedores, productos, materia_prima, sucursales, usuarios, deposito
