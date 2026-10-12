
CREATE TABLE turno (
                turno_id INTEGER NOT NULL,
                turno_descripcion VARCHAR NOT NULL,
                turno_estado VARCHAR NOT NULL,
                CONSTRAINT turno_pk PRIMARY KEY (turno_id)
);


CREATE TABLE rol (
                rol_id INTEGER NOT NULL,
                rol_descripcion VARCHAR NOT NULL,
                rol_estado VARCHAR NOT NULL,
                CONSTRAINT rol_pk PRIMARY KEY (rol_id)
);


CREATE TABLE tipo_pago (
                tipo_pago_id INTEGER NOT NULL,
                tipo_pago_descripcion VARCHAR NOT NULL,
                tipo_pago_estado VARCHAR NOT NULL,
                CONSTRAINT tipo_pago_pk PRIMARY KEY (tipo_pago_id)
);


CREATE TABLE motivo_merma (
                motivo_merma_id INTEGER NOT NULL,
                motivo_descripcion VARCHAR NOT NULL,
                motivo_estado VARCHAR NOT NULL,
                CONSTRAINT motivo_merma_pk PRIMARY KEY (motivo_merma_id)
);


CREATE TABLE tipo_producto (
                tipo_producto_id INTEGER NOT NULL,
                tipo_descripcion VARCHAR NOT NULL,
                CONSTRAINT tipo_producto_pk PRIMARY KEY (tipo_producto_id)
);


CREATE TABLE motivo_ajuste (
                motivo_id INTEGER NOT NULL,
                motivo_descripcion VARCHAR NOT NULL,
                motivo_estado VARCHAR NOT NULL,
                CONSTRAINT motivo_ajuste_pk PRIMARY KEY (motivo_id)
);


CREATE TABLE marca (
                marca_id INTEGER NOT NULL,
                marca_descrip VARCHAR NOT NULL,
                CONSTRAINT marca_pk PRIMARY KEY (marca_id)
);


CREATE SEQUENCE tipo_presupuesto_tipo_presupuesto_id_seq;

CREATE TABLE tipo_presupuesto (
                tipo_presupuesto_id INTEGER NOT NULL DEFAULT nextval('tipo_presupuesto_tipo_presupuesto_id_seq'),
                tipo_presupuesto_desc VARCHAR(30) NOT NULL,
                tipo_presupuesto_estado VARCHAR NOT NULL,
                CONSTRAINT tipo_presupuesto_pk PRIMARY KEY (tipo_presupuesto_id)
);


ALTER SEQUENCE tipo_presupuesto_tipo_presupuesto_id_seq OWNED BY tipo_presupuesto.tipo_presupuesto_id;

CREATE SEQUENCE personal_personal_id_seq;

CREATE TABLE personal (
                personal_id INTEGER NOT NULL DEFAULT nextval('personal_personal_id_seq'),
                personal_nombre VARCHAR(30) NOT NULL,
                personal_apellido VARCHAR(30) NOT NULL,
                personal_ci VARCHAR(30) NOT NULL,
                personal_direccion VARCHAR NOT NULL,
                personal_telefono VARCHAR(30) NOT NULL,
                personal_estado VARCHAR(30) NOT NULL,
                usuario_id INTEGER,
                CONSTRAINT personal_pk PRIMARY KEY (personal_id)
);


ALTER SEQUENCE personal_personal_id_seq OWNED BY personal.personal_id;

CREATE SEQUENCE usuario_usuario_id_seq;

CREATE TABLE usuario (
                usuario_id INTEGER NOT NULL DEFAULT nextval('usuario_usuario_id_seq'),
                username VARCHAR(30) NOT NULL,
                personal_id INTEGER,
                modulo_id INTEGER NOT NULL,
                usuario_contrasena VARCHAR(100) NOT NULL,
                usuario_estado VARCHAR(30) NOT NULL,
                cargo_id INTEGER NOT NULL,
                CONSTRAINT usuario_pk PRIMARY KEY (usuario_id)
);


ALTER SEQUENCE usuario_usuario_id_seq OWNED BY usuario.usuario_id;

CREATE SEQUENCE etapa_produccion_etapa_id_seq;

CREATE TABLE etapa_produccion (
                etapa_id INTEGER NOT NULL DEFAULT nextval('etapa_produccion_etapa_id_seq'),
                usuario_id INTEGER NOT NULL,
                etapa_fecha DATE NOT NULL,
                etapa_estado VARCHAR NOT NULL,
                CONSTRAINT etapa_produccion_pk PRIMARY KEY (etapa_id)
);


ALTER SEQUENCE etapa_produccion_etapa_id_seq OWNED BY etapa_produccion.etapa_id;

CREATE SEQUENCE tipo_iva_tipo_iva_id_seq;

CREATE TABLE tipo_iva (
                tipo_iva_id INTEGER NOT NULL DEFAULT nextval('tipo_iva_tipo_iva_id_seq'),
                tipo_iva_nombre VARCHAR NOT NULL,
                usuario_id INTEGER NOT NULL,
                tipo_iva_estado VARCHAR NOT NULL,
                CONSTRAINT tipo_iva_pk PRIMARY KEY (tipo_iva_id)
);


ALTER SEQUENCE tipo_iva_tipo_iva_id_seq OWNED BY tipo_iva.tipo_iva_id;

CREATE SEQUENCE banco_id_banco_seq;

CREATE TABLE banco (
                id_banco INTEGER NOT NULL DEFAULT nextval('banco_id_banco_seq'),
                banco_descri VARCHAR(30) NOT NULL,
                usuario_id INTEGER NOT NULL,
                CONSTRAINT banco_pk PRIMARY KEY (id_banco)
);


ALTER SEQUENCE banco_id_banco_seq OWNED BY banco.id_banco;

CREATE SEQUENCE cajas_id_caja_seq;

CREATE TABLE cajas (
                id_caja INTEGER NOT NULL DEFAULT nextval('cajas_id_caja_seq'),
                numero_expedicion VARCHAR(30) NOT NULL,
                caja_descri VARCHAR(30) NOT NULL,
                caja_estado VARCHAR(30) NOT NULL,
                usuario_id INTEGER NOT NULL,
                CONSTRAINT cajas_pk PRIMARY KEY (id_caja)
);


ALTER SEQUENCE cajas_id_caja_seq OWNED BY cajas.id_caja;

CREATE SEQUENCE deposito_deposito_id_seq;

CREATE TABLE deposito (
                deposito_id INTEGER NOT NULL DEFAULT nextval('deposito_deposito_id_seq'),
                deposito_nombre VARCHAR(30) NOT NULL,
                deposito_estado VARCHAR NOT NULL,
                usuario_id INTEGER NOT NULL,
                CONSTRAINT deposito_pk PRIMARY KEY (deposito_id)
);


ALTER SEQUENCE deposito_deposito_id_seq OWNED BY deposito.deposito_id;

CREATE SEQUENCE cajeros_cajero_id_seq;

CREATE TABLE cajeros (
                cajero_id INTEGER NOT NULL DEFAULT nextval('cajeros_cajero_id_seq'),
                cajero_estado VARCHAR(30) NOT NULL,
                usuario_id INTEGER NOT NULL,
                turno VARCHAR NOT NULL,
                CONSTRAINT cajeros_pk PRIMARY KEY (cajero_id)
);


ALTER SEQUENCE cajeros_cajero_id_seq OWNED BY cajeros.cajero_id;

CREATE SEQUENCE trabajadores_trabajadores_id_seq;

CREATE TABLE trabajadores (
                trabajadores_id INTEGER NOT NULL DEFAULT nextval('trabajadores_trabajadores_id_seq'),
                usuario_id INTEGER NOT NULL,
                personal_id INTEGER NOT NULL,
                trabajador_estado VARCHAR(30) NOT NULL,
                rol_id INTEGER NOT NULL,
                turno_id INTEGER NOT NULL,
                trabajador_costo_hora INTEGER NOT NULL DEFAULT 0,
                CONSTRAINT trabajadores_costo_hora_ck CHECK (trabajador_costo_hora >= 0),
                CONSTRAINT trabajadores_pk PRIMARY KEY (trabajadores_id)
);


ALTER SEQUENCE trabajadores_trabajadores_id_seq OWNED BY trabajadores.trabajadores_id;

CREATE SEQUENCE inspectores_id_inspectores_seq;

CREATE TABLE inspectores (
                id_inspectores INTEGER NOT NULL DEFAULT nextval('inspectores_id_inspectores_seq'),
                inspector_estado VARCHAR(30) NOT NULL,
                usuario_id INTEGER NOT NULL,
                trabajadores_id INTEGER NOT NULL,
                CONSTRAINT inspectores_pk PRIMARY KEY (id_inspectores)
);


ALTER SEQUENCE inspectores_id_inspectores_seq OWNED BY inspectores.id_inspectores;

CREATE SEQUENCE modulos_modulo_id_seq;

CREATE TABLE modulos (
                modulo_id INTEGER NOT NULL DEFAULT nextval('modulos_modulo_id_seq'),
                modulo_nombre VARCHAR(30) NOT NULL,
                modulo_estado VARCHAR NOT NULL,
                usuario_id INTEGER,
                CONSTRAINT modulos_pk PRIMARY KEY (modulo_id)
);


ALTER SEQUENCE modulos_modulo_id_seq OWNED BY modulos.modulo_id;

CREATE SEQUENCE clientes_cliente_id_seq;

CREATE TABLE clientes (
                cliente_id INTEGER NOT NULL DEFAULT nextval('clientes_cliente_id_seq'),
                cliente_nombre VARCHAR(30) NOT NULL,
                cliente_documento VARCHAR(30) NOT NULL,
                cliente_telefono VARCHAR NOT NULL,
                cliente_direccion VARCHAR NOT NULL,
                cliente_estado VARCHAR(30) NOT NULL,
                usuario_id INTEGER NOT NULL,
                CONSTRAINT clientes_pk PRIMARY KEY (cliente_id)
);


