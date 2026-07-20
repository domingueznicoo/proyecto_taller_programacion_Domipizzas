
CREATE TABLE tipo_producto (
                cod_tipo_prod INTEGER NOT NULL,
                t_p_descrip VARCHAR(50) NOT NULL,
                CONSTRAINT tipo_producto_pk PRIMARY KEY (cod_tipo_prod)
);
COMMENT ON TABLE tipo_producto IS 'Tipos de productos para clasificación';
COMMENT ON COLUMN tipo_producto.cod_tipo_prod IS 'Código único del tipo de producto';
COMMENT ON COLUMN tipo_producto.t_p_descrip IS 'Descripción del tipo de producto';


CREATE INDEX idx_tipo_producto_descrip
 ON tipo_producto
 ( t_p_descrip ASC );

CREATE SEQUENCE cargos_id_cargo_seq;

CREATE TABLE cargos (
                id_cargo INTEGER DEFAULT nextval('cargos_id_cargo_seq'::regclass) NOT NULL DEFAULT nextval('cargos_id_cargo_seq'),
                cargo_descripcion VARCHAR(30) NOT NULL,
                estado_cargo VARCHAR(30) NOT NULL,
                id_usuario INTEGER,
                CONSTRAINT cargos_pk PRIMARY KEY (id_cargo)
);


ALTER SEQUENCE cargos_id_cargo_seq OWNED BY cargos.id_cargo;

CREATE SEQUENCE personal_id_personal_seq;

CREATE TABLE personal (
                id_personal INTEGER DEFAULT nextval('personal_id_personal_seq'::regclass) NOT NULL DEFAULT nextval('personal_id_personal_seq'),
                personal_estado VARCHAR(30) NOT NULL,
                personal_nombre VARCHAR(30) NOT NULL,
                personal_apellido VARCHAR(30) NOT NULL,
                personal_telefono VARCHAR(30) NOT NULL,
                personal_ci VARCHAR(30) NOT NULL,
                id_cargo INTEGER NOT NULL,
                id_usuario INTEGER,
                id_sucursal INTEGER,
                CONSTRAINT personal_pk PRIMARY KEY (id_personal)
);
COMMENT ON COLUMN personal.id_sucursal IS 'ID de la sucursal a la que pertenece el personal';


ALTER SEQUENCE personal_id_personal_seq OWNED BY personal.id_personal;

CREATE INDEX idx_personal_sucursal
 ON personal
 ( id_sucursal ASC );

CREATE SEQUENCE usuarios_id_usuario_seq;

CREATE TABLE usuarios (
                id_usuario INTEGER DEFAULT nextval('usuarios_id_usuario_seq'::regclass) NOT NULL DEFAULT nextval('usuarios_id_usuario_seq'),
                estado_usuario VARCHAR(30) NOT NULL,
                id_sucursal INTEGER NOT NULL,
                modulo_id INTEGER NOT NULL,
                username VARCHAR(30) NOT NULL,
                usua_password VARCHAR(100) NOT NULL,
                id_cargo INTEGER,
                id_personal INTEGER,
                CONSTRAINT usuarios_pk PRIMARY KEY (id_usuario)
);
COMMENT ON COLUMN usuarios.id_personal IS 'Referencia opcional al registro de personal asociado al usuario';


ALTER SEQUENCE usuarios_id_usuario_seq OWNED BY usuarios.id_usuario;

CREATE INDEX idx_usuarios_username
 ON usuarios
 ( username ASC );

CREATE SEQUENCE vehiculos_vehiculo_id_seq;

CREATE TABLE vehiculos (
                vehiculo_id INTEGER DEFAULT nextval('vehiculos_vehiculo_id_seq'::regclass) NOT NULL DEFAULT nextval('vehiculos_vehiculo_id_seq'),
                vehiculo_marca VARCHAR(30) NOT NULL,
                vehiculo_ano VARCHAR(30) NOT NULL,
                vehiculo_color VARCHAR(30) NOT NULL,
                id_usuario INTEGER NOT NULL,
                CONSTRAINT vehiculos_pk PRIMARY KEY (vehiculo_id)
);


ALTER SEQUENCE vehiculos_vehiculo_id_seq OWNED BY vehiculos.vehiculo_id;

CREATE SEQUENCE unidad_medida_id_unidad_seq;

CREATE TABLE unidad_medida (
                id_unidad INTEGER DEFAULT nextval('unidad_medida_id_unidad_seq'::regclass) NOT NULL DEFAULT nextval('unidad_medida_id_unidad_seq'),
                unidad_descri VARCHAR(30) NOT NULL,
                id_usuario INTEGER NOT NULL,
                CONSTRAINT unidad_medida_pk PRIMARY KEY (id_unidad)
);


ALTER SEQUENCE unidad_medida_id_unidad_seq OWNED BY unidad_medida.id_unidad;

CREATE SEQUENCE tipo_perdida_tipo_perdida_id_seq;

CREATE TABLE tipo_perdida (
                tipo_perdida_id INTEGER DEFAULT nextval('tipo_perdida_tipo_perdida_id_seq'::regclass) NOT NULL DEFAULT nextval('tipo_perdida_tipo_perdida_id_seq'),
                tipo_perdida_descri VARCHAR(30) NOT NULL,
                id_usuario INTEGER NOT NULL,
                CONSTRAINT tipo_perdida_pk PRIMARY KEY (tipo_perdida_id)
);


ALTER SEQUENCE tipo_perdida_tipo_perdida_id_seq OWNED BY tipo_perdida.tipo_perdida_id;

CREATE SEQUENCE tipo_iva_iva_id_seq;

CREATE TABLE tipo_iva (
                iva_id INTEGER DEFAULT nextval('tipo_iva_iva_id_seq'::regclass) NOT NULL DEFAULT nextval('tipo_iva_iva_id_seq'),
                iva_descri VARCHAR(30) NOT NULL,
                id_usuario INTEGER NOT NULL,
                CONSTRAINT tipo_iva_pk PRIMARY KEY (iva_id)
);


ALTER SEQUENCE tipo_iva_iva_id_seq OWNED BY tipo_iva.iva_id;

CREATE SEQUENCE sucursales_id_sucursal_seq;

CREATE TABLE sucursales (
                id_sucursal INTEGER DEFAULT nextval('sucursales_id_sucursal_seq'::regclass) NOT NULL DEFAULT nextval('sucursales_id_sucursal_seq'),
                descripcion_sucursal VARCHAR(30) NOT NULL,
                estado_sucursal VARCHAR(30) NOT NULL,
                id_usuario INTEGER,
                CONSTRAINT sucursales_pk PRIMARY KEY (id_sucursal)
);


ALTER SEQUENCE sucursales_id_sucursal_seq OWNED BY sucursales.id_sucursal;

CREATE SEQUENCE proveedor_id_proveedor_seq;

CREATE TABLE proveedor (
                id_proveedor INTEGER DEFAULT nextval('proveedor_id_proveedor_seq'::regclass) NOT NULL DEFAULT nextval('proveedor_id_proveedor_seq'),
                id_usuario INTEGER NOT NULL,
                razon_social VARCHAR(150) NOT NULL,
                ruc_proveedor VARCHAR(20) NOT NULL,
                telefono_proveedor VARCHAR(30),
                direccion_proveedor VARCHAR(200),
                email_proveedor VARCHAR(120),
                estado_proveedor VARCHAR(10) DEFAULT 'ACTIVO'::character varying NOT NULL,
                CONSTRAINT proveedor_pk PRIMARY KEY (id_proveedor)
);


ALTER SEQUENCE proveedor_id_proveedor_seq OWNED BY proveedor.id_proveedor;

