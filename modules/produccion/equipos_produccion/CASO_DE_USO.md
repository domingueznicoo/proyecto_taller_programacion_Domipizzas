Gestionar Producción
Registrar Equipos de trabajo

Descripción Básica
Permitir al Encargado de Producción administrar equipos de trabajo asociados a una Orden de Producción (OP) y a una etapa productiva, asignando integrantes (trabajadores) y sus tareas/roles. El equipo queda en estado Pendiente y servirá de base para la planificación y control de la OP.

Actores Relacionados
•	Encargado de Producción (primario).
•	Sistema (secundario).

Pre Condición
•	Usuario con permisos del módulo Producción y sucursal asignada.
•	Orden de Producción existente (Pendiente o En proceso).
•	Trabajadores registrados y activos.
•	Etapas de producción activas para los productos de la OP.
•	Conexión estable a la base de datos.

Flujo de Eventos
Flujo Básico
1.	El Encargado accede a Producción → Equipos de trabajo.
2.	El sistema muestra la lista de equipos con búsqueda, orden y paginación.
3.	El Encargado pulsa Nuevo.
4.	El sistema muestra el formulario con contexto (usuario, sucursal, fecha/hora).
5.	El Encargado selecciona la Orden de Producción (OP).
6.	El Encargado selecciona la etapa de producción.
7.	El Encargado ingresa el nombre del equipo (p. ej., “Equipo OP-001 / Turno A”; opcional, se autogenera si queda vacío).
8.	El Encargado selecciona un trabajador.
9.	El Encargado ingresa la tarea/rol del trabajador en la OP (p. ej., armado, cocción, empaque).
10.	El Encargado hace clic en Agregar integrante.
11.	El sistema valida campos obligatorios y agrega la fila al detalle (evita duplicados del mismo trabajador en el equipo).
12.	El Encargado puede repetir 8–11 para sumar más integrantes.
13.	El Encargado pulsa Guardar.
14.	El sistema valida que exista al menos un integrante, asigna número correlativo, registra cabecera y detalle y deja el equipo en Pendiente.
15.	El sistema confirma y actualiza la lista.

Edición
16.	El Encargado selecciona un equipo Pendiente y pulsa Editar.
17.	El sistema muestra el formulario con los datos cargados (OP y etapa solo lectura).
18.	El Encargado puede ajustar nombre, fecha y detalle (agregar/quitar integrantes o cambiar tareas/roles).
19.	El sistema valida: detalle no vacío y sin duplicados.
20.	El Encargado pulsa Guardar.
21.	El sistema actualiza el equipo, registra bitácora (qué/quién/cuándo) y refresca la lista.
a.	Restricción: si la OP está Terminada/Anulada, o el equipo está Activo o Anulado, no se permite editar.

Operaciones complementarias
21.	Quitar integrante: el Encargado elimina una fila del detalle; el sistema actualiza el detalle.
Anular (desde la lista):
22.1. El Encargado ubica el equipo Pendiente y pulsa Anular.
22.2. El sistema solicita confirmación.
22.3. Verifica que el equipo no esté referenciado por Control de Producción, Controles de calidad o Productos terminados.
22.4. Si cumple, cambia estado a Anulado y confirma; si no, rechaza e informa motivo.
23.	Cancelar: abandona el formulario sin guardar.
24.	Cerrar: cierra el formulario sin guardar.

Flujo Alternativo
•	Campos vacíos o duplicado de trabajador → mensaje y foco.
•	OP no válida (estado incompatible) → bloqueo.
•	Trabajador inactivo → rechazo.

Postcondiciones
•	Equipo registrado en estado Pendiente, Activo (al usarse en control de producción) o Anulado (si se anuló).
•	Bitácora de alta/edición/anulación.
•	Trazabilidad: el equipo queda asociado a la OP y etapa, y disponible para Control de Producción y Controles de Calidad.

Descripción de las Tablas
•	equipos_produccion
•	equipo_detalle
•	orden_produccion
•	trabajadores
•	usuarios
•	sucursales
