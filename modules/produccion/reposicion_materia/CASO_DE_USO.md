Gestionar Producción
Registrar reposición de materia prima

Descripción Básica
Permitir al Encargado de Producción registrar reposiciones de materia prima hacia un depósito, tomando como referencia un Pedido de MP (interno) y actualizando el stock. La reposición no puede exceder lo pendiente del pedido. El depósito destino es el definido en el pedido seleccionado.

Actores Relacionados
•	Encargado de Producción (primario).
•	Sistema (secundario).

Pre Condición
•	Usuario con permisos del módulo Producción y sucursal activa.
•	Existen depósitos activos.
•	Existen pedidos de materia prima (y su detalle) en estado PENDIENTE o PARCIAL con cantidades pendientes de reponer.
•	Materias primas registradas y activas.
•	Conexión estable a la base de datos.

Flujo de Eventos
Flujo Básico
•	El Encargado accede a Producción → Reposición de MP.
•	El sistema muestra la lista de reposiciones con búsqueda, orden y paginación.
•	El Encargado pulsa Nueva reposición.
•	El sistema muestra el formulario con contexto (usuario, sucursal, fecha y hora).
•	El Encargado selecciona el Pedido de materia prima (PENDIENTE o PARCIAL con líneas pendientes).
•	El sistema muestra el depósito destino del pedido (solo lectura) y la grilla del detalle: materias primas con cantidades solicitadas, ya repuestas y pendientes.
•	El Encargado ingresa la cantidad a reponer en cada materia prima que corresponda (puede ser parcial por ítem).
•	El sistema valida: cantidad > 0 y numérica; la materia prima pertenece al detalle del pedido; la cantidad no excede la pendiente de ese ítem.
•	El Encargado pulsa Registrar reposición.
•	El sistema valida al menos un ítem con cantidad, asigna número correlativo, registra cabecera y detalle y aplica efectos:
•	Incrementa stock_materia_prima en el depósito del pedido.
•	Actualiza cantidad_repuesta en pedido_materia_detalle_produccion y recalcula estado del pedido (PARCIAL/COMPLETADO).
•	Registra bitácora y confirma en la lista.

Edición
•	El Encargado abre una reposición en estado REGISTRADO y pulsa Editar.
•	El sistema muestra pedido y depósito fijos; permite ajustar fecha y cantidades por ítem.
•	Al guardar, revierte efectos previos, valida topes del pedido y stock disponible (bloquea reducciones si hubo consumos posteriores en Control de Producción), reaplica diferencias y registra bitácora (MODIFICACION).
•	Restricción: no se cambia pedido ni depósito; pedido anulado bloquea la operación.

Operaciones complementarias
•	Ver detalle: modal con pedido, depósito, materias y cantidades repuestas.
Anular (desde la lista):
•	Seleccionar reposición REGISTRADA → Anular → Confirmar.
•	Verificar stock suficiente en depósito para revertir (no debe haberse consumido la MP repuesta).
•	Revertir efectos: stock (− cantidad) y cantidad_repuesta del pedido; estado ANULADO y bitácora.
•	Cancelar: abandona el formulario sin guardar.
•	Cerrar: cierra el formulario sin guardar.

Flujo Alternativo
•	Pedido no seleccionado o sin líneas pendientes → bloqueo.
•	Materia prima no pertenece al pedido o cantidad excede pendiente → rechazo.
•	Stock insuficiente al anular o reducir cantidades → rechazo con mensaje.

Postcondiciones
•	Reposición registrada en estado REGISTRADO o ANULADO, con bitácora.
•	Stock actualizado en el depósito destino.
•	Pendientes del pedido ajustados con trazabilidad por ítem.

Descripción de las Tablas
•	reposicion_materia
•	reposicion_materia_detalle
•	pedido_materia_produccion
•	pedido_materia_detalle_produccion
•	materia_prima
•	stock_materia_prima
•	deposito
•	usuarios
•	bitacora