CREATE SEQUENCE productos_producto_id_seq;

CREATE TABLE productos (
                producto_id INTEGER DEFAULT nextval('productos_producto_id_seq'::regclass) NOT NULL DEFAULT nextval('productos_producto_id_seq'),
                producto_precio INTEGER NOT NULL,
                producto_descri VARCHAR(30) NOT NULL,
                id_unidad INTEGER NOT NULL,
                id_usuario INTEGER NOT NULL,
                iva_id INTEGER,
                producto_estado VARCHAR(30) DEFAULT 'ACTIVO'::character varying,
                id_tipo_producto INTEGER,
                CONSTRAINT productos_pk PRIMARY KEY (producto_id)
);
COMMENT ON COLUMN productos.iva_id IS 'Tipo de IVA aplicable (5%, 10%, exento)';
COMMENT ON COLUMN productos.producto_estado IS 'Estado del producto: ACTIVO, INACTIVO, ANULADO';
COMMENT ON COLUMN productos.id_tipo_producto IS 'Tipo de producto (opcional)';


ALTER SEQUENCE productos_producto_id_seq OWNED BY productos.producto_id;

CREATE INDEX idx_productos_tipo_producto
 ON productos
 ( id_tipo_producto ASC );

CREATE SEQUENCE pedidos_compra_id_pedido_compra_seq;

CREATE TABLE pedidos_compra (
                id_pedido_compra INTEGER DEFAULT nextval('pedidos_compra_id_pedido_compra_seq'::regclass) NOT NULL DEFAULT nextval('pedidos_compra_id_pedido_compra_seq'),
                pedido_fecha_emision DATE NOT NULL,
                pedido_estado VARCHAR(20) NOT NULL,
                id_usuario INTEGER NOT NULL,
                id_sucursal INTEGER NOT NULL,
                pedido_observaciones TEXT,
                pedido_ultima_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT pedidos_compra_pk PRIMARY KEY (id_pedido_compra)
);
COMMENT ON COLUMN pedidos_compra.pedido_observaciones IS 'Observaciones o notas adicionales del pedido de compra';
COMMENT ON COLUMN pedidos_compra.pedido_ultima_modificacion IS 'Timestamp de última modificación para control de concurrencia';


ALTER SEQUENCE pedidos_compra_id_pedido_compra_seq OWNED BY pedidos_compra.id_pedido_compra;

CREATE SEQUENCE presupuesto_compra_id_presupuesto_compra_seq;

CREATE TABLE presupuesto_compra (
                id_presupuesto_compra INTEGER DEFAULT nextval('presupuesto_compra_id_presupuesto_compra_seq'::regclass) NOT NULL DEFAULT nextval('presupuesto_compra_id_presupuesto_compra_seq'),
                presu_total INTEGER NOT NULL,
                presu_fecha DATE NOT NULL,
                presu_estado VARCHAR(20) NOT NULL,
                id_pedido_compra INTEGER NOT NULL,
                id_usuario INTEGER NOT NULL,
                id_sucursal INTEGER NOT NULL,
                id_proveedor INTEGER NOT NULL,
                descuento_total NUMERIC(12,2) DEFAULT 0,
                presu_observaciones TEXT,
                presu_ultima_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT presupuesto_compra_pk PRIMARY KEY (id_presupuesto_compra)
);
COMMENT ON COLUMN presupuesto_compra.descuento_total IS 'Descuento general aplicado al presupuesto (en la misma moneda que el total)';
COMMENT ON COLUMN presupuesto_compra.presu_observaciones IS 'Observaciones o notas adicionales del presupuesto';
COMMENT ON COLUMN presupuesto_compra.presu_ultima_modificacion IS 'Timestamp de última modificación para control de concurrencia';


ALTER SEQUENCE presupuesto_compra_id_presupuesto_compra_seq OWNED BY presupuesto_compra.id_presupuesto_compra;

CREATE SEQUENCE pedido_materia_produccion_id_pedido_mat_prod_seq;

CREATE TABLE pedido_materia_produccion (
                id_pedido_mat_prod INTEGER DEFAULT nextval('pedido_materia_produccion_id_pedido_mat_prod_seq'::regclass) NOT NULL DEFAULT nextval('pedido_materia_produccion_id_pedido_mat_prod_seq'),
                ped_mat_prod_fecha DATE NOT NULL,
                ped_mat_prod_estado VARCHAR(30) NOT NULL,
                id_usuario INTEGER NOT NULL,
                CONSTRAINT pedido_materia_produccion_pk PRIMARY KEY (id_pedido_mat_prod)
);


ALTER SEQUENCE pedido_materia_produccion_id_pedido_mat_prod_seq OWNED BY pedido_materia_produccion.id_pedido_mat_prod;

CREATE SEQUENCE parametros_control_parametro_id_seq;

CREATE TABLE parametros_control (
                parametro_id INTEGER DEFAULT nextval('parametros_control_parametro_id_seq'::regclass) NOT NULL DEFAULT nextval('parametros_control_parametro_id_seq'),
                parametro_descri VARCHAR(30) NOT NULL,
                producto_id INTEGER NOT NULL,
                id_usuario INTEGER NOT NULL,
                CONSTRAINT parametros_control_pk PRIMARY KEY (parametro_id)
);


ALTER SEQUENCE parametros_control_parametro_id_seq OWNED BY parametros_control.parametro_id;

CREATE SEQUENCE orden_produccion_orden_id_seq;

CREATE TABLE orden_produccion (
                orden_id INTEGER DEFAULT nextval('orden_produccion_orden_id_seq'::regclass) NOT NULL DEFAULT nextval('orden_produccion_orden_id_seq'),
                orden_prod_fecha DATE NOT NULL,
                orden_prod_fecha_entrega DATE,
                orden_prod_estado VARCHAR(30) NOT NULL,
                id_usuario INTEGER NOT NULL,
                id_cliente INTEGER NOT NULL,
                id_pedido_venta INTEGER NOT NULL,
                CONSTRAINT orden_produccion_pk PRIMARY KEY (orden_id)
);


ALTER SEQUENCE orden_produccion_orden_id_seq OWNED BY orden_produccion.orden_id;

CREATE SEQUENCE producto_terminado_terminado_id_seq;

CREATE TABLE producto_terminado (
                terminado_id INTEGER DEFAULT nextval('producto_terminado_terminado_id_seq'::regclass) NOT NULL DEFAULT nextval('producto_terminado_terminado_id_seq'),
                orden_id INTEGER NOT NULL,
                terminado_fecha DATE NOT NULL,
                id_usuario INTEGER NOT NULL,
                CONSTRAINT producto_terminado_pk PRIMARY KEY (terminado_id)
);


ALTER SEQUENCE producto_terminado_terminado_id_seq OWNED BY producto_terminado.terminado_id;

CREATE TABLE productos_terminados_detalle (
                terminado_id INTEGER NOT NULL,
                producto_id INTEGER NOT NULL,
                terminado_cantidad INTEGER NOT NULL,
                CONSTRAINT productos_terminados_detalle_pk PRIMARY KEY (terminado_id, producto_id)
);


CREATE SEQUENCE orden_de_compra_id_orden_compra_seq;

