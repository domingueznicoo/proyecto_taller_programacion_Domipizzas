Gestionar Producción
Registrar Pérdidas

Descripción Básica
Permitir al Encargado de Producción registrar pérdidas del proceso productivo en dos modalidades:
•	Pérdida en proceso: unidades no recuperables detectadas durante la ejecución de la OP (no llegan a producto terminado). Origen: Control de Producción.
•	Merma por calidad: unidades rechazadas en Control de Calidad que se dan de baja definitiva. Origen: inspección NO CONFORME.
El registro impacta pendientes de la OP (pérdida en última etapa) y/o stock de productos (merma por calidad).

Actores Relacionados
•	Encargado de Producción (primario).
•	Sistema (secundario).

Pre Condición
•	Usuario con permisos del módulo Producción y sucursal activa.
•	Tipos de pérdida definidos y activos en catálogo.
•	Para pérdida en proceso: Control de Producción en estado REGISTRADO, OP sin productos terminados, con unidades pendientes de marcar como pérdida.
•	Para merma por calidad: Control de Calidad NO CONFORME con productos rechazados disponibles y stock en depósito del lote PT.
•	Productos registrados y activos.
•	Conexión a la base de datos.

Flujo de Eventos
Flujo Básico
•	El Encargado accede a Producción → Pérdidas.
•	El sistema muestra la lista de pérdidas con búsqueda, orden y paginación.
•	El Encargado pulsa Nueva pérdida.
•	El sistema muestra el formulario con contexto (usuario, sucursal, fecha y hora).
•	El Encargado selecciona el Tipo de pérdida:
•	Merma por calidad → origen: Control de Calidad (NO CONFORME).
•	Pérdida en proceso → origen: Control de Producción.
•	El sistema habilita el selector de origen correspondiente y carga productos con cantidad máxima disponible.
•	El Encargado selecciona producto, ingresa cantidad y observación (máx. 30 caracteres) y pulsa Agregar ítem.
•	El sistema valida: cantidad > 0; no excede lo pendiente (proceso) o lo rechazado disponible (calidad); motivo obligatorio. Agrega la línea al detalle.
•	Puede repetir la selección de producto, cantidad y observación para más ítems del mismo origen (distintos productos en calidad; en proceso un producto por control).
•	El Encargado pulsa Guardar.
•	El sistema valida al menos un ítem, unicidad de registro activo por origen, asigna número correlativo y aplica efectos:
•	Pérdida en proceso: si es última etapa, reintegra cantidad pendiente en la OP; no descuenta stock de producto terminado.
•	Merma por calidad: descuenta stock del depósito del lote PT (baja definitiva).
•	Registra cabecera y detalle en estado REGISTRADO, bitácora y confirma en la lista.

Edición
•	El Encargado abre un registro REGISTRADO y pulsa Editar.
•	El sistema muestra origen fijo; permite ajustar fecha, cantidades y observaciones por ítem.
•	El sistema revierte efectos previos, valida topes y reaplica diferencias sobre origen y/o stock.
•	El Encargado pulsa Guardar; el sistema registra bitácora (MODIFICACION) y refresca la lista.
•	Restricción: no se cambia el tipo ni el origen; control/OP anulados o con PT (proceso) bloquean la operación.

Operaciones complementarias
•	Quitar ítem: elimina una línea del detalle antes de guardar.
•	Ver detalle: modal con origen, productos, cantidades y observaciones.
Anular (desde la lista):
•	Seleccionar registro REGISTRADO → Anular → Confirmar.
•	Verificar estado y que el origen siga válido (control activo, CC no anulado, OP sin PT si es proceso).
•	Revertir efectos: repone stock (merma) o ajusta pendientes OP (proceso en última etapa); estado ANULADO y bitácora.
•	Cancelar: abandona el formulario sin guardar.
•	Cerrar: cierra el formulario sin guardar.

Flujo Alternativo
•	Tipo u origen no seleccionado → bloqueo.
•	Cantidad superior al disponible → rechazo con mensaje.
•	Stock insuficiente (merma) → rollback.
•	Origen con pérdida activa previa → rechazo (un registro activo por control).

Postcondiciones
•	Pérdida en estado REGISTRADO o ANULADO, con bitácora.
•	Ajustes aplicados sobre Control de Producción (pendientes OP) o stock (merma).
•	Trazabilidad: referencia a control origen, usuario, fecha y detalle por producto.

Descripción de las Tablas
•	perdidas
•	perdidas_detalle
•	tipo_perdida
•	control_produccion
•	control_produccion_detalle
•	control_calidad_produccion
•	control_calidad_detalle
•	producto_terminado
•	productos_terminados_detalle
•	orden_produccion
•	orden_detalle_produccion
•	productos
•	stock_producto
•	usuarios
•	bitacora