ALTER SEQUENCE clientes_cliente_id_seq OWNED BY clientes.cliente_id;

CREATE SEQUENCE proveedor_prov_id_seq;

CREATE TABLE proveedor (
                prov_id INTEGER NOT NULL DEFAULT nextval('proveedor_prov_id_seq'),
                prov_razon_social VARCHAR NOT NULL,
                prov_ruc VARCHAR NOT NULL,
                prov_telefono VARCHAR NOT NULL,
                prov_direccion VARCHAR NOT NULL,
                prov_email VARCHAR NOT NULL,
                prov_estado VARCHAR(20) NOT NULL,
                usuario_id INTEGER NOT NULL,
                CONSTRAINT proveedor_pk PRIMARY KEY (prov_id)
);


ALTER SEQUENCE proveedor_prov_id_seq OWNED BY proveedor.prov_id;

CREATE SEQUENCE timbrado_id_timbrado_seq;

CREATE TABLE timbrado (
                id_timbrado INTEGER NOT NULL DEFAULT nextval('timbrado_id_timbrado_seq'),
                timbrado_numero INTEGER NOT NULL,
                timbrado_estado VARCHAR(30) NOT NULL,
                timbrado_firma VARCHAR NOT NULL,
                firma_vencimiento DATE NOT NULL,
                usuario_id INTEGER NOT NULL,
                CONSTRAINT timbrado_pk PRIMARY KEY (id_timbrado)
);


ALTER SEQUENCE timbrado_id_timbrado_seq OWNED BY timbrado.id_timbrado;

CREATE SEQUENCE cargo_cargo_id_seq;

CREATE TABLE cargo (
                cargo_id INTEGER NOT NULL DEFAULT nextval('cargo_cargo_id_seq'),
                descrip_cargo VARCHAR(30) NOT NULL,
                estado_cargo VARCHAR(30) NOT NULL,
                usuario_id INTEGER,
                CONSTRAINT cargo_pk PRIMARY KEY (cargo_id)
);


ALTER SEQUENCE cargo_cargo_id_seq OWNED BY cargo.cargo_id;

CREATE SEQUENCE ajuste_stock_ajuste_id_seq;

CREATE TABLE ajustes (
                ajuste_id INTEGER NOT NULL DEFAULT nextval('ajuste_stock_ajuste_id_seq'),
                ajuste_nro VARCHAR NOT NULL,
                ajuste_fecha DATE NOT NULL,
                ajuste_estado VARCHAR(20) NOT NULL,
                ajuste_obs VARCHAR NOT NULL,
                usuario_id INTEGER NOT NULL,
                deposito_id INTEGER NOT NULL,
                motivo_id INTEGER NOT NULL,
                CONSTRAINT ajustes_pk PRIMARY KEY (ajuste_id)
);


ALTER SEQUENCE ajuste_stock_ajuste_id_seq OWNED BY ajustes.ajuste_id;

CREATE SEQUENCE presupuesto_venta_presu_id_seq;

CREATE TABLE presupuesto_venta (
                presu_id INTEGER NOT NULL DEFAULT nextval('presupuesto_venta_presu_id_seq'),
                presu_nro VARCHAR NOT NULL,
                presu_fecha DATE NOT NULL,
                presu_fecha_validez VARCHAR NOT NULL,
                presu_observacion VARCHAR(30) NOT NULL,
                presu_estado VARCHAR NOT NULL,
                presu_total NUMERIC NOT NULL,
                cliente_id INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                tipo_presupuesto_id INTEGER NOT NULL,
                CONSTRAINT presupuesto_venta_pk PRIMARY KEY (presu_id)
);


ALTER SEQUENCE presupuesto_venta_presu_id_seq OWNED BY presupuesto_venta.presu_id;

CREATE SEQUENCE pedidos_ventas_id_pedido_venta_seq;

CREATE TABLE pedidos_ventas (
                id_pedido_venta INTEGER NOT NULL DEFAULT nextval('pedidos_ventas_id_pedido_venta_seq'),
                pedido_nro VARCHAR NOT NULL,
                pedido_fecha DATE NOT NULL,
                pedido_observacion VARCHAR NOT NULL,
                pedido_estado VARCHAR(30) NOT NULL,
                pedido_total INTEGER NOT NULL,
                cliente_id INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                presu_id INTEGER NOT NULL,
                CONSTRAINT pedidos_ventas_pk PRIMARY KEY (id_pedido_venta)
);


ALTER SEQUENCE pedidos_ventas_id_pedido_venta_seq OWNED BY pedidos_ventas.id_pedido_venta;

CREATE TABLE pedido_produccion (
                pedido_id INTEGER NOT NULL,
                pedido_nro VARCHAR NOT NULL,
                pedido_fecha DATE NOT NULL,
                pedido_tipo VARCHAR NOT NULL,
                pedido_observacion VARCHAR NOT NULL,
                pedido_estado VARCHAR NOT NULL,
                id_pedido_venta INTEGER,
                usuario_id INTEGER NOT NULL,
                CONSTRAINT pedido_produccion_pk PRIMARY KEY (pedido_id)
);


CREATE SEQUENCE orden_produccion_orden_id_seq;

CREATE TABLE orden_produccion (
                orden_id INTEGER NOT NULL DEFAULT nextval('orden_produccion_orden_id_seq'),
                orden_nro VARCHAR NOT NULL,
                pedido_id INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                orden_fecha DATE NOT NULL,
                orden_fecha_entrega_prevista DATE,
                orden_observacion VARCHAR NOT NULL,
                orden_estado VARCHAR(30) NOT NULL,
                CONSTRAINT orden_produccion_pk PRIMARY KEY (orden_id)
);


ALTER SEQUENCE orden_produccion_orden_id_seq OWNED BY orden_produccion.orden_id;

CREATE TABLE produccion_terminada (
                produccion_terminada_id INTEGER NOT NULL,
                orden_id INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                fecha_elaboracion DATE NOT NULL,
                fecha_vencimiento DATE NOT NULL,
                observacion VARCHAR NOT NULL,
                estado VARCHAR NOT NULL,
                CONSTRAINT produccion_terminada_pk PRIMARY KEY (produccion_terminada_id)
);


CREATE SEQUENCE control_produccion_control_id_seq;

CREATE TABLE control_produccion (
                control_id INTEGER NOT NULL DEFAULT nextval('control_produccion_control_id_seq'),
                control_nro VARCHAR NOT NULL,
                orden_id INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                control_fecha DATE NOT NULL,
                control_estado VARCHAR(30) NOT NULL,
                CONSTRAINT control_produccion_pk PRIMARY KEY (control_id)
);


ALTER SEQUENCE control_produccion_control_id_seq OWNED BY control_produccion.control_id;

CREATE SEQUENCE pedido_materia_prima_pedido_mp_id_seq;

CREATE TABLE pedido_materia_prima (
                pedido_mp_id INTEGER NOT NULL DEFAULT nextval('pedido_materia_prima_pedido_mp_id_seq'),
                pedido_mp_nro VARCHAR NOT NULL,
                pedido_mp_fecha DATE NOT NULL,
                pedido_id INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                pedido_mp_observacion VARCHAR NOT NULL,
                pedido_mp_estado VARCHAR(30) NOT NULL,
                CONSTRAINT pedido_materia_prima_pk PRIMARY KEY (pedido_mp_id)
);


ALTER SEQUENCE pedido_materia_prima_pedido_mp_id_seq OWNED BY pedido_materia_prima.pedido_mp_id;

CREATE SEQUENCE pedido_compra_pedido_id_seq;

CREATE TABLE pedido_compra (
                pedido_id INTEGER NOT NULL DEFAULT nextval('pedido_compra_pedido_id_seq'),
                pedido_nro VARCHAR NOT NULL,
                pedido_fecha DATE NOT NULL,
                pedido_estado VARCHAR(20) NOT NULL,
                usuario_id INTEGER NOT NULL,
                pedido_observacion VARCHAR NOT NULL,
                pedido_mp_id INTEGER,
                CONSTRAINT pedido_compra_pk PRIMARY KEY (pedido_id)
);


ALTER SEQUENCE pedido_compra_pedido_id_seq OWNED BY pedido_compra.pedido_id;

CREATE SEQUENCE costos_produccion_costo_id_seq;

CREATE TABLE costos_produccion (
                costo_id INTEGER NOT NULL DEFAULT nextval('costos_produccion_costo_id_seq'),
                costo_nro VARCHAR NOT NULL,
                pedido_id INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                costo_fecha DATE NOT NULL,
                costo_cif_total INTEGER NOT NULL,
                costo_total_op INTEGER NOT NULL,
                costo_observacion INTEGER NOT NULL,
                costo_estado VARCHAR(30) NOT NULL,
                costo_total INTEGER NOT NULL,
                CONSTRAINT costos_produccion_pk PRIMARY KEY (costo_id)
);


ALTER SEQUENCE costos_produccion_costo_id_seq OWNED BY costos_produccion.costo_id;

CREATE SEQUENCE presupuesto_compra_presu_id_seq;

CREATE TABLE presupuesto_compra (
                presu_id INTEGER NOT NULL DEFAULT nextval('presupuesto_compra_presu_id_seq'),
                presu_nro VARCHAR NOT NULL,
                presu_fecha DATE NOT NULL,
                presu_estado VARCHAR(20) NOT NULL,
                presu_total INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                prov_id INTEGER NOT NULL,
                pedido_id INTEGER NOT NULL,
                CONSTRAINT presupuesto_compra_pk PRIMARY KEY (presu_id)
);


ALTER SEQUENCE presupuesto_compra_presu_id_seq OWNED BY presupuesto_compra.presu_id;