CREATE TABLE orden_de_compra (
                id_orden_compra INTEGER DEFAULT nextval('orden_de_compra_id_orden_compra_seq'::regclass) NOT NULL DEFAULT nextval('orden_de_compra_id_orden_compra_seq'),
                orden_fecha DATE NOT NULL,
                orden_estado VARCHAR(30) NOT NULL,
                orden_total INTEGER NOT NULL,
                id_presupuesto_compra INTEGER NOT NULL,
                id_proveedor INTEGER NOT NULL,
                id_sucursal INTEGER NOT NULL,
                id_usuario INTEGER NOT NULL,
                orden_condicion VARCHAR(20) DEFAULT 'CONTADO'::character varying NOT NULL,
                orden_observaciones TEXT,
                CONSTRAINT orden_de_compra_pk PRIMARY KEY (id_orden_compra)
);
COMMENT ON COLUMN orden_de_compra.orden_condicion IS 'Condición de pago: CONTADO o CREDITO';
COMMENT ON COLUMN orden_de_compra.orden_observaciones IS 'Observaciones o condiciones adicionales de la orden de compra';


ALTER SEQUENCE orden_de_compra_id_orden_compra_seq OWNED BY orden_de_compra.id_orden_compra;

CREATE SEQUENCE motivo_id_motivo_seq;

CREATE TABLE motivo (
                id_motivo INTEGER DEFAULT nextval('motivo_id_motivo_seq'::regclass) NOT NULL DEFAULT nextval('motivo_id_motivo_seq'),
                motivo_descripcion VARCHAR(30) NOT NULL,
                id_usuario INTEGER NOT NULL,
                categoria_motivo VARCHAR(20) DEFAULT 'NOTA_CREDITO'::character varying,
                CONSTRAINT motivo_pk PRIMARY KEY (id_motivo)
);
COMMENT ON COLUMN motivo.categoria_motivo IS 'Categoría del motivo: AJUSTE (para ajustes de inventario), NOTA_CREDITO (para notas de crédito/débito)';


ALTER SEQUENCE motivo_id_motivo_seq OWNED BY motivo.id_motivo;

CREATE INDEX idx_motivo_categoria
 ON motivo
 ( categoria_motivo ASC );

CREATE SEQUENCE modulos_modulo_id_seq;

CREATE TABLE modulos (
                modulo_id INTEGER DEFAULT nextval('modulos_modulo_id_seq'::regclass) NOT NULL DEFAULT nextval('modulos_modulo_id_seq'),
                modulo_descri VARCHAR(30) NOT NULL,
                id_usuario INTEGER,
                CONSTRAINT modulos_pk PRIMARY KEY (modulo_id)
);


ALTER SEQUENCE modulos_modulo_id_seq OWNED BY modulos.modulo_id;

CREATE SEQUENCE materia_prima_id_materia_prima_seq;

CREATE TABLE materia_prima (
                id_materia_prima INTEGER DEFAULT nextval('materia_prima_id_materia_prima_seq'::regclass) NOT NULL DEFAULT nextval('materia_prima_id_materia_prima_seq'),
                materia_prima_descripcion VARCHAR(30) NOT NULL,
                materia_prima_estado VARCHAR(30) NOT NULL,
                id_unidad INTEGER NOT NULL,
                id_usuario INTEGER NOT NULL,
                iva_id INTEGER NOT NULL,
                CONSTRAINT materia_prima_pk PRIMARY KEY (id_materia_prima)
);


ALTER SEQUENCE materia_prima_id_materia_prima_seq OWNED BY materia_prima.id_materia_prima;

CREATE TABLE presupuesto_detalle_compra (
                id_presupuesto_compra INTEGER NOT NULL,
                id_materia_prima INTEGER NOT NULL,
                detalle_presu_cantidad INTEGER NOT NULL,
                detalle_presu_precio_compra INTEGER NOT NULL,
                descuento NUMERIC(12,2) DEFAULT 0,
                detalle_presu_iva NUMERIC(12,2) DEFAULT 0,
                CONSTRAINT presupuesto_detalle_compra_pk PRIMARY KEY (id_presupuesto_compra, id_materia_prima)
);
COMMENT ON COLUMN presupuesto_detalle_compra.descuento IS 'Descuento aplicado al ítem (en la misma moneda que el precio)';
COMMENT ON COLUMN presupuesto_detalle_compra.detalle_presu_iva IS 'Monto de IVA calculado para el ítem';


CREATE TABLE pedido_materia_detalle_produccion (
                id_pedido_mat_prod INTEGER NOT NULL,
                id_materia_prima INTEGER NOT NULL,
                ped_mat_prod_cantidad INTEGER NOT NULL,
                CONSTRAINT pedido_materia_detalle_produccion_pk PRIMARY KEY (id_pedido_mat_prod, id_materia_prima)
);


CREATE TABLE pedido_detalle_compra (
                id_pedido_compra INTEGER NOT NULL,
                id_materia_prima INTEGER NOT NULL,
                cantidad_pedido INTEGER NOT NULL,
                CONSTRAINT pedido_detalle_compra_pk PRIMARY KEY (id_pedido_compra, id_materia_prima)
);


CREATE TABLE orden_detalle_compra (
                id_materia_prima INTEGER NOT NULL,
                id_orden_compra INTEGER NOT NULL,
                oc_cantidad_compra INTEGER NOT NULL,
                oc_precio_compra INTEGER NOT NULL,
                CONSTRAINT orden_detalle_compra_pk PRIMARY KEY (id_materia_prima, id_orden_compra)
);


CREATE SEQUENCE factura_compra_id_factura_compra_seq;

CREATE TABLE factura_compra (
                id_factura_compra INTEGER DEFAULT nextval('factura_compra_id_factura_compra_seq'::regclass) NOT NULL DEFAULT nextval('factura_compra_id_factura_compra_seq'),
                numero_factura VARCHAR(30) NOT NULL,
                timbrado VARCHAR(30) NOT NULL,
                fac_fecha_vencimiento DATE NOT NULL,
                fact_fecha_compra DATE NOT NULL,
                fac_total INTEGER NOT NULL,
                fac_estado VARCHAR(20) NOT NULL,
                fac_plazo VARCHAR(30) NOT NULL,
                fac_remision DOUBLE PRECISION NOT NULL,
                id_usuario INTEGER NOT NULL,
                id_sucursal INTEGER NOT NULL,
                id_orden_compra INTEGER NOT NULL,
                tipo_operacion VARCHAR(30),
                CONSTRAINT factura_compra_pk PRIMARY KEY (id_factura_compra)
);


ALTER SEQUENCE factura_compra_id_factura_compra_seq OWNED BY factura_compra.id_factura_compra;

CREATE SEQUENCE nota_compra_id_nota_compra_seq;

CREATE TABLE nota_compra (
                id_nota_compra INTEGER DEFAULT nextval('nota_compra_id_nota_compra_seq'::regclass) NOT NULL DEFAULT nextval('nota_compra_id_nota_compra_seq'),
                nota_compra_tipo VARCHAR(50) NOT NULL,
                nota_compra_fecha DATE NOT NULL,
                nota_nro BIGINT NOT NULL,
                nota_compra_timbrado VARCHAR(30) NOT NULL,
                nota_compra_inicio DATE NOT NULL,
                nota_compra_vencimiento DATE NOT NULL,
                nota_compra_estado VARCHAR(20) NOT NULL,
                nota_total INTEGER NOT NULL,
                id_usuario INTEGER NOT NULL,
                id_sucursal INTEGER NOT NULL,
                id_proveedor INTEGER NOT NULL,
                id_motivo INTEGER NOT NULL,
                id_factura_compra INTEGER,
                CONSTRAINT nota_compra_pk PRIMARY KEY (id_nota_compra)
);


ALTER SEQUENCE nota_compra_id_nota_compra_seq OWNED BY nota_compra.id_nota_compra;

CREATE TABLE nota_detalle_compra (
                id_materia_prima INTEGER NOT NULL,
                id_nota_compra INTEGER NOT NULL,
                nota_compra_cantidad INTEGER NOT NULL,
                tipo_iva NUMERIC NOT NULL,
                nota_precio INTEGER NOT NULL,
                CONSTRAINT nota_detalle_compra_pk PRIMARY KEY (id_materia_prima, id_nota_compra)
);


