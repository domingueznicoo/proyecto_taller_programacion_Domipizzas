Gestionar Producción
Registrar pedidos de materia prima

Descripción Básica
Permitir al Encargado de Producción solicitar materia prima hacia un depósito, generando un pedido con una o más líneas (materia prima y cantidades requeridas). El pedido no mueve stock; establece cantidades pendientes (`cantidad_repuesta = 0`) que serán atendidas mediante Reposición de materia prima.

Actores Relacionados
•	Encargado de Producción (primario).
•	Sistema (secundario).

Pre Condición
•	Usuario con permisos del módulo Producción y sucursal activa.
•	Depósitos registrados en el sistema.
•	Materias primas registradas y en estado ACTIVO.
•	Conexión estable a la base de datos.

Flujo de Eventos
Flujo Básico
•	El Encargado accede a Producción → Pedidos de materia prima.
•	El sistema muestra la lista de pedidos con búsqueda, orden y paginación.
•	El Encargado pulsa Nuevo pedido.
•	El sistema muestra el formulario con contexto (usuario, sucursal, fecha y hora).
•	El Encargado selecciona depósito destino.
•	El Encargado elige materia prima, ingresa cantidad requerida y pulsa Agregar.
•	El sistema valida: cantidad numérica > 0; MP activa; no duplicar la misma MP en el detalle.
•	Puede repetir la selección para más materias primas.
•	El Encargado pulsa Guardar pedido.
•	El sistema valida al menos un ítem, asigna número correlativo, registra cabecera y detalle en estado PENDIENTE con `cantidad_repuesta = 0` por ítem.
•	Registra bitácora y confirma en la lista.

Edición
•	El Encargado abre un pedido en estado PENDIENTE sin reposiciones ni cantidades repuestas y pulsa Editar.
•	El sistema permite ajustar fecha, depósito destino, cantidades y detalle (agregar/quitar ítems).
•	Al guardar, actualiza cabecera/detalle y registra bitácora (MODIFICACION).
•	Restricción: pedidos PARCIAL, COMPLETADO, ANULADO o con reposiciones activas no se editan.

Operaciones complementarias
•	Quitar ítem: elimina una línea del detalle antes de guardar.
•	Ver detalle: modal con solicitada, repuesta y pendiente por ítem.
•	Registrar reposición: acceso directo desde la lista (PENDIENTE/PARCIAL) al módulo Reposición de MP.
Anular (desde la lista):
•	Seleccionar pedido PENDIENTE → Anular → Confirmar.
•	Verificar que no tenga reposiciones registradas ni cantidades repuestas.
•	Cambiar estado a ANULADO, bitácora y confirmación.
•	Cancelar: abandona el formulario sin guardar.
•	Cerrar: cierra el formulario sin guardar.

Flujo Alternativo
•	Depósito no seleccionado → bloqueo.
•	Sin ítems → bloqueo.
•	Cantidad ≤ 0 o no numérica → rechazo.
•	MP inactiva o inexistente → rechazo.
•	Pedido con reposiciones al editar/anular → rechazo con mensaje.

Postcondiciones
•	Pedido registrado en PENDIENTE, PARCIAL, COMPLETADO o ANULADO, con bitácora.
•	Cada ítem queda con pendiente = solicitada − repuesta, listo para Reposición de MP.
•	Sin impacto en stock en esta etapa.

Descripción de las Tablas
•	pedido_materia_produccion
•	pedido_materia_detalle_produccion
•	materia_prima
•	deposito
•	sucursales
•	usuarios
•	reposicion_materia
•	bitacora