CREATE SEQUENCE orden_compra_oc_id_seq;

CREATE TABLE orden_compra (
                oc_id INTEGER NOT NULL DEFAULT nextval('orden_compra_oc_id_seq'),
                oc_nro VARCHAR NOT NULL,
                oc_fecha DATE NOT NULL,
                oc_estado VARCHAR(30) NOT NULL,
                usuario_id INTEGER NOT NULL,
                prov_id INTEGER NOT NULL,
                presu_id INTEGER NOT NULL,
                orden_total INTEGER NOT NULL,
                tipo_pago VARCHAR NOT NULL,
                oc_observacion VARCHAR NOT NULL,
                CONSTRAINT orden_compra_pk PRIMARY KEY (oc_id)
);


ALTER SEQUENCE orden_compra_oc_id_seq OWNED BY orden_compra.oc_id;

CREATE SEQUENCE factura_compra_fac_id_seq;

CREATE TABLE factura_compra (
                fac_id INTEGER NOT NULL DEFAULT nextval('factura_compra_fac_id_seq'),
                fac_nro VARCHAR(30) NOT NULL,
                fact_fecha DATE NOT NULL,
                fac_fecha_emision DATE NOT NULL,
                fac_timbrado VARCHAR(30) NOT NULL,
                fac_vigencia DATE NOT NULL,
                fac_estado VARCHAR(20) NOT NULL,
                tipo_pago VARCHAR NOT NULL,
                fac_total INTEGER NOT NULL,
                fac_cantidad_cuotas INTEGER NOT NULL,
                fac_remision DOUBLE PRECISION NOT NULL,
                usuario_id INTEGER NOT NULL,
                oc_id INTEGER NOT NULL,
                CONSTRAINT factura_compra_pk PRIMARY KEY (fac_id)
);


ALTER SEQUENCE factura_compra_fac_id_seq OWNED BY factura_compra.fac_id;

CREATE SEQUENCE nota_remision_compra_nr_id_seq;

CREATE TABLE nota_remision_compra (
                nr_id INTEGER NOT NULL DEFAULT nextval('nota_remision_compra_nr_id_seq'),
                nr_nro_interno VARCHAR NOT NULL,
                nr_nro_legal VARCHAR NOT NULL,
                nota_fecha DATE NOT NULL,
                nr_timbrado VARCHAR NOT NULL,
                nr_vigencia DATE NOT NULL,
                conductor_nombre VARCHAR NOT NULL,
                conductor_documento VARCHAR NOT NULL,
                vehiculo_marca VARCHAR NOT NULL,
                vehiculo_modelo VARCHAR NOT NULL,
                vehiculo_matricula VARCHAR NOT NULL,
                nr_estado VARCHAR(30) NOT NULL,
                usuario_id INTEGER NOT NULL,
                prov_id INTEGER NOT NULL,
                deposito_id INTEGER NOT NULL,
                nota_remision_total INTEGER NOT NULL,
                fac_id INTEGER NOT NULL,
                CONSTRAINT nota_remision_compra_pk PRIMARY KEY (nr_id)
);


ALTER SEQUENCE nota_remision_compra_nr_id_seq OWNED BY nota_remision_compra.nr_id;

CREATE SEQUENCE cuentas_pagar_cp_id_seq;

CREATE TABLE cuentas_pagar (
                cp_id INTEGER NOT NULL DEFAULT nextval('cuentas_pagar_cp_id_seq'),
                fac_id INTEGER NOT NULL,
                monto_total INTEGER NOT NULL,
                monto_pendiente INTEGER NOT NULL,
                estado VARCHAR(15) NOT NULL,
                fecha_emision DATE NOT NULL,
                fecha_vencimiento DATE NOT NULL,
                usuario_id INTEGER NOT NULL,
                prov_id INTEGER NOT NULL,
                CONSTRAINT cuentas_pagar_pk PRIMARY KEY (cp_id)
);


ALTER SEQUENCE cuentas_pagar_cp_id_seq OWNED BY cuentas_pagar.cp_id;

CREATE SEQUENCE apertura_cierre_caja_apertura_cierre_id_seq;

CREATE TABLE apertura_cierre_caja (
                apertura_cierre_id INTEGER NOT NULL DEFAULT nextval('apertura_cierre_caja_apertura_cierre_id_seq'),
                apertura_nro VARCHAR NOT NULL,
                fecha_hora_apertura DATE NOT NULL,
                monto_inicial INTEGER NOT NULL,
                observacion_apertura VARCHAR NOT NULL,
                total_recaudado INTEGER NOT NULL,
                apertura_estado VARCHAR(30) NOT NULL,
                fecha_hora_cierre DATE NOT NULL,
                id_caja INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                cajero_id INTEGER NOT NULL,
                observacion_cierre VARCHAR NOT NULL,
                CONSTRAINT apertura_cierre_caja_pk PRIMARY KEY (apertura_cierre_id)
);


ALTER SEQUENCE apertura_cierre_caja_apertura_cierre_id_seq OWNED BY apertura_cierre_caja.apertura_cierre_id;

CREATE SEQUENCE factura_venta_id_factura_venta_seq;

CREATE TABLE factura_venta (
                id_factura_venta INTEGER NOT NULL DEFAULT nextval('factura_venta_id_factura_venta_seq'),
                apertura_cierre_id INTEGER NOT NULL,
                factura_nro_interno VARCHAR NOT NULL,
                factura_nro_fiscal VARCHAR NOT NULL,
                factura_fecha DATE NOT NULL,
                factura_tipo VARCHAR NOT NULL,
                factura_total INTEGER NOT NULL,
                factura_interes INTEGER NOT NULL,
                factura_iva INTEGER NOT NULL,
                factura_estado VARCHAR(30) NOT NULL,
                factura_cuotas INTEGER NOT NULL,
                id_pedido_venta INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                id_timbrado INTEGER NOT NULL,
                CONSTRAINT factura_venta_pk PRIMARY KEY (id_factura_venta)
);


ALTER SEQUENCE factura_venta_id_factura_venta_seq OWNED BY factura_venta.id_factura_venta;

CREATE SEQUENCE cuentas_cobrar_id_cuenta_cobrar_seq;

CREATE TABLE cuentas_cobrar (
                id_cuenta_cobrar INTEGER NOT NULL DEFAULT nextval('cuentas_cobrar_id_cuenta_cobrar_seq'),
                id_factura_venta INTEGER NOT NULL,
                cuenta_total INTEGER NOT NULL,
                cuenta_cobrar_fecha DATE NOT NULL,
                cuenta_cobrar_monto INTEGER NOT NULL,
                cuenta_cobrar_estado VARCHAR(30) NOT NULL,
                CONSTRAINT cuentas_cobrar_pk PRIMARY KEY (id_cuenta_cobrar)
);


ALTER SEQUENCE cuentas_cobrar_id_cuenta_cobrar_seq OWNED BY cuentas_cobrar.id_cuenta_cobrar;

CREATE TABLE cuentas_cobrar_detalle (
                id_cuenta_cobrar INTEGER NOT NULL,
                nro_cuota INTEGER NOT NULL,
                fecha_vencimiento DATE NOT NULL,
                importe_cuota INTEGER NOT NULL,
                cuota_estado VARCHAR NOT NULL,
                CONSTRAINT cuentas_cobrar_detalle_pk PRIMARY KEY (id_cuenta_cobrar)
);


CREATE TABLE cierre_caja_detalle (
                apertura_cierre_id INTEGER NOT NULL,
                tipo_pago_id INTEGER NOT NULL,
                valor_esperado INTEGER NOT NULL,
                valor_verificado INTEGER NOT NULL,
                faltante INTEGER NOT NULL,
                sobrante INTEGER NOT NULL,
                CONSTRAINT cierre_caja_detalle_pk PRIMARY KEY (apertura_cierre_id, tipo_pago_id)
);


CREATE SEQUENCE cobros_cobro_id_seq;

CREATE TABLE cobros (
                cobro_id INTEGER NOT NULL DEFAULT nextval('cobros_cobro_id_seq'),
                cobro_nro VARCHAR NOT NULL,
                apertura_cierre_id INTEGER NOT NULL,
                cobro_fecha DATE NOT NULL,
                cobro_estado VARCHAR(30) NOT NULL,
                usuario_id INTEGER NOT NULL,
                cajero_id INTEGER NOT NULL,
                cliente_id INTEGER NOT NULL,
                cobro_total INTEGER NOT NULL,
                cobro_observacion VARCHAR NOT NULL,
                CONSTRAINT cobros_pk PRIMARY KEY (cobro_id)
);


ALTER SEQUENCE cobros_cobro_id_seq OWNED BY cobros.cobro_id;

CREATE TABLE cobro_tarjeta (
                id_tarjeta INTEGER NOT NULL,
                cobro_id INTEGER NOT NULL,
                tarjeta_tipo VARCHAR NOT NULL,
                tarjeta_marca VARCHAR NOT NULL,
                tarjeta_numero_operacion VARCHAR NOT NULL,
                tarjeta_cuotas VARCHAR NOT NULL,
                tarjeta_monto VARCHAR NOT NULL,
                tarjeta_estado VARCHAR NOT NULL,
                id_banco INTEGER NOT NULL,
                CONSTRAINT cobro_tarjeta_pk PRIMARY KEY (id_tarjeta)
);


CREATE TABLE cobro_transeferencia (
                id_transferencia INTEGER NOT NULL,
                cobro_id INTEGER NOT NULL,
                id_banco INTEGER NOT NULL,
                transferencia_fecha DATE NOT NULL,
                transferencia_numero VARCHAR NOT NULL,
                transferencia_monto INTEGER NOT NULL,
                transferencia_estado VARCHAR NOT NULL,
                CONSTRAINT cobro_transeferencia_pk PRIMARY KEY (id_transferencia)
);