CREATE SEQUENCE iva_compra_iva_id_seq;

CREATE TABLE iva_compra (
                iva_id INTEGER DEFAULT nextval('iva_compra_id_libro_compra_seq'::regclass) NOT NULL DEFAULT nextval('iva_compra_iva_id_seq'),
                id_factura_compra INTEGER NOT NULL,
                iva_fecha DATE NOT NULL,
                iva_exento NUMERIC NOT NULL,
                iva_5 NUMERIC NOT NULL,
                iva_10 NUMERIC NOT NULL,
                CONSTRAINT iva_compra_pk PRIMARY KEY (iva_id)
);


ALTER SEQUENCE iva_compra_iva_id_seq OWNED BY iva_compra.iva_id;

CREATE TABLE factura_detalle_compra (
                id_materia_prima INTEGER NOT NULL,
                id_factura_compra INTEGER NOT NULL,
                fac_cantidad INTEGER NOT NULL,
                fac_iva NUMERIC NOT NULL,
                fac_precio INTEGER NOT NULL,
                CONSTRAINT factura_detalle_compra_pk PRIMARY KEY (id_materia_prima, id_factura_compra)
);


CREATE SEQUENCE etapa_produccion_etapa_id_seq;

CREATE TABLE etapa_produccion (
                etapa_id INTEGER DEFAULT nextval('etapa_produccion_etapa_id_seq'::regclass) NOT NULL DEFAULT nextval('etapa_produccion_etapa_id_seq'),
                etapa_fecha DATE NOT NULL,
                etapa_descri VARCHAR(30) NOT NULL,
                id_usuario INTEGER NOT NULL,
                CONSTRAINT etapa_produccion_pk PRIMARY KEY (etapa_id)
);


ALTER SEQUENCE etapa_produccion_etapa_id_seq OWNED BY etapa_produccion.etapa_id;

CREATE TABLE etapa_detalle_produccion (
                etapa_id INTEGER NOT NULL,
                producto_id INTEGER NOT NULL,
                etapa_nombre VARCHAR(30) NOT NULL,
                etapa_procedimiento VARCHAR(30) NOT NULL,
                CONSTRAINT etapa_detalle_produccion_pk PRIMARY KEY (etapa_id, producto_id)
);


CREATE SEQUENCE equipos_produccion_equipo_id_seq;

CREATE TABLE equipos_produccion (
                equipo_id INTEGER DEFAULT nextval('equipos_produccion_equipo_id_seq'::regclass) NOT NULL DEFAULT nextval('equipos_produccion_equipo_id_seq'),
                equipo_descri VARCHAR(30) NOT NULL,
                id_usuario INTEGER NOT NULL,
                equipo_estado VARCHAR(30) NOT NULL,
                CONSTRAINT equipos_produccion_pk PRIMARY KEY (equipo_id)
);


ALTER SEQUENCE equipos_produccion_equipo_id_seq OWNED BY equipos_produccion.equipo_id;

CREATE TABLE orden_detalle_produccion (
                orden_id INTEGER NOT NULL,
                producto_id INTEGER NOT NULL,
                orden_prod_cantidad INTEGER NOT NULL,
                equipo_id INTEGER NOT NULL,
                CONSTRAINT orden_detalle_produccion_pk PRIMARY KEY (orden_id, producto_id)
);


CREATE SEQUENCE deposito_deposito_id_seq;

CREATE TABLE deposito (
                deposito_id INTEGER DEFAULT nextval('deposito_deposito_id_seq'::regclass) NOT NULL DEFAULT nextval('deposito_deposito_id_seq'),
                deposito_descri VARCHAR(30) NOT NULL,
                id_usuario INTEGER NOT NULL,
                CONSTRAINT deposito_pk PRIMARY KEY (deposito_id)
);


ALTER SEQUENCE deposito_deposito_id_seq OWNED BY deposito.deposito_id;

CREATE SEQUENCE stock_producto_id_stock_productos_seq;

CREATE TABLE stock_producto (
                id_stock_productos INTEGER DEFAULT nextval('stock_producto_id_stock_productos_seq'::regclass) NOT NULL DEFAULT nextval('stock_producto_id_stock_productos_seq'),
                producto_id INTEGER NOT NULL,
                deposito_id INTEGER NOT NULL,
                stock_prod_ven DATE NOT NULL,
                stock_prod_existente INTEGER NOT NULL,
                id_usuario INTEGER NOT NULL,
                CONSTRAINT stock_producto_pk PRIMARY KEY (id_stock_productos)
);


ALTER SEQUENCE stock_producto_id_stock_productos_seq OWNED BY stock_producto.id_stock_productos;

CREATE INDEX idx_stock_producto_producto_deposito
 ON stock_producto
 ( producto_id ASC, deposito_id ASC );

CREATE SEQUENCE stock_materia_prima_id_stock_seq;

CREATE TABLE stock_materia_prima (
                id_stock INTEGER DEFAULT nextval('stock_materia_prima_id_stock_seq'::regclass) NOT NULL DEFAULT nextval('stock_materia_prima_id_stock_seq'),
                id_materia_prima INTEGER NOT NULL,
                stock_cantidad_minima INTEGER NOT NULL,
                stock_cantidad_maxima INTEGER NOT NULL,
                cantidad_existente INTEGER NOT NULL,
                deposito_id INTEGER NOT NULL,
                id_usuario INTEGER NOT NULL,
                CONSTRAINT stock_materia_prima_pk PRIMARY KEY (id_stock)
);


ALTER SEQUENCE stock_materia_prima_id_stock_seq OWNED BY stock_materia_prima.id_stock;

CREATE SEQUENCE reposicion_materia_reposicion_id_seq;

CREATE TABLE reposicion_materia (
                reposicion_id INTEGER DEFAULT nextval('reposicion_materia_reposicion_id_seq'::regclass) NOT NULL DEFAULT nextval('reposicion_materia_reposicion_id_seq'),
                reposicion_fecha DATE NOT NULL,
                reposicion_estado VARCHAR(30) NOT NULL,
                deposito_id INTEGER NOT NULL,
                id_usuario INTEGER NOT NULL,
                id_pedido_compra INTEGER NOT NULL,
                CONSTRAINT reposicion_materia_pk PRIMARY KEY (reposicion_id)
);


ALTER SEQUENCE reposicion_materia_reposicion_id_seq OWNED BY reposicion_materia.reposicion_id;

CREATE TABLE reposicion_materia_detalle (
                reposicion_id INTEGER NOT NULL,
                id_materia_prima INTEGER NOT NULL,
                reposicion_cantidad INTEGER NOT NULL,
                CONSTRAINT reposicion_materia_detalle_pk PRIMARY KEY (reposicion_id, id_materia_prima)
);


CREATE SEQUENCE cuentas_pagar_id_cuenta_pagar_seq;

CREATE TABLE cuentas_pagar (
                id_cuenta_pagar INTEGER DEFAULT nextval('cuentas_pagar_id_cuenta_pagar_seq'::regclass) NOT NULL DEFAULT nextval('cuentas_pagar_id_cuenta_pagar_seq'),
                monto_total INTEGER NOT NULL,
                monto_pendiente INTEGER NOT NULL,
                estado VARCHAR(15) NOT NULL,
                fecha_emision DATE NOT NULL,
                fecha_vencimiento DATE NOT NULL,
                id_sucursal INTEGER NOT NULL,
                id_usuario INTEGER NOT NULL,
                id_proveedor INTEGER NOT NULL,
                id_factura_compra INTEGER,
                plazo_cuenta INTEGER DEFAULT 0 NOT NULL,
                nro_cuota INTEGER DEFAULT 0 NOT NULL,
                CONSTRAINT cuentas_pagar_pk PRIMARY KEY (id_cuenta_pagar)
);