CREATE SEQUENCE cobro_cheque_id_cheque_seq;

CREATE TABLE cobro_cheque (
                id_cheque INTEGER NOT NULL DEFAULT nextval('cobro_cheque_id_cheque_seq'),
                cheque_fecha_vencimiento DATE NOT NULL,
                cheque_tipo VARCHAR(30) NOT NULL,
                cheque_numero INTEGER NOT NULL,
                cheque_fecha_emision DATE NOT NULL,
                monto_cheque INTEGER NOT NULL,
                cheque_estado VARCHAR(30) NOT NULL,
                id_banco INTEGER NOT NULL,
                cobro_id INTEGER NOT NULL,
                CONSTRAINT cobro_cheque_pk PRIMARY KEY (id_cheque)
);


ALTER SEQUENCE cobro_cheque_id_cheque_seq OWNED BY cobro_cheque.id_cheque;

CREATE TABLE detalle_cobros (
                cobro_id INTEGER NOT NULL,
                id_cuenta_cobrar INTEGER NOT NULL,
                monto_cobro INTEGER NOT NULL,
                tipo_pago_id INTEGER NOT NULL,
                nro_cuota INTEGER NOT NULL,
                CONSTRAINT detalle_cobros_pk PRIMARY KEY (cobro_id, id_cuenta_cobrar)
);


CREATE SEQUENCE recaudaciones_depositar_id_recaudacion_seq;

CREATE TABLE recaudaciones_depositar (
                id_recaudacion INTEGER NOT NULL DEFAULT nextval('recaudaciones_depositar_id_recaudacion_seq'),
                recaudacion_monto_efectivo INTEGER NOT NULL,
                recaudacion_monto_cheque INTEGER NOT NULL,
                recaudacion_estado VARCHAR(30) NOT NULL,
                recaudacion_fecha DATE NOT NULL,
                recaudacion_total INTEGER NOT NULL,
                apertura_cierre_id INTEGER NOT NULL,
                CONSTRAINT recaudaciones_depositar_pk PRIMARY KEY (id_recaudacion)
);


ALTER SEQUENCE recaudaciones_depositar_id_recaudacion_seq OWNED BY recaudaciones_depositar.id_recaudacion;

CREATE SEQUENCE arqueo_caja_id_arqueo_seq;

CREATE TABLE arqueo_caja (
                id_arqueo INTEGER NOT NULL DEFAULT nextval('arqueo_caja_id_arqueo_seq'),
                apertura_cierre_id INTEGER NOT NULL,
                arqueo_estado VARCHAR(30) NOT NULL,
                usuario_id INTEGER NOT NULL,
                arqueo_fecha DATE NOT NULL,
                CONSTRAINT arqueo_caja_pk PRIMARY KEY (id_arqueo)
);


ALTER SEQUENCE arqueo_caja_id_arqueo_seq OWNED BY arqueo_caja.id_arqueo;

CREATE TABLE arqueo_detalle (
                id_arqueo INTEGER NOT NULL,
                tipo_pago_id INTEGER NOT NULL,
                valor_esperado INTEGER NOT NULL,
                valor_verificado INTEGER NOT NULL,
                faltante INTEGER NOT NULL,
                sobrante INTEGER NOT NULL,
                observacion VARCHAR NOT NULL,
                CONSTRAINT arqueo_detalle_pk PRIMARY KEY (id_arqueo, tipo_pago_id)
);


CREATE SEQUENCE motivo_nota_motivo_id_seq;

CREATE TABLE motivo_nota (
                motivo_id INTEGER NOT NULL DEFAULT nextval('motivo_nota_motivo_id_seq'),
                motivo_descripcion VARCHAR(30) NOT NULL,
                motivo_tipo VARCHAR NOT NULL,
                motivo_estado VARCHAR NOT NULL,
                usuario_id INTEGER NOT NULL,
                CONSTRAINT motivo_nota_pk PRIMARY KEY (motivo_id)
);


ALTER SEQUENCE motivo_nota_motivo_id_seq OWNED BY motivo_nota.motivo_id;

CREATE SEQUENCE nota_venta_id_nota_venta_seq;

CREATE TABLE nota_venta (
                id_nota_venta INTEGER NOT NULL DEFAULT nextval('nota_venta_id_nota_venta_seq'),
                nota_numero INTEGER NOT NULL,
                nota_nro_fiscal VARCHAR NOT NULL,
                tipo_nota VARCHAR NOT NULL,
                fecha_emision DATE NOT NULL,
                monto_total INTEGER NOT NULL,
                nota_estado VARCHAR(30) NOT NULL,
                id_factura_venta INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                motivo_id INTEGER NOT NULL,
                id_timbrado INTEGER NOT NULL,
                nota_observacion VARCHAR NOT NULL,
                CONSTRAINT nota_venta_pk PRIMARY KEY (id_nota_venta)
);


ALTER SEQUENCE nota_venta_id_nota_venta_seq OWNED BY nota_venta.id_nota_venta;

CREATE SEQUENCE iva_venta_id_iva_venta_seq;

CREATE TABLE iva_venta (
                id_iva_venta INTEGER NOT NULL DEFAULT nextval('iva_venta_id_iva_venta_seq'),
                id_factura_venta INTEGER NOT NULL,
                iva_fecha DATE NOT NULL,
                iva_5 NUMERIC NOT NULL,
                iva_10 NUMERIC NOT NULL,
                iva_exento NUMERIC NOT NULL,
                id_nota_venta INTEGER NOT NULL,
                CONSTRAINT iva_venta_pk PRIMARY KEY (id_iva_venta)
);


ALTER SEQUENCE iva_venta_id_iva_venta_seq OWNED BY iva_venta.id_iva_venta;

CREATE SEQUENCE nota_compra_nota_id_seq;

CREATE TABLE nota_compra (
                nota_id INTEGER NOT NULL DEFAULT nextval('nota_compra_nota_id_seq'),
                nota_nro INTEGER NOT NULL,
                nota_fecha DATE NOT NULL,
                notacompra_tipo VARCHAR(50) NOT NULL,
                nota_timbrado VARCHAR(30) NOT NULL,
                nota_vencimiento DATE NOT NULL,
                nota_descripcion VARCHAR NOT NULL,
                nota_estado VARCHAR NOT NULL,
                nota_compra_inicio DATE NOT NULL,
                nota_total INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                prov_id INTEGER NOT NULL,
                motivo_id INTEGER NOT NULL,
                fac_id INTEGER NOT NULL,
                fecha_emision DATE NOT NULL,
                CONSTRAINT nota_compra_pk PRIMARY KEY (nota_id)
);


ALTER SEQUENCE nota_compra_nota_id_seq OWNED BY nota_compra.nota_id;

CREATE SEQUENCE iva_compra_id_libro_compra_seq;

CREATE TABLE iva_compras (
                iva_id INTEGER NOT NULL DEFAULT nextval('iva_compra_id_libro_compra_seq'),
                fac_id INTEGER NOT NULL,
                iva_fecha DATE NOT NULL,
                iva_exento NUMERIC NOT NULL,
                iva_5 NUMERIC NOT NULL,
                iva_10 NUMERIC NOT NULL,
                nota_id INTEGER NOT NULL,
                CONSTRAINT iva_compras_pk PRIMARY KEY (iva_id)
);


ALTER SEQUENCE iva_compra_id_libro_compra_seq OWNED BY iva_compras.iva_id;

CREATE SEQUENCE unidad_medida_unidad_medida_id_seq;

CREATE TABLE unidad_medida (
                unidad_medida_id INTEGER NOT NULL DEFAULT nextval('unidad_medida_unidad_medida_id_seq'),
                unidad_medida_nombre VARCHAR(30) NOT NULL,
                unidad_medida_estado VARCHAR NOT NULL,
                usuario_id INTEGER NOT NULL,
                CONSTRAINT unidad_medida_pk PRIMARY KEY (unidad_medida_id)
);


ALTER SEQUENCE unidad_medida_unidad_medida_id_seq OWNED BY unidad_medida.unidad_medida_id;

CREATE SEQUENCE producto_terminado_producto_id_seq;

CREATE TABLE producto_terminado (
                producto_id INTEGER NOT NULL DEFAULT nextval('producto_terminado_producto_id_seq'),
                producto_descripcion VARCHAR(30) NOT NULL,
                unidad_medida_id INTEGER NOT NULL,
                tipo_iva_id INTEGER NOT NULL,
                tipo_producto_id INTEGER NOT NULL,
                producto_precio INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                deposito_id INTEGER NOT NULL,
                producto_estado VARCHAR NOT NULL,
                CONSTRAINT producto_terminado_pk PRIMARY KEY (producto_id)
);


ALTER SEQUENCE producto_terminado_producto_id_seq OWNED BY producto_terminado.producto_id;

CREATE TABLE costos_detalle_produccion (
                costo_id INTEGER NOT NULL,
                producto_id INTEGER NOT NULL,
                det_cantidad_producida INTEGER NOT NULL,
                det_costo_mp INTEGER NOT NULL,
                det_costo_mo INTEGER NOT NULL,
                det_cif_asignado INTEGER NOT NULL,
                det_costo_total_producto INTEGER NOT NULL,
                det_costo_unitario INTEGER NOT NULL,
                CONSTRAINT costos_detalle_produccion_pk PRIMARY KEY (costo_id, producto_id)
);


CREATE TABLE mermas (
                merma_id INTEGER NOT NULL,
                control_id INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                merma_nro VARCHAR NOT NULL,
                merma_fecha DATE NOT NULL,
                merma_observacion VARCHAR NOT NULL,
                merma_estado VARCHAR NOT NULL,
                CONSTRAINT mermas_pk PRIMARY KEY (merma_id)
);


CREATE TABLE mermas_detalle (
                merma_id INTEGER NOT NULL,
                producto_id INTEGER NOT NULL,
                motivo_merma_id INTEGER NOT NULL,
                id_inspectores INTEGER NOT NULL,
                det_cantidad_orden INTEGER NOT NULL,
                det_cantidad_procesada INTEGER NOT NULL,
                det_cantidad_merma INTEGER NOT NULL
);


CREATE TABLE produccion_terminada_detalle (
                produccion_terminada_id INTEGER NOT NULL,
                producto_id INTEGER NOT NULL,
                det_cantidad_producida INTEGER NOT NULL,
                deposito_id INTEGER NOT NULL,
                CONSTRAINT produccion_terminada_detalle_pk PRIMARY KEY (produccion_terminada_id, producto_id)
);


CREATE TABLE equipos_trabajo (
                equipo_id INTEGER NOT NULL,
                equipo_nro VARCHAR NOT NULL,
                pedido_id INTEGER NOT NULL,
                etapa_id INTEGER NOT NULL,
                producto_id INTEGER NOT NULL,
                etapa_secuencia INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                equipo_nombre VARCHAR NOT NULL,
                equipo_fecha DATE NOT NULL,
                equipo_estado VARCHAR NOT NULL,
                CONSTRAINT equipos_trabajo_pk PRIMARY KEY (equipo_id)
);


CREATE TABLE equipos_detalle (
                equipo_id INTEGER NOT NULL,
                trabajadores_id INTEGER NOT NULL,
                equipo_tarea VARCHAR NOT NULL,
                CONSTRAINT equipos_detalle_pk PRIMARY KEY (equipo_id, trabajadores_id)
);


CREATE TABLE etapa_produccion_detalle (
                etapa_id INTEGER NOT NULL,
                producto_id INTEGER NOT NULL,
                det_nombre VARCHAR(30) NOT NULL,
                det_secuencia INTEGER NOT NULL,
                det_procedimiento VARCHAR NOT NULL,
                det_minutos VARCHAR NOT NULL,
                det_observacion VARCHAR NOT NULL,
                CONSTRAINT etapa_produccion_detalle_pk PRIMARY KEY (etapa_id, producto_id)
);


CREATE TABLE pedido_detalle_produccion (
                pedido_id INTEGER NOT NULL,
                producto_id INTEGER NOT NULL,
                det_cantidad INTEGER NOT NULL,
                CONSTRAINT pedido_detalle_produccion_pk PRIMARY KEY (pedido_id, producto_id)
);


CREATE TABLE receta (
                receta_id INTEGER NOT NULL,
                receta_codigo VARCHAR NOT NULL,
                receta_fecha DATE NOT NULL,
                producto_id INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                receta_estado VARCHAR NOT NULL,
                CONSTRAINT receta_pk PRIMARY KEY (receta_id)
);


CREATE TABLE control_detalle_produccion (
                control_id INTEGER NOT NULL,
                producto_id INTEGER NOT NULL,
                etapa_id INTEGER NOT NULL,
                etapa_secuencia INTEGER NOT NULL,
                equipo_id INTEGER NOT NULL,
                id_inspectores INTEGER NOT NULL,
                det_cantidad_procesada INTEGER NOT NULL,
                det_hora_inicio DATE NOT NULL,
                det_hora_fin DATE NOT NULL,
                det_duracion VARCHAR NOT NULL,
                det_observacion VARCHAR NOT NULL,
                det_estado VARCHAR NOT NULL,
                CONSTRAINT control_detalle_produccion_pk PRIMARY KEY (control_id, producto_id)
);


CREATE TABLE orden_detalle_produccion (
                orden_id INTEGER NOT NULL,
                producto_id INTEGER NOT NULL,
                det_cantidad INTEGER NOT NULL,
                CONSTRAINT orden_detalle_produccion_pk PRIMARY KEY (orden_id, producto_id)
);


CREATE TABLE presupuesto_detalle_ventas (
                presu_id INTEGER NOT NULL,
                producto_id INTEGER NOT NULL,
                det_cantidad NUMERIC NOT NULL,
                det_precio NUMERIC NOT NULL,
                det_iva INTEGER NOT NULL,
                CONSTRAINT presupuesto_detalle_ventas_pk PRIMARY KEY (presu_id, producto_id)
);


CREATE TABLE nota_detalle_venta (
                id_nota_venta INTEGER NOT NULL,
                producto_id INTEGER NOT NULL,
                cantidad_nota INTEGER NOT NULL,
                nota_precio INTEGER NOT NULL,
                nota_iva NUMERIC NOT NULL,
                CONSTRAINT nota_detalle_venta_pk PRIMARY KEY (id_nota_venta, producto_id)
);


CREATE TABLE factura_detalle_venta (
                id_factura_venta INTEGER NOT NULL,
                producto_id INTEGER NOT NULL,
                fact_cantidad INTEGER NOT NULL,
                fact_exenta INTEGER NOT NULL,
                factura_iva_5 INTEGER NOT NULL,
                factura_iva_10 INTEGER NOT NULL,
                fact_precio INTEGER NOT NULL,
                CONSTRAINT factura_detalle_venta_pk PRIMARY KEY (id_factura_venta, producto_id)
);


CREATE TABLE pedidos_detalle_venta (
                id_pedido_venta INTEGER NOT NULL,
                producto_id INTEGER NOT NULL,
                det_cantidad INTEGER NOT NULL,
                det_precio INTEGER NOT NULL,
                det_iva INTEGER NOT NULL,
                CONSTRAINT pedidos_detalle_venta_pk PRIMARY KEY (id_pedido_venta, producto_id)
);


CREATE SEQUENCE stock_producto_id_stock_productos_seq;

CREATE TABLE stock_producto (
                id_stock_productos INTEGER NOT NULL DEFAULT nextval('stock_producto_id_stock_productos_seq'),
                producto_id INTEGER NOT NULL,
                deposito_id INTEGER NOT NULL,
                stock_prod_ven DATE NOT NULL,
                stock_prod_existente INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                CONSTRAINT stock_producto_pk PRIMARY KEY (id_stock_productos)
);


ALTER SEQUENCE stock_producto_id_stock_productos_seq OWNED BY stock_producto.id_stock_productos;

CREATE SEQUENCE materia_prima_mp_id_seq;

CREATE TABLE materia_prima (
                mp_id INTEGER NOT NULL DEFAULT nextval('materia_prima_mp_id_seq'),
                mp_descripcion VARCHAR(30) NOT NULL,
                unidad_medida_id INTEGER NOT NULL,
                tipo_iva_id INTEGER NOT NULL,
                marca_id INTEGER NOT NULL,
                mp_precio_referencia VARCHAR NOT NULL,
                deposito_id INTEGER NOT NULL,
                mp_estado VARCHAR(30) NOT NULL,
                usuario_id INTEGER NOT NULL,
                CONSTRAINT materia_prima_pk PRIMARY KEY (mp_id)
);


ALTER SEQUENCE materia_prima_mp_id_seq OWNED BY materia_prima.mp_id;

CREATE TABLE receta_detalle (
                receta_id INTEGER NOT NULL,
                mp_id INTEGER NOT NULL,
                det_cantidad INTEGER NOT NULL,
                CONSTRAINT receta_detalle_pk PRIMARY KEY (receta_id, mp_id)
);


CREATE TABLE pedido_detalle_materia_prima (
                pedido_mp_id INTEGER NOT NULL,
                mp_id INTEGER NOT NULL,
                det_cantidad_requerida INTEGER NOT NULL,
                det_cantidad_solicitada VARCHAR NOT NULL,
                CONSTRAINT pedido_detalle_materia_prima_pk PRIMARY KEY (pedido_mp_id, mp_id)
);


CREATE TABLE pedido_detalle_compra (
                pedido_id INTEGER NOT NULL,
                mp_id INTEGER NOT NULL,
                detalle_cantidad INTEGER NOT NULL,
                CONSTRAINT pedido_detalle_compra_pk PRIMARY KEY (pedido_id, mp_id)
);


CREATE TABLE nota_remision_detalle_compra (
                nr_id INTEGER NOT NULL,
                mp_id INTEGER NOT NULL,
                nota_cantidad INTEGER NOT NULL,
                nota_remi_iva NUMERIC NOT NULL,
                CONSTRAINT nota_remision_detalle_compra_pk PRIMARY KEY (nr_id, mp_id)
);


CREATE TABLE nota_detalle_compra (
                mp_id INTEGER NOT NULL,
                nota_id INTEGER NOT NULL,
                nota_compra_cantidad INTEGER NOT NULL,
                mp_iva NUMERIC NOT NULL,
                nota_precio INTEGER NOT NULL,
                CONSTRAINT nota_detalle_compra_pk PRIMARY KEY (mp_id, nota_id)
);


CREATE TABLE factura_detalle_compra (
                fac_id INTEGER NOT NULL,
                mp_id INTEGER NOT NULL,
                tipo_iva_id INTEGER NOT NULL,
                det_cantidad INTEGER NOT NULL,
                det_precio_unit INTEGER NOT NULL,
                det_iva_monto NUMERIC NOT NULL,
                CONSTRAINT factura_detalle_compra_pk PRIMARY KEY (fac_id, mp_id)
);


CREATE SEQUENCE stock_materia_prima_stock_id_seq;

CREATE TABLE stock_materia_prima (
                stock_id INTEGER NOT NULL DEFAULT nextval('stock_materia_prima_stock_id_seq'),
                mp_id INTEGER NOT NULL,
                deposito_id INTEGER NOT NULL,
                stock_cantidad INTEGER NOT NULL,
                usuario_id INTEGER NOT NULL,
                CONSTRAINT stock_materia_prima_pk PRIMARY KEY (stock_id)
);


ALTER SEQUENCE stock_materia_prima_stock_id_seq OWNED BY stock_materia_prima.stock_id;