ALTER SEQUENCE cuentas_pagar_id_cuenta_pagar_seq OWNED BY cuentas_pagar.id_cuenta_pagar;

CREATE INDEX idx_cuentas_pagar_id_factura_compra
 ON cuentas_pagar
 ( id_factura_compra ASC );

CREATE SEQUENCE costo_produccion_costo_id_seq;

CREATE TABLE costo_produccion (
                costo_id INTEGER DEFAULT nextval('costo_produccion_costo_id_seq'::regclass) NOT NULL DEFAULT nextval('costo_produccion_costo_id_seq'),
                costo_fecha DATE NOT NULL,
                id_pedido_compra INTEGER NOT NULL,
                costo_estado VARCHAR(30) NOT NULL,
                costo_total INTEGER NOT NULL,
                id_usuario INTEGER NOT NULL,
                CONSTRAINT costo_produccion_pk PRIMARY KEY (costo_id)
);


ALTER SEQUENCE costo_produccion_costo_id_seq OWNED BY costo_produccion.costo_id;

CREATE TABLE costo_detalle_produccion (
                costo_id INTEGER NOT NULL,
                id_materia_prima INTEGER NOT NULL,
                costo_cantidad INTEGER NOT NULL,
                costo_precio INTEGER NOT NULL,
                CONSTRAINT costo_detalle_produccion_pk PRIMARY KEY (costo_id, id_materia_prima)
);


CREATE SEQUENCE conductores_conductor_id_seq;

CREATE TABLE conductores (
                conductor_id INTEGER DEFAULT nextval('conductores_conductor_id_seq'::regclass) NOT NULL DEFAULT nextval('conductores_conductor_id_seq'),
                conductor_nombre VARCHAR(30) NOT NULL,
                conductor_apellido VARCHAR(30) NOT NULL,
                conductor_telefono VARCHAR(30) NOT NULL,
                id_usuario INTEGER NOT NULL,
                CONSTRAINT conductores_pk PRIMARY KEY (conductor_id)
);


ALTER SEQUENCE conductores_conductor_id_seq OWNED BY conductores.conductor_id;

CREATE SEQUENCE nota_remision_compra_id_nota_remision_seq;

CREATE TABLE nota_remision_compra (
                id_nota_remision INTEGER DEFAULT nextval('nota_remision_compra_id_nota_remision_seq'::regclass) NOT NULL DEFAULT nextval('nota_remision_compra_id_nota_remision_seq'),
                id_factura_compra INTEGER,
                nota_fecha DATE NOT NULL,
                nota_remision_total INTEGER NOT NULL,
                nota_remision_nro VARCHAR(20) NOT NULL,
                nota_estado VARCHAR(30) NOT NULL,
                id_usuario INTEGER NOT NULL,
                id_proveedor INTEGER NOT NULL,
                deposito_id INTEGER NOT NULL,
                conductor_id INTEGER NOT NULL,
                vehiculo_id INTEGER NOT NULL,
                id_orden_compra INTEGER,
                CONSTRAINT nota_remision_compra_pk PRIMARY KEY (id_nota_remision)
);
COMMENT ON COLUMN nota_remision_compra.nota_remision_nro IS 'Número legal de remisión. Puede ser formato EEE-PPP-NNNNNNN o 13 dígitos.';
COMMENT ON COLUMN nota_remision_compra.id_orden_compra IS 'Orden de Compra asociada directamente. Permite acceso sin pasar por factura_compra.';


ALTER SEQUENCE nota_remision_compra_id_nota_remision_seq OWNED BY nota_remision_compra.id_nota_remision;

CREATE INDEX idx_nota_remision_compra_orden_compra
 ON nota_remision_compra
 ( id_orden_compra ASC );

CREATE TABLE nota_remision_detalle_compra (
                id_nota_remision INTEGER NOT NULL,
                id_materia_prima INTEGER NOT NULL,
                nota_cantidad INTEGER NOT NULL,
                nota_remi_iva NUMERIC NOT NULL,
                CONSTRAINT nota_remision_detalle_compra_pk PRIMARY KEY (id_nota_remision, id_materia_prima)
);


CREATE SEQUENCE trabajadores_trabajadores_id_seq;

CREATE TABLE trabajadores (
                trabajadores_id INTEGER DEFAULT nextval('trabajadores_trabajadores_id_seq'::regclass) NOT NULL DEFAULT nextval('trabajadores_trabajadores_id_seq'),
                id_usuario INTEGER NOT NULL,
                id_personal INTEGER NOT NULL,
                trabajador_estado VARCHAR(30) NOT NULL,
                CONSTRAINT trabajadores_pk PRIMARY KEY (trabajadores_id)
);


ALTER SEQUENCE trabajadores_trabajadores_id_seq OWNED BY trabajadores.trabajadores_id;

CREATE TABLE equipo_detalle (
                equipo_id INTEGER NOT NULL,
                trabajadores_id INTEGER NOT NULL,
                CONSTRAINT equipo_detalle_pk PRIMARY KEY (equipo_id, trabajadores_id)
);


CREATE SEQUENCE inspectores_id_inspectores_seq;

CREATE TABLE inspectores (
                id_inspectores INTEGER DEFAULT nextval('inspectores_id_inspectores_seq'::regclass) NOT NULL DEFAULT nextval('inspectores_id_inspectores_seq'),
                id_personal INTEGER NOT NULL,
                inspector_estado VARCHAR(30) NOT NULL,
                id_usuario INTEGER NOT NULL,
                CONSTRAINT inspectores_pk PRIMARY KEY (id_inspectores)
);


ALTER SEQUENCE inspectores_id_inspectores_seq OWNED BY inspectores.id_inspectores;

CREATE SEQUENCE control_produccion_control_id_seq;

CREATE TABLE control_produccion (
                control_id INTEGER DEFAULT nextval('control_produccion_control_id_seq'::regclass) NOT NULL DEFAULT nextval('control_produccion_control_id_seq'),
                control_fecha DATE NOT NULL,
                control_estado VARCHAR(30) NOT NULL,
                id_inspectores INTEGER NOT NULL,
                orden_id INTEGER NOT NULL,
                id_usuario INTEGER NOT NULL,
                CONSTRAINT control_produccion_pk PRIMARY KEY (control_id)
);


ALTER SEQUENCE control_produccion_control_id_seq OWNED BY control_produccion.control_id;

CREATE TABLE control_produccion_detalle (
                control_id INTEGER NOT NULL,
                producto_id INTEGER NOT NULL,
                control_cantidad INTEGER NOT NULL,
                control_descri VARCHAR(30) NOT NULL,
                CONSTRAINT control_produccion_detalle_pk PRIMARY KEY (control_id, producto_id)
);


CREATE SEQUENCE control_calidad_produccion_calidad_id_seq;

CREATE TABLE control_calidad_produccion (
                calidad_id INTEGER DEFAULT nextval('control_calidad_produccion_calidad_id_seq'::regclass) NOT NULL DEFAULT nextval('control_calidad_produccion_calidad_id_seq'),
                calidad_fecha DATE NOT NULL,
                calidad_estado VARCHAR(30) NOT NULL,
                id_inspectores INTEGER NOT NULL,
                id_usuario INTEGER NOT NULL,
                terminado_id INTEGER NOT NULL,
                CONSTRAINT control_calidad_produccion_pk PRIMARY KEY (calidad_id)
);