CREATE TABLE ajustes_detalle (
                mp_id INTEGER NOT NULL,
                ajuste_id INTEGER NOT NULL,
                stock_id INTEGER NOT NULL,
                ajuste_cantidad INTEGER NOT NULL,
                CONSTRAINT ajustes_detalle_pk PRIMARY KEY (mp_id, ajuste_id, stock_id)
);


CREATE TABLE orden_detalle_compra (
                oc_id INTEGER NOT NULL,
                mp_id INTEGER NOT NULL,
                tipo_iva_id INTEGER NOT NULL,
                det_cantidad INTEGER NOT NULL,
                det_precio_unit INTEGER NOT NULL,
                CONSTRAINT orden_detalle_compra_pk PRIMARY KEY (oc_id, mp_id)
);


CREATE TABLE presupuesto_detalle_compra (
                presu_id INTEGER NOT NULL,
                mp_id INTEGER NOT NULL,
                tipo_iva_id INTEGER NOT NULL,
                det_cantidad INTEGER NOT NULL,
                det_precio_unitario INTEGER NOT NULL,
                det_descuento INTEGER NOT NULL,
                CONSTRAINT presupuesto_detalle_compra_pk PRIMARY KEY (presu_id, mp_id)
);


ALTER TABLE trabajadores ADD CONSTRAINT turno_trabajadores_fk
FOREIGN KEY (turno_id)
REFERENCES turno (turno_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE trabajadores ADD CONSTRAINT rol_trabajadores_fk
FOREIGN KEY (rol_id)
REFERENCES rol (rol_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE arqueo_detalle ADD CONSTRAINT tipo_pago_arqueo_detalle_fk
FOREIGN KEY (tipo_pago_id)
REFERENCES tipo_pago (tipo_pago_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE detalle_cobros ADD CONSTRAINT tipo_pago_detalle_cobros_fk
FOREIGN KEY (tipo_pago_id)
REFERENCES tipo_pago (tipo_pago_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cierre_caja_detalle ADD CONSTRAINT tipo_pago_cierre_caja_detalle_fk
FOREIGN KEY (tipo_pago_id)
REFERENCES tipo_pago (tipo_pago_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE mermas_detalle ADD CONSTRAINT motivo_merma_mermas_detalle_fk
FOREIGN KEY (motivo_merma_id)
REFERENCES motivo_merma (motivo_merma_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE producto_terminado ADD CONSTRAINT tipo_producto_producto_terminado_fk
FOREIGN KEY (tipo_producto_id)
REFERENCES tipo_producto (tipo_producto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE ajustes ADD CONSTRAINT motivo_ajuste_ajustes_fk
FOREIGN KEY (motivo_id)
REFERENCES motivo_ajuste (motivo_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE materia_prima ADD CONSTRAINT marca_materia_prima_fk
FOREIGN KEY (marca_id)
REFERENCES marca (marca_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE presupuesto_venta ADD CONSTRAINT tipo_presupuesto_presupuesto_venta_fk
FOREIGN KEY (tipo_presupuesto_id)
REFERENCES tipo_presupuesto (tipo_presupuesto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE usuario ADD CONSTRAINT empleados_usuarios_fk
FOREIGN KEY (personal_id)
REFERENCES personal (personal_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE trabajadores ADD CONSTRAINT personal_trabajadores_fk
FOREIGN KEY (personal_id)
REFERENCES personal (personal_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE apertura_cierre_caja ADD CONSTRAINT usuarios_aper_cierre_caja_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedidos_ventas ADD CONSTRAINT usuarios_pedido_venta_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE factura_venta ADD CONSTRAINT usuarios_factura_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE arqueo_caja ADD CONSTRAINT usuarios_arqueo_caja_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cobros ADD CONSTRAINT usuarios_cobros_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedido_compra ADD CONSTRAINT usuarios_pedidos_compra_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE presupuesto_compra ADD CONSTRAINT usuarios_presupuesto_compra_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE orden_compra ADD CONSTRAINT usuarios_orden_de_compra_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE factura_compra ADD CONSTRAINT usuarios_factura_compra_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_compra ADD CONSTRAINT usuarios_nota_credi_compra_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_remision_compra ADD CONSTRAINT usuarios_nota_remision_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE presupuesto_venta ADD CONSTRAINT usuarios_presupuesto_venta_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_venta ADD CONSTRAINT usuarios_nota_credito_venta_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cuentas_pagar ADD CONSTRAINT usuarios_cuentas_pagar_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE ajustes ADD CONSTRAINT usuarios_ajustes_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cargo ADD CONSTRAINT usuarios_cargos_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE timbrado ADD CONSTRAINT usuarios_timbrado_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE materia_prima ADD CONSTRAINT usuarios_materia_prima_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE personal ADD CONSTRAINT usuarios_personal_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE unidad_medida ADD CONSTRAINT usuarios_categoria_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE proveedor ADD CONSTRAINT usuarios_proveedor_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE clientes ADD CONSTRAINT usuarios_clientes_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE modulos ADD CONSTRAINT usuarios_modulos_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE trabajadores ADD CONSTRAINT usuarios_trabajadores_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE inspectores ADD CONSTRAINT usuarios_inspectores_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cajeros ADD CONSTRAINT usuarios_cajero_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE deposito ADD CONSTRAINT usuarios_deposito_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE motivo_nota ADD CONSTRAINT usuarios_motivo_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedido_materia_prima ADD CONSTRAINT usuarios_pedido_materia_produccion_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cajas ADD CONSTRAINT usuarios_cajas_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE banco ADD CONSTRAINT usuarios_banco_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE tipo_iva ADD CONSTRAINT usuarios_tipo_iva_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE producto_terminado ADD CONSTRAINT usuarios_productos_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE stock_producto ADD CONSTRAINT usuarios_stock_producto_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE stock_materia_prima ADD CONSTRAINT usuarios_stock_materia_prima_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE orden_produccion ADD CONSTRAINT usuarios_orden_produccion_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE control_produccion ADD CONSTRAINT usuarios_control_produccion_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE etapa_produccion ADD CONSTRAINT usuarios_etapa_produccion_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE costos_produccion ADD CONSTRAINT usuarios_costo_produccion_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE receta ADD CONSTRAINT usuario_receta_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE equipos_trabajo ADD CONSTRAINT usuario_equipos_trabajo_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE produccion_terminada ADD CONSTRAINT usuario_produccion_terminada_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE mermas ADD CONSTRAINT usuario_mermas_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedido_produccion ADD CONSTRAINT usuario_pedido_produccion_fk
FOREIGN KEY (usuario_id)
REFERENCES usuario (usuario_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE etapa_produccion_detalle ADD CONSTRAINT etapa_produccion_etapa_detalle_produccion_fk
FOREIGN KEY (etapa_id)
REFERENCES etapa_produccion (etapa_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE control_detalle_produccion ADD CONSTRAINT etapa_produccion_control_detalle_produccion_fk
FOREIGN KEY (etapa_id)
REFERENCES etapa_produccion (etapa_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE equipos_trabajo ADD CONSTRAINT etapa_produccion_equipos_trabajo_fk
FOREIGN KEY (etapa_id)
REFERENCES etapa_produccion (etapa_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE materia_prima ADD CONSTRAINT tipo_iva_materia_prima_fk
FOREIGN KEY (tipo_iva_id)
REFERENCES tipo_iva (tipo_iva_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE presupuesto_detalle_compra ADD CONSTRAINT tipo_iva_presupuesto_detalle_compra_fk
FOREIGN KEY (tipo_iva_id)
REFERENCES tipo_iva (tipo_iva_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE orden_detalle_compra ADD CONSTRAINT tipo_iva_orden_detalle_compra_fk
FOREIGN KEY (tipo_iva_id)
REFERENCES tipo_iva (tipo_iva_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE factura_detalle_compra ADD CONSTRAINT tipo_iva_factura_detalle_compra_fk
FOREIGN KEY (tipo_iva_id)
REFERENCES tipo_iva (tipo_iva_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE producto_terminado ADD CONSTRAINT tipo_iva_producto_terminado_fk
FOREIGN KEY (tipo_iva_id)
REFERENCES tipo_iva (tipo_iva_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cobro_cheque ADD CONSTRAINT banco_cheque_fk
FOREIGN KEY (id_banco)
REFERENCES banco (id_banco)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cobro_transeferencia ADD CONSTRAINT banco_cobro_transeferencia_fk
FOREIGN KEY (id_banco)
REFERENCES banco (id_banco)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cobro_tarjeta ADD CONSTRAINT banco_cobro_tarjeta_fk
FOREIGN KEY (id_banco)
REFERENCES banco (id_banco)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE apertura_cierre_caja ADD CONSTRAINT cajas_aper_cierre_caja_fk
FOREIGN KEY (id_caja)
REFERENCES cajas (id_caja)
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

ALTER TABLE materia_prima ADD CONSTRAINT deposito_materia_prima_fk
FOREIGN KEY (deposito_id)
REFERENCES deposito (deposito_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE producto_terminado ADD CONSTRAINT deposito_producto_terminado_fk
FOREIGN KEY (deposito_id)
REFERENCES deposito (deposito_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE produccion_terminada_detalle ADD CONSTRAINT deposito_produiccion_terminada_detalle_fk
FOREIGN KEY (deposito_id)
REFERENCES deposito (deposito_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE apertura_cierre_caja ADD CONSTRAINT cajero_apertura_cierre_caja_fk
FOREIGN KEY (cajero_id)
REFERENCES cajeros (cajero_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cobros ADD CONSTRAINT cajero_cobros_fk
FOREIGN KEY (cajero_id)
REFERENCES cajeros (cajero_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE equipos_detalle ADD CONSTRAINT trabajadores_equipos_detalle_fk
FOREIGN KEY (trabajadores_id)
REFERENCES trabajadores (trabajadores_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE inspectores ADD CONSTRAINT trabajadores_inspectores_fk
FOREIGN KEY (trabajadores_id)
REFERENCES trabajadores (trabajadores_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE control_detalle_produccion ADD CONSTRAINT inspectores_control_detalle_produccion_fk
FOREIGN KEY (id_inspectores)
REFERENCES inspectores (id_inspectores)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE mermas_detalle ADD CONSTRAINT inspectores_mermas_detalle_fk
FOREIGN KEY (id_inspectores)
REFERENCES inspectores (id_inspectores)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE usuario ADD CONSTRAINT modulos_usuarios_fk
FOREIGN KEY (modulo_id)
REFERENCES modulos (modulo_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedidos_ventas ADD CONSTRAINT clientes_pedido_venta_fk
FOREIGN KEY (cliente_id)
REFERENCES clientes (cliente_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE presupuesto_venta ADD CONSTRAINT clientes_presupuesto_venta_fk
FOREIGN KEY (cliente_id)
REFERENCES clientes (cliente_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cobros ADD CONSTRAINT clientes_cobros_fk
FOREIGN KEY (cliente_id)
REFERENCES clientes (cliente_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE presupuesto_compra ADD CONSTRAINT proveedor_presupuesto_compra_fk
FOREIGN KEY (prov_id)
REFERENCES proveedor (prov_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE orden_compra ADD CONSTRAINT proveedor_orden_de_compra_fk
FOREIGN KEY (prov_id)
REFERENCES proveedor (prov_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_remision_compra ADD CONSTRAINT proveedor_nota_remision_fk
FOREIGN KEY (prov_id)
REFERENCES proveedor (prov_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cuentas_pagar ADD CONSTRAINT proveedor_cuentas_pagar_fk
FOREIGN KEY (prov_id)
REFERENCES proveedor (prov_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_compra ADD CONSTRAINT proveedor_nota_compra_fk
FOREIGN KEY (prov_id)
REFERENCES proveedor (prov_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE factura_venta ADD CONSTRAINT timbrado_factura_fk
FOREIGN KEY (id_timbrado)
REFERENCES timbrado (id_timbrado)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_venta ADD CONSTRAINT timbrado_nota_venta_fk
FOREIGN KEY (id_timbrado)
REFERENCES timbrado (id_timbrado)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE usuario ADD CONSTRAINT cargos_usuarios_fk
FOREIGN KEY (cargo_id)
REFERENCES cargo (cargo_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE ajustes_detalle ADD CONSTRAINT ajuste_stock_detalle_ajuste_fk
FOREIGN KEY (ajuste_id)
REFERENCES ajustes (ajuste_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE presupuesto_detalle_ventas ADD CONSTRAINT presupuesto_venta_detalle_presupuesto_venta_fk
FOREIGN KEY (presu_id)
REFERENCES presupuesto_venta (presu_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedidos_ventas ADD CONSTRAINT presupuesto_venta_pedidos_ventas_fk
FOREIGN KEY (presu_id)
REFERENCES presupuesto_venta (presu_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedidos_detalle_venta ADD CONSTRAINT pedido_venta_detalle_pedido_articulo_fk
FOREIGN KEY (id_pedido_venta)
REFERENCES pedidos_ventas (id_pedido_venta)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE factura_venta ADD CONSTRAINT pedido_venta_factura_ventas_fk
FOREIGN KEY (id_pedido_venta)
REFERENCES pedidos_ventas (id_pedido_venta)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedido_produccion ADD CONSTRAINT pedidos_ventas_pedido_produccion_fk
FOREIGN KEY (id_pedido_venta)
REFERENCES pedidos_ventas (id_pedido_venta)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedido_detalle_produccion ADD CONSTRAINT pedido_produccion_pedido_detalle_produccion_fk
FOREIGN KEY (pedido_id)
REFERENCES pedido_produccion (pedido_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedido_materia_prima ADD CONSTRAINT pedido_produccion_pedido_materia_prima_fk
FOREIGN KEY (pedido_id)
REFERENCES pedido_produccion (pedido_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE orden_produccion ADD CONSTRAINT pedido_produccion_orden_produccion_fk
FOREIGN KEY (pedido_id)
REFERENCES pedido_produccion (pedido_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE equipos_trabajo ADD CONSTRAINT pedido_produccion_equipos_trabajo_fk
FOREIGN KEY (pedido_id)
REFERENCES pedido_produccion (pedido_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE costos_produccion ADD CONSTRAINT pedido_produccion_costos_produccion_fk
FOREIGN KEY (pedido_id)
REFERENCES pedido_produccion (pedido_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE orden_detalle_produccion ADD CONSTRAINT orden_produccion_orden_detalle_produccion_fk
FOREIGN KEY (orden_id)
REFERENCES orden_produccion (orden_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE control_produccion ADD CONSTRAINT orden_produccion_control_produccion_fk
FOREIGN KEY (orden_id)
REFERENCES orden_produccion (orden_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE produccion_terminada ADD CONSTRAINT orden_produccion_produccion_terminada_fk
FOREIGN KEY (orden_id)
REFERENCES orden_produccion (orden_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE produccion_terminada_detalle ADD CONSTRAINT produccion_terminada_produiccion_terminada_detalle_fk
FOREIGN KEY (produccion_terminada_id)
REFERENCES produccion_terminada (produccion_terminada_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE control_detalle_produccion ADD CONSTRAINT control_produccion_control_produccion_detalle_fk
FOREIGN KEY (control_id)
REFERENCES control_produccion (control_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE mermas ADD CONSTRAINT control_produccion_mermas_fk
FOREIGN KEY (control_id)
REFERENCES control_produccion (control_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedido_detalle_materia_prima ADD CONSTRAINT pedido_materia_produccion_pedido_materia_detalle_produccion_fk
FOREIGN KEY (pedido_mp_id)
REFERENCES pedido_materia_prima (pedido_mp_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedido_compra ADD CONSTRAINT pedido_materia_prima_pedido_compra_fk
FOREIGN KEY (pedido_mp_id)
REFERENCES pedido_materia_prima (pedido_mp_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE presupuesto_compra ADD CONSTRAINT pedidos_compra_presupuesto_compra_fk
FOREIGN KEY (pedido_id)
REFERENCES pedido_compra (pedido_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedido_detalle_compra ADD CONSTRAINT pedidos_compra_pedido_detalle_compra_fk
FOREIGN KEY (pedido_id)
REFERENCES pedido_compra (pedido_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE costos_produccion ADD CONSTRAINT pedidos_compra_costo_produccion_fk
FOREIGN KEY (pedido_id)
REFERENCES pedido_compra (pedido_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE costos_detalle_produccion ADD CONSTRAINT costo_produccion_costo_detalle_produccion_fk
FOREIGN KEY (costo_id)
REFERENCES costos_produccion (costo_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE presupuesto_detalle_compra ADD CONSTRAINT presupuesto_compra_detalle_presu_compra_fk
FOREIGN KEY (presu_id)
REFERENCES presupuesto_compra (presu_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE orden_compra ADD CONSTRAINT presupuesto_compra_orden_de_compra_fk
FOREIGN KEY (presu_id)
REFERENCES presupuesto_compra (presu_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE orden_detalle_compra ADD CONSTRAINT orden_de_compra_detalle_orden_compra_fk
FOREIGN KEY (oc_id)
REFERENCES orden_compra (oc_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE factura_compra ADD CONSTRAINT orden_de_compra_factura_compra_fk
FOREIGN KEY (oc_id)
REFERENCES orden_compra (oc_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE factura_detalle_compra ADD CONSTRAINT factura_compra_detalle_registro_compra_fk
FOREIGN KEY (fac_id)
REFERENCES factura_compra (fac_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE iva_compras ADD CONSTRAINT factura_compra_iva_compra_fk
FOREIGN KEY (fac_id)
REFERENCES factura_compra (fac_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cuentas_pagar ADD CONSTRAINT factura_compra_cuentas_pagar_fk
FOREIGN KEY (fac_id)
REFERENCES factura_compra (fac_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_compra ADD CONSTRAINT factura_compra_nota_compra_fk
FOREIGN KEY (fac_id)
REFERENCES factura_compra (fac_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_remision_compra ADD CONSTRAINT factura_compra_nota_remision_compra_fk
FOREIGN KEY (fac_id)
REFERENCES factura_compra (fac_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_remision_detalle_compra ADD CONSTRAINT nota_remision_detalle_nota_remision_fk
FOREIGN KEY (nr_id)
REFERENCES nota_remision_compra (nr_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE arqueo_caja ADD CONSTRAINT aper_cierre_caja_arqueo_caja_fk
FOREIGN KEY (apertura_cierre_id)
REFERENCES apertura_cierre_caja (apertura_cierre_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE recaudaciones_depositar ADD CONSTRAINT aper_cierre_caja_recaudacion_depositar_fk
FOREIGN KEY (apertura_cierre_id)
REFERENCES apertura_cierre_caja (apertura_cierre_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cobros ADD CONSTRAINT aper_cierre_caja_cobros_fk
FOREIGN KEY (apertura_cierre_id)
REFERENCES apertura_cierre_caja (apertura_cierre_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cierre_caja_detalle ADD CONSTRAINT apertura_cierre_caja_cierre_caja_detalle_fk
FOREIGN KEY (apertura_cierre_id)
REFERENCES apertura_cierre_caja (apertura_cierre_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE factura_venta ADD CONSTRAINT apertura_cierre_caja_factura_venta_fk
FOREIGN KEY (apertura_cierre_id)
REFERENCES apertura_cierre_caja (apertura_cierre_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE factura_detalle_venta ADD CONSTRAINT factura_detalle_factura_fk
FOREIGN KEY (id_factura_venta)
REFERENCES factura_venta (id_factura_venta)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cuentas_cobrar ADD CONSTRAINT factura_cuenta_cobrar_fk
FOREIGN KEY (id_factura_venta)
REFERENCES factura_venta (id_factura_venta)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE iva_venta ADD CONSTRAINT factura_iva_venta_fk
FOREIGN KEY (id_factura_venta)
REFERENCES factura_venta (id_factura_venta)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_venta ADD CONSTRAINT factura_nota_credito_venta_fk
FOREIGN KEY (id_factura_venta)
REFERENCES factura_venta (id_factura_venta)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE detalle_cobros ADD CONSTRAINT cuenta_cobrar_detalle_cobros_fk
FOREIGN KEY (id_cuenta_cobrar)
REFERENCES cuentas_cobrar (id_cuenta_cobrar)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cuentas_cobrar_detalle ADD CONSTRAINT cuentas_cobrar_cuentas_cobrar_detalle_fk
FOREIGN KEY (id_cuenta_cobrar)
REFERENCES cuentas_cobrar (id_cuenta_cobrar)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE detalle_cobros ADD CONSTRAINT cobros_detalle_cobros_fk
FOREIGN KEY (cobro_id)
REFERENCES cobros (cobro_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cobro_cheque ADD CONSTRAINT cobros_cheque_fk
FOREIGN KEY (cobro_id)
REFERENCES cobros (cobro_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cobro_transeferencia ADD CONSTRAINT cobros_cobro_transeferencia_fk
FOREIGN KEY (cobro_id)
REFERENCES cobros (cobro_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE cobro_tarjeta ADD CONSTRAINT cobros_cobro_tarjeta_fk
FOREIGN KEY (cobro_id)
REFERENCES cobros (cobro_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE arqueo_detalle ADD CONSTRAINT arqueo_caja_arqueo_detalle_fk
FOREIGN KEY (id_arqueo)
REFERENCES arqueo_caja (id_arqueo)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_compra ADD CONSTRAINT motivo_nota_compra_fk
FOREIGN KEY (motivo_id)
REFERENCES motivo_nota (motivo_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_venta ADD CONSTRAINT motivo_nota_venta_fk
FOREIGN KEY (motivo_id)
REFERENCES motivo_nota (motivo_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_detalle_venta ADD CONSTRAINT nota_credito_venta_detalle_credito_venta_fk
FOREIGN KEY (id_nota_venta)
REFERENCES nota_venta (id_nota_venta)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE iva_venta ADD CONSTRAINT nota_venta_iva_venta_fk
FOREIGN KEY (id_nota_venta)
REFERENCES nota_venta (id_nota_venta)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_detalle_compra ADD CONSTRAINT nota_credi_compra_detalle_credito_compra_fk
FOREIGN KEY (nota_id)
REFERENCES nota_compra (nota_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE iva_compras ADD CONSTRAINT nota_compra_iva_compras_fk
FOREIGN KEY (nota_id)
REFERENCES nota_compra (nota_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE materia_prima ADD CONSTRAINT categoria_articulos_fk
FOREIGN KEY (unidad_medida_id)
REFERENCES unidad_medida (unidad_medida_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE producto_terminado ADD CONSTRAINT unidad_medida_productos_fk
FOREIGN KEY (unidad_medida_id)
REFERENCES unidad_medida (unidad_medida_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE stock_producto ADD CONSTRAINT productos_stock_producto_fk
FOREIGN KEY (producto_id)
REFERENCES producto_terminado (producto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedidos_detalle_venta ADD CONSTRAINT productos_detalle_pedido_venta_fk
FOREIGN KEY (producto_id)
REFERENCES producto_terminado (producto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE factura_detalle_venta ADD CONSTRAINT productos_detalle_factura_ventas_fk
FOREIGN KEY (producto_id)
REFERENCES producto_terminado (producto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_detalle_venta ADD CONSTRAINT productos_nota_detalle_venta_fk
FOREIGN KEY (producto_id)
REFERENCES producto_terminado (producto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE presupuesto_detalle_ventas ADD CONSTRAINT productos_detalle_presupuesto_venta_fk
FOREIGN KEY (producto_id)
REFERENCES producto_terminado (producto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE orden_detalle_produccion ADD CONSTRAINT productos_orden_detalle_produccion_fk
FOREIGN KEY (producto_id)
REFERENCES producto_terminado (producto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE control_detalle_produccion ADD CONSTRAINT productos_control_produccion_detalle_fk
FOREIGN KEY (producto_id)
REFERENCES producto_terminado (producto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE receta ADD CONSTRAINT productos_receta_fk
FOREIGN KEY (producto_id)
REFERENCES producto_terminado (producto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedido_detalle_produccion ADD CONSTRAINT producto_terminado_pedido_detalle_produccion_fk
FOREIGN KEY (producto_id)
REFERENCES producto_terminado (producto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE etapa_produccion_detalle ADD CONSTRAINT producto_terminado_etapa_produccion_detalle_fk
FOREIGN KEY (producto_id)
REFERENCES producto_terminado (producto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE equipos_trabajo ADD CONSTRAINT producto_terminado_equipos_trabajo_fk
FOREIGN KEY (producto_id)
REFERENCES producto_terminado (producto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE produccion_terminada_detalle ADD CONSTRAINT producto_terminado_produiccion_terminada_detalle_fk
FOREIGN KEY (producto_id)
REFERENCES producto_terminado (producto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE mermas_detalle ADD CONSTRAINT producto_terminado_mermas_detalle_fk
FOREIGN KEY (producto_id)
REFERENCES producto_terminado (producto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE costos_detalle_produccion ADD CONSTRAINT producto_terminado_costos_detalle_produccion_fk
FOREIGN KEY (producto_id)
REFERENCES producto_terminado (producto_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE mermas_detalle ADD CONSTRAINT mermas_mermas_detalle_fk
FOREIGN KEY (merma_id)
REFERENCES mermas (merma_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE equipos_detalle ADD CONSTRAINT equipos_trabajo_equipos_detalle_fk
FOREIGN KEY (equipo_id)
REFERENCES equipos_trabajo (equipo_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE control_detalle_produccion ADD CONSTRAINT equipos_trabajo_control_detalle_produccion_fk
FOREIGN KEY (equipo_id)
REFERENCES equipos_trabajo (equipo_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE receta_detalle ADD CONSTRAINT receta_receta_detalle_fk
FOREIGN KEY (receta_id)
REFERENCES receta (receta_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE presupuesto_detalle_compra ADD CONSTRAINT articulos_detalle_presu_compra_fk
FOREIGN KEY (mp_id)
REFERENCES materia_prima (mp_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE orden_detalle_compra ADD CONSTRAINT articulos_detalle_orden_compra_fk
FOREIGN KEY (mp_id)
REFERENCES materia_prima (mp_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE stock_materia_prima ADD CONSTRAINT articulos_stock_detalle_fk
FOREIGN KEY (mp_id)
REFERENCES materia_prima (mp_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE factura_detalle_compra ADD CONSTRAINT articulos_detalle_registro_compra_fk
FOREIGN KEY (mp_id)
REFERENCES materia_prima (mp_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_detalle_compra ADD CONSTRAINT articulos_detalle_credito_compra_fk
FOREIGN KEY (mp_id)
REFERENCES materia_prima (mp_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE ajustes_detalle ADD CONSTRAINT articulos_detalle_ajuste_fk
FOREIGN KEY (mp_id)
REFERENCES materia_prima (mp_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE nota_remision_detalle_compra ADD CONSTRAINT articulos_detalle_nota_remision_fk
FOREIGN KEY (mp_id)
REFERENCES materia_prima (mp_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedido_detalle_compra ADD CONSTRAINT materia_prima_pedido_detalle_compra_fk
FOREIGN KEY (mp_id)
REFERENCES materia_prima (mp_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE pedido_detalle_materia_prima ADD CONSTRAINT materia_prima_pedido_materia_detalle_produccion_fk
FOREIGN KEY (mp_id)
REFERENCES materia_prima (mp_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE receta_detalle ADD CONSTRAINT materia_prima_receta_detalle_fk
FOREIGN KEY (mp_id)
REFERENCES materia_prima (mp_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;

ALTER TABLE ajustes_detalle ADD CONSTRAINT stock_materia_prima_ajustes_detallle_fk
FOREIGN KEY (stock_id)
REFERENCES stock_materia_prima (stock_id)
ON DELETE NO ACTION
ON UPDATE NO ACTION
NOT DEFERRABLE;
-- Bitácora general e intentos de acceso. 
CREATE TABLE bitacora (
    bitacora_id INTEGER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
    id_usuario INTEGER,
    fecha_hora TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    entidad_afectada VARCHAR NOT NULL,
    id_registro INTEGER,
    accion_realizada VARCHAR NOT NULL,
    descripcion_cambio TEXT NOT NULL,
    CONSTRAINT bitacora_usuario_fk FOREIGN KEY (id_usuario)
        REFERENCES usuario (usuario_id),
    CONSTRAINT ck_bitacora_accion CHECK (
        accion_realizada IN (
            'ALTA', 'MODIFICACION', 'ELIMINACION', 'INACTIVACION', 'ANULACION',
            'ACCESO_EXITOSO', 'ACCESO_FALLIDO', 'CUENTA_BLOQUEADA', 'ACCESO_DENEGADO'
        )
    )
);

CREATE INDEX bitacora_entidad_fecha_idx ON bitacora (entidad_afectada, fecha_hora);

-- Sesiones recordadas: 
CREATE TABLE sesion_recordada (
    sesion_recordada_id INTEGER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
    usuario_id INTEGER NOT NULL REFERENCES usuario (usuario_id) ON DELETE CASCADE,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    fecha_vencimiento TIMESTAMP WITH TIME ZONE NOT NULL
);