ALTER SEQUENCE control_calidad_produccion_calidad_id_seq OWNED BY control_calidad_produccion.calidad_id;

CREATE SEQUENCE perdidas_perdidas_id_seq;

CREATE TABLE perdidas (
                perdidas_id INTEGER DEFAULT nextval('perdidas_perdidas_id_seq'::regclass) NOT NULL DEFAULT nextval('perdidas_perdidas_id_seq'),
                perdida_estado VARCHAR(30) NOT NULL,
                perdida_fecha DATE NOT NULL,
                tipo_perdida_id INTEGER NOT NULL,
                calidad_id INTEGER NOT NULL,
                control_id INTEGER NOT NULL,
                id_usuario INTEGER NOT NULL,
                CONSTRAINT perdidas_pk PRIMARY KEY (perdidas_id)
);


ALTER SEQUENCE perdidas_perdidas_id_seq OWNED BY perdidas.perdidas_id;

CREATE TABLE perdidas_detalle (
                perdidas_id INTEGER NOT NULL,
                producto_id INTEGER NOT NULL,
                perdida_cantidad INTEGER NOT NULL,
                perdida_motivo VARCHAR(30) NOT NULL,
                CONSTRAINT perdidas_detalle_pk PRIMARY KEY (perdidas_id, producto_id)
);


CREATE TABLE control_calidad_detalle (
                calidad_id INTEGER NOT NULL,
                producto_id INTEGER NOT NULL,
                calidad_estado VARCHAR(30) NOT NULL,
                calidad_cantidad INTEGER NOT NULL,
                parametro_id INTEGER NOT NULL,
                CONSTRAINT control_calidad_detalle_pk PRIMARY KEY (calidad_id, producto_id)
);


CREATE SEQUENCE bitacora_id_bitacora_seq;

CREATE TABLE bitacora (
                id_bitacora INTEGER DEFAULT nextval('bitacora_id_bitacora_seq'::regclass) NOT NULL DEFAULT nextval('bitacora_id_bitacora_seq'),
                id_usuario INTEGER NOT NULL,
                entidad_afectada VARCHAR(50) NOT NULL,
                id_registro INTEGER,
                accion_realizada VARCHAR(30) NOT NULL,
                descripcion_cambio TEXT,
                fecha_accion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ip_origen VARCHAR(45),
                navegador_usuario VARCHAR(200),
                estado_registro VARCHAR(10) DEFAULT 'ACTIVO'::character varying,
                CONSTRAINT bitacora_pkey PRIMARY KEY (id_bitacora)
);


ALTER SEQUENCE bitacora_id_bitacora_seq OWNED BY bitacora.id_bitacora;

CREATE SEQUENCE ajustes_id_ajuste_seq;

CREATE TABLE ajustes (
                id_ajuste INTEGER DEFAULT nextval('ajuste_stock_id_ajuste_seq'::regclass) NOT NULL DEFAULT nextval('ajustes_id_ajuste_seq'),
                ajuste_fecha DATE NOT NULL,
                ajuste_estado VARCHAR(20) NOT NULL,
                id_usuario INTEGER NOT NULL,
                deposito_id INTEGER NOT NULL,
                CONSTRAINT ajustes_pk PRIMARY KEY (id_ajuste)
);


ALTER SEQUENCE ajustes_id_ajuste_seq OWNED BY ajustes.id_ajuste;

CREATE TABLE ajustes_detalle (
                id_materia_prima INTEGER NOT NULL,
                id_ajuste INTEGER NOT NULL,
                id_stock INTEGER NOT NULL,
                ajuste_cantidad INTEGER NOT NULL,
                id_motivo INTEGER NOT NULL,
                CONSTRAINT ajustes_detalle_pk PRIMARY KEY (id_materia_prima, id_ajuste, id_stock)
);


ALTER TABLE personal ADD CONSTRAINT cargos_empleados_fk
FOREIGN KEY (id_cargo)
REFERENCES cargos (id_cargo)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE usuarios ADD CONSTRAINT usuarios_id_cargo_fk
FOREIGN KEY (id_cargo)
REFERENCES cargos (id_cargo)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE inspectores ADD CONSTRAINT personal_inspectores_fk
FOREIGN KEY (id_personal)
REFERENCES personal (id_personal)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE trabajadores ADD CONSTRAINT personal_trabajadores_fk
FOREIGN KEY (id_personal)
REFERENCES personal (id_personal)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE usuarios ADD CONSTRAINT usuarios_id_personal_fk
FOREIGN KEY (id_personal)
REFERENCES personal (id_personal)
ON DELETE SET NULL
ON UPDATE CASCADE
NOT DEFERRABLE;

ALTER TABLE ajustes ADD CONSTRAINT usuarios_ajustes_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE bitacora ADD CONSTRAINT fk_bitacora_usuario
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE SET NULL
ON UPDATE CASCADE
NOT DEFERRABLE;

ALTER TABLE cargos ADD CONSTRAINT usuarios_cargos_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE conductores ADD CONSTRAINT usuarios_conductores_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE control_calidad_produccion ADD CONSTRAINT usuarios_control_calidad_produccion_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE control_produccion ADD CONSTRAINT usuarios_control_produccion_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE costo_produccion ADD CONSTRAINT usuarios_costo_produccion_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cuentas_pagar ADD CONSTRAINT usuarios_cuentas_pagar_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE deposito ADD CONSTRAINT usuarios_deposito_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE equipos_produccion ADD CONSTRAINT usuarios_equipos_produccion_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE etapa_produccion ADD CONSTRAINT usuarios_etapa_produccion_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE factura_compra ADD CONSTRAINT usuarios_factura_compra_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE inspectores ADD CONSTRAINT usuarios_inspectores_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE materia_prima ADD CONSTRAINT usuarios_materia_prima_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE modulos ADD CONSTRAINT usuarios_modulos_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE motivo ADD CONSTRAINT usuarios_motivo_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_compra ADD CONSTRAINT usuarios_nota_credi_compra_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_remision_compra ADD CONSTRAINT usuarios_nota_remision_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE orden_de_compra ADD CONSTRAINT usuarios_orden_de_compra_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE orden_produccion ADD CONSTRAINT usuarios_orden_produccion_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE parametros_control ADD CONSTRAINT usuarios_parametros_control_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedido_materia_produccion ADD CONSTRAINT usuarios_pedido_materia_produccion_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedidos_compra ADD CONSTRAINT usuarios_pedidos_compra_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE perdidas ADD CONSTRAINT usuarios_perdidas_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE personal ADD CONSTRAINT usuarios_personal_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE presupuesto_compra ADD CONSTRAINT usuarios_presupuesto_compra_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE producto_terminado ADD CONSTRAINT usuarios_producto_terminado_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE productos ADD CONSTRAINT usuarios_productos_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE proveedor ADD CONSTRAINT usuarios_proveedor_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE reposicion_materia ADD CONSTRAINT usuarios_reposicion_materia_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE stock_materia_prima ADD CONSTRAINT usuarios_stock_materia_prima_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE stock_producto ADD CONSTRAINT usuarios_stock_producto_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE sucursales ADD CONSTRAINT usuarios_sucursales_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE tipo_iva ADD CONSTRAINT usuarios_tipo_iva_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE tipo_perdida ADD CONSTRAINT usuarios_tipo_perdida_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE trabajadores ADD CONSTRAINT usuarios_trabajadores_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE unidad_medida ADD CONSTRAINT usuarios_categoria_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE vehiculos ADD CONSTRAINT usuarios_vehiculos_fk
FOREIGN KEY (id_usuario)
REFERENCES usuarios (id_usuario)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_remision_compra ADD CONSTRAINT vehiculos_nota_remision_compra_fk
FOREIGN KEY (vehiculo_id)
REFERENCES vehiculos (vehiculo_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE materia_prima ADD CONSTRAINT categoria_articulos_fk
FOREIGN KEY (id_unidad)
REFERENCES unidad_medida (id_unidad)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE productos ADD CONSTRAINT unidad_medida_productos_fk
FOREIGN KEY (id_unidad)
REFERENCES unidad_medida (id_unidad)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE perdidas ADD CONSTRAINT tipo_perdida_perdidas_fk
FOREIGN KEY (tipo_perdida_id)
REFERENCES tipo_perdida (tipo_perdida_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE materia_prima ADD CONSTRAINT tipo_iva_materia_prima_fk
FOREIGN KEY (iva_id)
REFERENCES tipo_iva (iva_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE productos ADD CONSTRAINT productos_tipo_iva_fk
FOREIGN KEY (iva_id)
REFERENCES tipo_iva (iva_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cuentas_pagar ADD CONSTRAINT sucursales_cuentas_pagar_fk
FOREIGN KEY (id_sucursal)
REFERENCES sucursales (id_sucursal)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE factura_compra ADD CONSTRAINT sucursales_factura_compra_fk
FOREIGN KEY (id_sucursal)
REFERENCES sucursales (id_sucursal)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_compra ADD CONSTRAINT sucursales_nota_credi_compra_fk
FOREIGN KEY (id_sucursal)
REFERENCES sucursales (id_sucursal)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE orden_de_compra ADD CONSTRAINT sucursales_orden_de_compra_fk
FOREIGN KEY (id_sucursal)
REFERENCES sucursales (id_sucursal)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedidos_compra ADD CONSTRAINT sucursales_pedidos_compra_fk
FOREIGN KEY (id_sucursal)
REFERENCES sucursales (id_sucursal)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE personal ADD CONSTRAINT fk_personal_sucursal
FOREIGN KEY (id_sucursal)
REFERENCES sucursales (id_sucursal)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE presupuesto_compra ADD CONSTRAINT sucursales_presupuesto_compra_fk
FOREIGN KEY (id_sucursal)
REFERENCES sucursales (id_sucursal)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE usuarios ADD CONSTRAINT sucursales_usuarios_fk
FOREIGN KEY (id_sucursal)
REFERENCES sucursales (id_sucursal)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cuentas_pagar ADD CONSTRAINT proveedor_cuentas_pagar_fk
FOREIGN KEY (id_proveedor)
REFERENCES proveedor (id_proveedor)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_compra ADD CONSTRAINT proveedor_nota_compra_fk
FOREIGN KEY (id_proveedor)
REFERENCES proveedor (id_proveedor)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_remision_compra ADD CONSTRAINT proveedor_nota_remision_fk
FOREIGN KEY (id_proveedor)
REFERENCES proveedor (id_proveedor)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE orden_de_compra ADD CONSTRAINT proveedor_orden_de_compra_fk
FOREIGN KEY (id_proveedor)
REFERENCES proveedor (id_proveedor)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE presupuesto_compra ADD CONSTRAINT proveedor_presupuesto_compra_fk
FOREIGN KEY (id_proveedor)
REFERENCES proveedor (id_proveedor)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE control_calidad_detalle ADD CONSTRAINT productos_control_calidad_detalle_fk
FOREIGN KEY (producto_id)
REFERENCES productos (producto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE control_produccion_detalle ADD CONSTRAINT productos_control_produccion_detalle_fk
FOREIGN KEY (producto_id)
REFERENCES productos (producto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE etapa_detalle_produccion ADD CONSTRAINT productos_etapa_detalle_produccion_fk
FOREIGN KEY (producto_id)
REFERENCES productos (producto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE orden_detalle_produccion ADD CONSTRAINT productos_orden_detalle_produccion_fk
FOREIGN KEY (producto_id)
REFERENCES productos (producto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE parametros_control ADD CONSTRAINT productos_parametros_control_fk
FOREIGN KEY (producto_id)
REFERENCES productos (producto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE perdidas_detalle ADD CONSTRAINT productos_perdidas_detalle_fk
FOREIGN KEY (producto_id)
REFERENCES productos (producto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE productos_terminados_detalle ADD CONSTRAINT productos_productos_terminados_detalle_fk
FOREIGN KEY (producto_id)
REFERENCES productos (producto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE stock_producto ADD CONSTRAINT productos_stock_producto_fk
FOREIGN KEY (producto_id)
REFERENCES productos (producto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE costo_produccion ADD CONSTRAINT pedidos_compra_costo_produccion_fk
FOREIGN KEY (id_pedido_compra)
REFERENCES pedidos_compra (id_pedido_compra)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedido_detalle_compra ADD CONSTRAINT pedidos_compra_pedido_detalle_compra_fk
FOREIGN KEY (id_pedido_compra)
REFERENCES pedidos_compra (id_pedido_compra)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE presupuesto_compra ADD CONSTRAINT pedidos_compra_presupuesto_compra_fk
FOREIGN KEY (id_pedido_compra)
REFERENCES pedidos_compra (id_pedido_compra)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE reposicion_materia ADD CONSTRAINT pedidos_compra_reposicion_materia_fk
FOREIGN KEY (id_pedido_compra)
REFERENCES pedidos_compra (id_pedido_compra)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE orden_de_compra ADD CONSTRAINT presupuesto_compra_orden_de_compra_fk
FOREIGN KEY (id_presupuesto_compra)
REFERENCES presupuesto_compra (id_presupuesto_compra)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedido_materia_detalle_produccion ADD CONSTRAINT pedido_materia_produccion_pedido_materia_detalle_produccion_fk
FOREIGN KEY (id_pedido_mat_prod)
REFERENCES pedido_materia_produccion (id_pedido_mat_prod)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE control_calidad_detalle ADD CONSTRAINT parametros_control_control_calidad_detalle_fk
FOREIGN KEY (parametro_id)
REFERENCES parametros_control (parametro_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE control_produccion ADD CONSTRAINT orden_produccion_control_produccion_fk
FOREIGN KEY (orden_id)
REFERENCES orden_produccion (orden_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE orden_detalle_produccion ADD CONSTRAINT orden_produccion_orden_detalle_produccion_fk
FOREIGN KEY (orden_id)
REFERENCES orden_produccion (orden_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE producto_terminado ADD CONSTRAINT orden_produccion_producto_terminado_fk
FOREIGN KEY (orden_id)
REFERENCES orden_produccion (orden_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE control_calidad_produccion ADD CONSTRAINT producto_terminado_control_calidad_produccion_fk
FOREIGN KEY (terminado_id)
REFERENCES producto_terminado (terminado_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE productos_terminados_detalle ADD CONSTRAINT producto_terminado_productos_terminados_detalle_fk
FOREIGN KEY (terminado_id)
REFERENCES producto_terminado (terminado_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE factura_compra ADD CONSTRAINT orden_de_compra_factura_compra_fk
FOREIGN KEY (id_orden_compra)
REFERENCES orden_de_compra (id_orden_compra)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_remision_compra ADD CONSTRAINT nota_remision_compra_orden_compra_fk
FOREIGN KEY (id_orden_compra)
REFERENCES orden_de_compra (id_orden_compra)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE orden_detalle_compra ADD CONSTRAINT orden_de_compra_detalle_orden_compra_fk
FOREIGN KEY (id_orden_compra)
REFERENCES orden_de_compra (id_orden_compra)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE ajustes_detalle ADD CONSTRAINT motivo_detalle_ajuste_fk
FOREIGN KEY (id_motivo)
REFERENCES motivo (id_motivo)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_compra ADD CONSTRAINT motivo_nota_compra_fk
FOREIGN KEY (id_motivo)
REFERENCES motivo (id_motivo)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE usuarios ADD CONSTRAINT modulos_usuarios_fk
FOREIGN KEY (modulo_id)
REFERENCES modulos (modulo_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE ajustes_detalle ADD CONSTRAINT articulos_detalle_ajuste_fk
FOREIGN KEY (id_materia_prima)
REFERENCES materia_prima (id_materia_prima)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE costo_detalle_produccion ADD CONSTRAINT materia_prima_costo_detalle_produccion_fk
FOREIGN KEY (id_materia_prima)
REFERENCES materia_prima (id_materia_prima)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE factura_detalle_compra ADD CONSTRAINT articulos_detalle_registro_compra_fk
FOREIGN KEY (id_materia_prima)
REFERENCES materia_prima (id_materia_prima)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_detalle_compra ADD CONSTRAINT articulos_detalle_credito_compra_fk
FOREIGN KEY (id_materia_prima)
REFERENCES materia_prima (id_materia_prima)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_remision_detalle_compra ADD CONSTRAINT articulos_detalle_nota_remision_fk
FOREIGN KEY (id_materia_prima)
REFERENCES materia_prima (id_materia_prima)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE orden_detalle_compra ADD CONSTRAINT articulos_detalle_orden_compra_fk
FOREIGN KEY (id_materia_prima)
REFERENCES materia_prima (id_materia_prima)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedido_detalle_compra ADD CONSTRAINT materia_prima_pedido_detalle_compra_fk
FOREIGN KEY (id_materia_prima)
REFERENCES materia_prima (id_materia_prima)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedido_materia_detalle_produccion ADD CONSTRAINT materia_prima_pedido_materia_detalle_produccion_fk
FOREIGN KEY (id_materia_prima)
REFERENCES materia_prima (id_materia_prima)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE presupuesto_detalle_compra ADD CONSTRAINT articulos_detalle_presu_compra_fk
FOREIGN KEY (id_materia_prima)
REFERENCES materia_prima (id_materia_prima)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE reposicion_materia_detalle ADD CONSTRAINT materia_prima_reposicion_materia_detalle_fk
FOREIGN KEY (id_materia_prima)
REFERENCES materia_prima (id_materia_prima)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE stock_materia_prima ADD CONSTRAINT articulos_stock_detalle_fk
FOREIGN KEY (id_materia_prima)
REFERENCES materia_prima (id_materia_prima)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cuentas_pagar ADD CONSTRAINT factura_compra_cuentas_pagar_fk
FOREIGN KEY (id_factura_compra)
REFERENCES factura_compra (id_factura_compra)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE factura_detalle_compra ADD CONSTRAINT factura_compra_detalle_registro_compra_fk
FOREIGN KEY (id_factura_compra)
REFERENCES factura_compra (id_factura_compra)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE iva_compra ADD CONSTRAINT factura_compra_iva_compra_fk
FOREIGN KEY (id_factura_compra)
REFERENCES factura_compra (id_factura_compra)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_compra ADD CONSTRAINT nota_compra_id_factura_compra_fkey
FOREIGN KEY (id_factura_compra)
REFERENCES factura_compra (id_factura_compra)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_remision_compra ADD CONSTRAINT factura_compra_nota_remision_compra_fk
FOREIGN KEY (id_factura_compra)
REFERENCES factura_compra (id_factura_compra)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_detalle_compra ADD CONSTRAINT nota_credi_compra_detalle_credito_compra_fk
FOREIGN KEY (id_nota_compra)
REFERENCES nota_compra (id_nota_compra)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE etapa_detalle_produccion ADD CONSTRAINT etapa_produccion_etapa_detalle_produccion_fk
FOREIGN KEY (etapa_id)
REFERENCES etapa_produccion (etapa_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE equipo_detalle ADD CONSTRAINT equipos_produccion_equipo_detalle_fk
FOREIGN KEY (equipo_id)
REFERENCES equipos_produccion (equipo_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE orden_detalle_produccion ADD CONSTRAINT equipos_produccion_orden_detalle_produccion_fk
FOREIGN KEY (equipo_id)
REFERENCES equipos_produccion (equipo_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE ajustes ADD CONSTRAINT deposito_ajustes_fk
FOREIGN KEY (deposito_id)
REFERENCES deposito (deposito_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_remision_compra ADD CONSTRAINT deposito_nota_remision_compra_fk
FOREIGN KEY (deposito_id)
REFERENCES deposito (deposito_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE reposicion_materia ADD CONSTRAINT deposito_reposicion_materia_fk
FOREIGN KEY (deposito_id)
REFERENCES deposito (deposito_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE stock_materia_prima ADD CONSTRAINT deposito_stock_materia_prima_fk
FOREIGN KEY (deposito_id)
REFERENCES deposito (deposito_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE stock_producto ADD CONSTRAINT deposito_stock_producto_fk
FOREIGN KEY (deposito_id)
REFERENCES deposito (deposito_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE ajustes_detalle ADD CONSTRAINT stock_materia_prima_ajustes_detallle_fk
FOREIGN KEY (id_stock)
REFERENCES stock_materia_prima (id_stock)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE reposicion_materia_detalle ADD CONSTRAINT reposicion_materia_reposicion_materia_detalle_fk
FOREIGN KEY (reposicion_id)
REFERENCES reposicion_materia (reposicion_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE costo_detalle_produccion ADD CONSTRAINT costo_produccion_costo_detalle_produccion_fk
FOREIGN KEY (costo_id)
REFERENCES costo_produccion (costo_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_remision_compra ADD CONSTRAINT conductores_nota_remision_compra_fk
FOREIGN KEY (conductor_id)
REFERENCES conductores (conductor_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_remision_detalle_compra ADD CONSTRAINT nota_remision_detalle_nota_remision_fk
FOREIGN KEY (id_nota_remision)
REFERENCES nota_remision_compra (id_nota_remision)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE equipo_detalle ADD CONSTRAINT trabajadores_equipo_detalle_fk
FOREIGN KEY (trabajadores_id)
REFERENCES trabajadores (trabajadores_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE control_calidad_produccion ADD CONSTRAINT inspectores_control_calidad_produccion_fk
FOREIGN KEY (id_inspectores)
REFERENCES inspectores (id_inspectores)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE control_produccion ADD CONSTRAINT inspectores_control_produccion_fk
FOREIGN KEY (id_inspectores)
REFERENCES inspectores (id_inspectores)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE control_produccion_detalle ADD CONSTRAINT control_produccion_control_produccion_detalle_fk
FOREIGN KEY (control_id)
REFERENCES control_produccion (control_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE perdidas ADD CONSTRAINT control_produccion_perdidas_fk
FOREIGN KEY (control_id)
REFERENCES control_produccion (control_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE control_calidad_detalle ADD CONSTRAINT control_calidad_produccion_control_calidad_detalle_fk
FOREIGN KEY (calidad_id)
REFERENCES control_calidad_produccion (calidad_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE perdidas ADD CONSTRAINT control_calidad_produccion_perdidas_fk
FOREIGN KEY (calidad_id)
REFERENCES control_calidad_produccion (calidad_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE perdidas_detalle ADD CONSTRAINT perdidas_perdidas_detalle_fk
FOREIGN KEY (perdidas_id)
REFERENCES perdidas (perdidas_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE ajustes_detalle ADD CONSTRAINT ajuste_stock_detalle_ajuste_fk
FOREIGN KEY (id_ajuste)
REFERENCES ajustes (id_ajuste)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;