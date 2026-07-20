--
-- PostgreSQL database dump
--

-- Dumped from database version 16.2
-- Dumped by pg_dump version 16.2

-- Started on 2026-06-24 21:07:17

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- TOC entry 323 (class 1255 OID 66043)
-- Name: fn_pedido_produccion_ultima_modificacion(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.fn_pedido_produccion_ultima_modificacion() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.pedido_prod_ultima_modificacion = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.fn_pedido_produccion_ultima_modificacion() OWNER TO postgres;

--
-- TOC entry 321 (class 1255 OID 44142)
-- Name: fn_pedidos_compra_ultima_modificacion(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.fn_pedidos_compra_ultima_modificacion() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.pedido_ultima_modificacion = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.fn_pedidos_compra_ultima_modificacion() OWNER TO postgres;

--
-- TOC entry 322 (class 1255 OID 44153)
-- Name: fn_presupuesto_compra_ultima_modificacion(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.fn_presupuesto_compra_ultima_modificacion() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.presu_ultima_modificacion = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.fn_presupuesto_compra_ultima_modificacion() OWNER TO postgres;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- TOC entry 249 (class 1259 OID 37776)
-- Name: ajustes; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.ajustes (
    id_ajuste integer NOT NULL,
    ajuste_fecha date NOT NULL,
    ajuste_estado character varying(20) NOT NULL,
    id_usuario integer NOT NULL,
    deposito_id integer NOT NULL
);


ALTER TABLE public.ajustes OWNER TO postgres;

--
-- TOC entry 248 (class 1259 OID 37775)
-- Name: ajuste_stock_id_ajuste_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.ajuste_stock_id_ajuste_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.ajuste_stock_id_ajuste_seq OWNER TO postgres;

--
-- TOC entry 5617 (class 0 OID 0)
-- Dependencies: 248
-- Name: ajuste_stock_id_ajuste_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.ajuste_stock_id_ajuste_seq OWNED BY public.ajustes.id_ajuste;


--
-- TOC entry 307 (class 1259 OID 38126)
-- Name: ajustes_detalle; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.ajustes_detalle (
    id_materia_prima integer NOT NULL,
    id_ajuste integer NOT NULL,
    id_stock integer NOT NULL,
    ajuste_cantidad integer NOT NULL,
    id_motivo integer NOT NULL
);


ALTER TABLE public.ajustes_detalle OWNER TO postgres;

--
-- TOC entry 311 (class 1259 OID 39041)
-- Name: bitacora; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.bitacora (
    id_bitacora integer NOT NULL,
    id_usuario integer NOT NULL,
    entidad_afectada character varying(50) NOT NULL,
    id_registro integer,
    accion_realizada character varying(30) NOT NULL,
    descripcion_cambio text,
    fecha_accion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    ip_origen character varying(45),
    navegador_usuario character varying(200),
    estado_registro character varying(10) DEFAULT 'ACTIVO'::character varying,
    CONSTRAINT ck_bitacora_accion CHECK (((accion_realizada)::text = ANY ((ARRAY['ALTA'::character varying, 'MODIFICACION'::character varying, 'ELIMINACION'::character varying, 'INACTIVACION'::character varying])::text[])))
);


ALTER TABLE public.bitacora OWNER TO postgres;

--
-- TOC entry 310 (class 1259 OID 39040)
-- Name: bitacora_id_bitacora_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.bitacora_id_bitacora_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.bitacora_id_bitacora_seq OWNER TO postgres;

--
-- TOC entry 5618 (class 0 OID 0)
-- Dependencies: 310
-- Name: bitacora_id_bitacora_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.bitacora_id_bitacora_seq OWNED BY public.bitacora.id_bitacora;


--
-- TOC entry 247 (class 1259 OID 37769)
-- Name: cargos; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cargos (
    id_cargo integer NOT NULL,
    cargo_descripcion character varying(30) NOT NULL,
    estado_cargo character varying(30) NOT NULL,
    id_usuario integer
);


ALTER TABLE public.cargos OWNER TO postgres;

--
-- TOC entry 246 (class 1259 OID 37768)
-- Name: cargos_id_cargo_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.cargos_id_cargo_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.cargos_id_cargo_seq OWNER TO postgres;

--
-- TOC entry 5619 (class 0 OID 0)
-- Dependencies: 246
-- Name: cargos_id_cargo_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.cargos_id_cargo_seq OWNED BY public.cargos.id_cargo;


--
-- TOC entry 232 (class 1259 OID 37682)
-- Name: conductores; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.conductores (
    conductor_id integer NOT NULL,
    conductor_nombre character varying(30) NOT NULL,
    conductor_apellido character varying(30) NOT NULL,
    conductor_telefono character varying(30) NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.conductores OWNER TO postgres;

--
-- TOC entry 231 (class 1259 OID 37681)
-- Name: conductores_conductor_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.conductores_conductor_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.conductores_conductor_id_seq OWNER TO postgres;

--
-- TOC entry 5620 (class 0 OID 0)
-- Dependencies: 231
-- Name: conductores_conductor_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.conductores_conductor_id_seq OWNED BY public.conductores.conductor_id;


--
-- TOC entry 293 (class 1259 OID 38059)
-- Name: control_calidad_detalle; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.control_calidad_detalle (
    calidad_id integer NOT NULL,
    producto_id integer NOT NULL,
    calidad_estado character varying(30) NOT NULL,
    calidad_cantidad integer NOT NULL,
    parametro_id integer NOT NULL,
    valor_medido character varying(100),
    cumple_parametro boolean
);


ALTER TABLE public.control_calidad_detalle OWNER TO postgres;

--
-- TOC entry 267 (class 1259 OID 37855)
-- Name: control_calidad_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.control_calidad_produccion (
    calidad_id integer NOT NULL,
    calidad_fecha date NOT NULL,
    calidad_estado character varying(30) NOT NULL,
    id_inspectores integer NOT NULL,
    id_usuario integer NOT NULL,
    terminado_id integer NOT NULL
);


ALTER TABLE public.control_calidad_produccion OWNER TO postgres;

--
-- TOC entry 266 (class 1259 OID 37854)
-- Name: control_calidad_produccion_calidad_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.control_calidad_produccion_calidad_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.control_calidad_produccion_calidad_id_seq OWNER TO postgres;

--
-- TOC entry 5621 (class 0 OID 0)
-- Dependencies: 266
-- Name: control_calidad_produccion_calidad_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.control_calidad_produccion_calidad_id_seq OWNED BY public.control_calidad_produccion.calidad_id;


--
-- TOC entry 269 (class 1259 OID 37862)
-- Name: control_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.control_produccion (
    control_id integer NOT NULL,
    control_fecha date NOT NULL,
    control_estado character varying(30) NOT NULL,
    id_inspectores integer NOT NULL,
    orden_id integer NOT NULL,
    id_usuario integer NOT NULL,
    producto_id integer,
    etapa_id integer,
    control_observacion text,
    CONSTRAINT control_produccion_estado_chk CHECK (((control_estado)::text = ANY ((ARRAY['REGISTRADO'::character varying, 'ANULADO'::character varying])::text[])))
);


ALTER TABLE public.control_produccion OWNER TO postgres;

--
-- TOC entry 319 (class 1259 OID 66159)
-- Name: control_produccion_consumo; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.control_produccion_consumo (
    control_id integer NOT NULL,
    id_materia_prima integer NOT NULL,
    cantidad_consumida numeric(12,3) NOT NULL,
    CONSTRAINT control_produccion_consumo_cant_chk CHECK ((cantidad_consumida > (0)::numeric))
);


ALTER TABLE public.control_produccion_consumo OWNER TO postgres;

--
-- TOC entry 5622 (class 0 OID 0)
-- Dependencies: 319
-- Name: TABLE control_produccion_consumo; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.control_produccion_consumo IS 'Materia prima consumida al registrar avance en control de producción.';


--
-- TOC entry 268 (class 1259 OID 37861)
-- Name: control_produccion_control_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.control_produccion_control_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.control_produccion_control_id_seq OWNER TO postgres;

--
-- TOC entry 5623 (class 0 OID 0)
-- Dependencies: 268
-- Name: control_produccion_control_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.control_produccion_control_id_seq OWNED BY public.control_produccion.control_id;


--
-- TOC entry 289 (class 1259 OID 38018)
-- Name: control_produccion_detalle; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.control_produccion_detalle (
    control_id integer NOT NULL,
    producto_id integer NOT NULL,
    control_cantidad integer NOT NULL,
    control_descri character varying(30) NOT NULL
);


ALTER TABLE public.control_produccion_detalle OWNER TO postgres;

--
-- TOC entry 298 (class 1259 OID 38078)
-- Name: costo_detalle_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.costo_detalle_produccion (
    costo_id integer NOT NULL,
    id_materia_prima integer,
    costo_cantidad integer NOT NULL,
    costo_precio integer NOT NULL,
    costo_detalle_id integer NOT NULL,
    costo_tipo character varying(10) DEFAULT 'MP'::character varying NOT NULL,
    trabajadores_id integer,
    costo_concepto character varying(150),
    CONSTRAINT costo_detalle_tipo_chk CHECK (((costo_tipo)::text = ANY ((ARRAY['MP'::character varying, 'MO'::character varying, 'CIF'::character varying])::text[]))),
    CONSTRAINT costo_detalle_tipo_mp_chk CHECK (((((costo_tipo)::text = 'MP'::text) AND (id_materia_prima IS NOT NULL)) OR ((costo_tipo)::text = ANY ((ARRAY['MO'::character varying, 'CIF'::character varying])::text[]))))
);


ALTER TABLE public.costo_detalle_produccion OWNER TO postgres;

--
-- TOC entry 318 (class 1259 OID 66113)
-- Name: costo_detalle_produccion_costo_detalle_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.costo_detalle_produccion_costo_detalle_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.costo_detalle_produccion_costo_detalle_id_seq OWNER TO postgres;

--
-- TOC entry 5624 (class 0 OID 0)
-- Dependencies: 318
-- Name: costo_detalle_produccion_costo_detalle_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.costo_detalle_produccion_costo_detalle_id_seq OWNED BY public.costo_detalle_produccion.costo_detalle_id;


--
-- TOC entry 255 (class 1259 OID 37806)
-- Name: costo_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.costo_produccion (
    costo_id integer NOT NULL,
    costo_fecha date NOT NULL,
    costo_estado character varying(30) NOT NULL,
    costo_total integer NOT NULL,
    id_usuario integer NOT NULL,
    orden_id integer,
    CONSTRAINT costo_produccion_estado_chk CHECK (((costo_estado)::text = ANY ((ARRAY['PENDIENTE'::character varying, 'CERRADO'::character varying, 'ANULADO'::character varying])::text[])))
);


ALTER TABLE public.costo_produccion OWNER TO postgres;

--
-- TOC entry 5625 (class 0 OID 0)
-- Dependencies: 255
-- Name: COLUMN costo_produccion.orden_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.costo_produccion.orden_id IS 'Orden de producción costeada (MP + MO + CIF).';


--
-- TOC entry 254 (class 1259 OID 37805)
-- Name: costo_produccion_costo_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.costo_produccion_costo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.costo_produccion_costo_id_seq OWNER TO postgres;

--
-- TOC entry 5626 (class 0 OID 0)
-- Dependencies: 254
-- Name: costo_produccion_costo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.costo_produccion_costo_id_seq OWNED BY public.costo_produccion.costo_id;


--
-- TOC entry 251 (class 1259 OID 37783)
-- Name: cuentas_pagar; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cuentas_pagar (
    id_cuenta_pagar integer NOT NULL,
    monto_total integer NOT NULL,
    monto_pendiente integer NOT NULL,
    estado character varying(15) NOT NULL,
    fecha_emision date NOT NULL,
    fecha_vencimiento date NOT NULL,
    id_sucursal integer NOT NULL,
    id_usuario integer NOT NULL,
    id_proveedor integer NOT NULL,
    id_factura_compra integer,
    plazo_cuenta integer DEFAULT 0 NOT NULL,
    nro_cuota integer DEFAULT 0 NOT NULL
);


ALTER TABLE public.cuentas_pagar OWNER TO postgres;

--
-- TOC entry 250 (class 1259 OID 37782)
-- Name: cuentas_pagar_id_cuenta_pagar_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.cuentas_pagar_id_cuenta_pagar_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.cuentas_pagar_id_cuenta_pagar_seq OWNER TO postgres;

--
-- TOC entry 5627 (class 0 OID 0)
-- Dependencies: 250
-- Name: cuentas_pagar_id_cuenta_pagar_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.cuentas_pagar_id_cuenta_pagar_seq OWNED BY public.cuentas_pagar.id_cuenta_pagar;


--
-- TOC entry 234 (class 1259 OID 37689)
-- Name: deposito; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.deposito (
    deposito_id integer NOT NULL,
    deposito_descri character varying(30) NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.deposito OWNER TO postgres;

--
-- TOC entry 233 (class 1259 OID 37688)
-- Name: deposito_deposito_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.deposito_deposito_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.deposito_deposito_id_seq OWNER TO postgres;

--
-- TOC entry 5628 (class 0 OID 0)
-- Dependencies: 233
-- Name: deposito_deposito_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.deposito_deposito_id_seq OWNED BY public.deposito.deposito_id;


--
-- TOC entry 239 (class 1259 OID 37716)
-- Name: equipo_detalle; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.equipo_detalle (
    equipo_id integer NOT NULL,
    trabajadores_id integer NOT NULL,
    tarea_rol character varying(100) DEFAULT 'Operario'::character varying NOT NULL
);


ALTER TABLE public.equipo_detalle OWNER TO postgres;

--
-- TOC entry 5629 (class 0 OID 0)
-- Dependencies: 239
-- Name: COLUMN equipo_detalle.tarea_rol; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.equipo_detalle.tarea_rol IS 'Rol o tarea del trabajador en la orden (armado, cocción, empaque, etc.).';


--
-- TOC entry 222 (class 1259 OID 37626)
-- Name: equipos_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.equipos_produccion (
    equipo_id integer NOT NULL,
    equipo_descri character varying(30) NOT NULL,
    id_usuario integer NOT NULL,
    equipo_estado character varying(30) NOT NULL,
    orden_id integer,
    equipo_fecha date DEFAULT CURRENT_DATE,
    id_sucursal integer,
    etapa_id integer,
    etapa_nombre character varying(50),
    CONSTRAINT equipos_produccion_estado_chk CHECK (((equipo_estado)::text = ANY ((ARRAY['PENDIENTE'::character varying, 'ACTIVO'::character varying, 'ANULADO'::character varying])::text[])))
);


ALTER TABLE public.equipos_produccion OWNER TO postgres;

--
-- TOC entry 5630 (class 0 OID 0)
-- Dependencies: 222
-- Name: COLUMN equipos_produccion.equipo_descri; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.equipos_produccion.equipo_descri IS 'Nombre del equipo (ej. Equipo OP-001 / Turno A).';


--
-- TOC entry 221 (class 1259 OID 37625)
-- Name: equipos_produccion_equipo_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.equipos_produccion_equipo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.equipos_produccion_equipo_id_seq OWNER TO postgres;

--
-- TOC entry 5631 (class 0 OID 0)
-- Dependencies: 221
-- Name: equipos_produccion_equipo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.equipos_produccion_equipo_id_seq OWNED BY public.equipos_produccion.equipo_id;


--
-- TOC entry 286 (class 1259 OID 38003)
-- Name: etapa_detalle_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.etapa_detalle_produccion (
    etapa_id integer NOT NULL,
    producto_id integer NOT NULL,
    etapa_nombre character varying(30) NOT NULL,
    etapa_procedimiento text NOT NULL,
    etapa_secuencia integer DEFAULT 1 NOT NULL,
    etapa_tiempo_estimado integer,
    etapa_observaciones text
);


ALTER TABLE public.etapa_detalle_produccion OWNER TO postgres;

--
-- TOC entry 5632 (class 0 OID 0)
-- Dependencies: 286
-- Name: COLUMN etapa_detalle_produccion.etapa_tiempo_estimado; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.etapa_detalle_produccion.etapa_tiempo_estimado IS 'Tiempo estimado en minutos para la etapa.';


--
-- TOC entry 220 (class 1259 OID 37619)
-- Name: etapa_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.etapa_produccion (
    etapa_id integer NOT NULL,
    etapa_fecha date NOT NULL,
    etapa_descri character varying(30) NOT NULL,
    id_usuario integer NOT NULL,
    producto_id integer,
    etapa_estado character varying(30) DEFAULT 'ACTIVA'::character varying NOT NULL,
    CONSTRAINT etapa_produccion_estado_chk CHECK (((etapa_estado)::text = ANY ((ARRAY['ACTIVA'::character varying, 'ANULADA'::character varying])::text[])))
);


ALTER TABLE public.etapa_produccion OWNER TO postgres;

--
-- TOC entry 219 (class 1259 OID 37618)
-- Name: etapa_produccion_etapa_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.etapa_produccion_etapa_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.etapa_produccion_etapa_id_seq OWNER TO postgres;

--
-- TOC entry 5633 (class 0 OID 0)
-- Dependencies: 219
-- Name: etapa_produccion_etapa_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.etapa_produccion_etapa_id_seq OWNED BY public.etapa_produccion.etapa_id;


--
-- TOC entry 273 (class 1259 OID 37918)
-- Name: factura_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.factura_compra (
    id_factura_compra integer NOT NULL,
    numero_factura character varying(30) NOT NULL,
    timbrado character varying(30) NOT NULL,
    fac_fecha_vencimiento date NOT NULL,
    fact_fecha_compra timestamp without time zone NOT NULL,
    fac_total integer NOT NULL,
    fac_estado character varying(20) NOT NULL,
    fac_plazo character varying(30) NOT NULL,
    fac_remision double precision NOT NULL,
    id_usuario integer NOT NULL,
    id_sucursal integer NOT NULL,
    id_orden_compra integer NOT NULL,
    tipo_operacion character varying(30)
);


ALTER TABLE public.factura_compra OWNER TO postgres;

--
-- TOC entry 272 (class 1259 OID 37917)
-- Name: factura_compra_id_factura_compra_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.factura_compra_id_factura_compra_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.factura_compra_id_factura_compra_seq OWNER TO postgres;

--
-- TOC entry 5634 (class 0 OID 0)
-- Dependencies: 272
-- Name: factura_compra_id_factura_compra_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.factura_compra_id_factura_compra_seq OWNED BY public.factura_compra.id_factura_compra;


--
-- TOC entry 304 (class 1259 OID 38112)
-- Name: factura_detalle_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.factura_detalle_compra (
    id_materia_prima integer NOT NULL,
    id_factura_compra integer NOT NULL,
    fac_cantidad integer NOT NULL,
    fac_iva numeric NOT NULL,
    fac_precio integer NOT NULL
);


ALTER TABLE public.factura_detalle_compra OWNER TO postgres;

--
-- TOC entry 236 (class 1259 OID 37703)
-- Name: inspectores; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.inspectores (
    id_inspectores integer NOT NULL,
    id_personal integer NOT NULL,
    inspector_estado character varying(30) NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.inspectores OWNER TO postgres;

--
-- TOC entry 235 (class 1259 OID 37702)
-- Name: inspectores_id_inspectores_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.inspectores_id_inspectores_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.inspectores_id_inspectores_seq OWNER TO postgres;

--
-- TOC entry 5635 (class 0 OID 0)
-- Dependencies: 235
-- Name: inspectores_id_inspectores_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.inspectores_id_inspectores_seq OWNED BY public.inspectores.id_inspectores;


--
-- TOC entry 275 (class 1259 OID 37925)
-- Name: iva_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.iva_compra (
    iva_id integer NOT NULL,
    id_factura_compra integer NOT NULL,
    iva_fecha date NOT NULL,
    iva_exento numeric NOT NULL,
    iva_5 numeric NOT NULL,
    iva_10 numeric NOT NULL
);


ALTER TABLE public.iva_compra OWNER TO postgres;

--
-- TOC entry 274 (class 1259 OID 37924)
-- Name: iva_compra_id_libro_compra_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.iva_compra_id_libro_compra_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.iva_compra_id_libro_compra_seq OWNER TO postgres;

--
-- TOC entry 5636 (class 0 OID 0)
-- Dependencies: 274
-- Name: iva_compra_id_libro_compra_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.iva_compra_id_libro_compra_seq OWNED BY public.iva_compra.iva_id;


--
-- TOC entry 297 (class 1259 OID 38072)
-- Name: materia_prima; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.materia_prima (
    id_materia_prima integer NOT NULL,
    materia_prima_descripcion character varying(30) NOT NULL,
    materia_prima_estado character varying(30) NOT NULL,
    id_unidad integer NOT NULL,
    id_usuario integer NOT NULL,
    iva_id integer NOT NULL
);


ALTER TABLE public.materia_prima OWNER TO postgres;

--
-- TOC entry 296 (class 1259 OID 38071)
-- Name: materia_prima_id_materia_prima_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.materia_prima_id_materia_prima_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.materia_prima_id_materia_prima_seq OWNER TO postgres;

--
-- TOC entry 5637 (class 0 OID 0)
-- Dependencies: 296
-- Name: materia_prima_id_materia_prima_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.materia_prima_id_materia_prima_seq OWNED BY public.materia_prima.id_materia_prima;


--
-- TOC entry 241 (class 1259 OID 37722)
-- Name: modulos; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.modulos (
    modulo_id integer NOT NULL,
    modulo_descri character varying(30) NOT NULL,
    id_usuario integer
);


ALTER TABLE public.modulos OWNER TO postgres;

--
-- TOC entry 240 (class 1259 OID 37721)
-- Name: modulos_modulo_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.modulos_modulo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.modulos_modulo_id_seq OWNER TO postgres;

--
-- TOC entry 5638 (class 0 OID 0)
-- Dependencies: 240
-- Name: modulos_modulo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.modulos_modulo_id_seq OWNED BY public.modulos.modulo_id;


--
-- TOC entry 279 (class 1259 OID 37969)
-- Name: motivo; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.motivo (
    id_motivo integer NOT NULL,
    motivo_descripcion character varying(30) NOT NULL,
    id_usuario integer NOT NULL,
    categoria_motivo character varying(20) DEFAULT 'NOTA_CREDITO'::character varying
);


ALTER TABLE public.motivo OWNER TO postgres;

--
-- TOC entry 5639 (class 0 OID 0)
-- Dependencies: 279
-- Name: COLUMN motivo.categoria_motivo; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.motivo.categoria_motivo IS 'Categoría del motivo: AJUSTE (para ajustes de inventario), NOTA_CREDITO (para notas de crédito/débito)';


--
-- TOC entry 278 (class 1259 OID 37968)
-- Name: motivo_id_motivo_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.motivo_id_motivo_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.motivo_id_motivo_seq OWNER TO postgres;

--
-- TOC entry 5640 (class 0 OID 0)
-- Dependencies: 278
-- Name: motivo_id_motivo_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.motivo_id_motivo_seq OWNED BY public.motivo.id_motivo;


--
-- TOC entry 281 (class 1259 OID 37983)
-- Name: nota_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.nota_compra (
    id_nota_compra integer NOT NULL,
    nota_compra_tipo character varying(50) NOT NULL,
    nota_compra_fecha timestamp without time zone NOT NULL,
    nota_nro bigint NOT NULL,
    nota_compra_timbrado character varying(30) NOT NULL,
    nota_compra_inicio date NOT NULL,
    nota_compra_vencimiento date NOT NULL,
    nota_compra_estado character varying(20) NOT NULL,
    nota_total integer NOT NULL,
    id_usuario integer NOT NULL,
    id_sucursal integer NOT NULL,
    id_proveedor integer NOT NULL,
    id_motivo integer NOT NULL,
    id_factura_compra integer
);


ALTER TABLE public.nota_compra OWNER TO postgres;

--
-- TOC entry 280 (class 1259 OID 37982)
-- Name: nota_compra_id_nota_compra_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.nota_compra_id_nota_compra_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.nota_compra_id_nota_compra_seq OWNER TO postgres;

--
-- TOC entry 5641 (class 0 OID 0)
-- Dependencies: 280
-- Name: nota_compra_id_nota_compra_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.nota_compra_id_nota_compra_seq OWNED BY public.nota_compra.id_nota_compra;


--
-- TOC entry 303 (class 1259 OID 38105)
-- Name: nota_detalle_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.nota_detalle_compra (
    id_materia_prima integer NOT NULL,
    id_nota_compra integer NOT NULL,
    nota_compra_cantidad integer NOT NULL,
    tipo_iva numeric NOT NULL,
    nota_precio integer NOT NULL
);


ALTER TABLE public.nota_detalle_compra OWNER TO postgres;

--
-- TOC entry 277 (class 1259 OID 37934)
-- Name: nota_remision_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.nota_remision_compra (
    id_nota_remision integer NOT NULL,
    id_factura_compra integer,
    nota_fecha timestamp without time zone NOT NULL,
    nota_remision_total integer NOT NULL,
    nota_remision_nro character varying(20) NOT NULL,
    nota_estado character varying(30) NOT NULL,
    id_usuario integer NOT NULL,
    id_proveedor integer NOT NULL,
    deposito_id integer NOT NULL,
    conductor_id integer NOT NULL,
    vehiculo_id integer NOT NULL,
    id_orden_compra integer
);


ALTER TABLE public.nota_remision_compra OWNER TO postgres;

--
-- TOC entry 5642 (class 0 OID 0)
-- Dependencies: 277
-- Name: COLUMN nota_remision_compra.nota_remision_nro; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.nota_remision_compra.nota_remision_nro IS 'Número legal de remisión. Puede ser formato EEE-PPP-NNNNNNN o 13 dígitos.';


--
-- TOC entry 5643 (class 0 OID 0)
-- Dependencies: 277
-- Name: COLUMN nota_remision_compra.id_orden_compra; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.nota_remision_compra.id_orden_compra IS 'Orden de Compra asociada directamente. Permite acceso sin pasar por factura_compra.';


--
-- TOC entry 276 (class 1259 OID 37933)
-- Name: nota_remision_compra_id_nota_remision_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.nota_remision_compra_id_nota_remision_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.nota_remision_compra_id_nota_remision_seq OWNER TO postgres;

--
-- TOC entry 5644 (class 0 OID 0)
-- Dependencies: 276
-- Name: nota_remision_compra_id_nota_remision_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.nota_remision_compra_id_nota_remision_seq OWNED BY public.nota_remision_compra.id_nota_remision;


--
-- TOC entry 302 (class 1259 OID 38098)
-- Name: nota_remision_detalle_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.nota_remision_detalle_compra (
    id_nota_remision integer NOT NULL,
    id_materia_prima integer NOT NULL,
    nota_cantidad integer NOT NULL,
    nota_remi_iva numeric NOT NULL
);


ALTER TABLE public.nota_remision_detalle_compra OWNER TO postgres;

--
-- TOC entry 261 (class 1259 OID 37827)
-- Name: orden_de_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.orden_de_compra (
    id_orden_compra integer NOT NULL,
    orden_fecha timestamp without time zone NOT NULL,
    orden_estado character varying(30) NOT NULL,
    orden_total integer NOT NULL,
    id_presupuesto_compra integer NOT NULL,
    id_proveedor integer NOT NULL,
    id_sucursal integer NOT NULL,
    id_usuario integer NOT NULL,
    orden_condicion character varying(20) DEFAULT 'CONTADO'::character varying NOT NULL
);


ALTER TABLE public.orden_de_compra OWNER TO postgres;

--
-- TOC entry 5645 (class 0 OID 0)
-- Dependencies: 261
-- Name: COLUMN orden_de_compra.orden_condicion; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.orden_de_compra.orden_condicion IS 'Condición de pago: CONTADO o CREDITO';


--
-- TOC entry 260 (class 1259 OID 37826)
-- Name: orden_de_compra_id_orden_compra_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.orden_de_compra_id_orden_compra_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.orden_de_compra_id_orden_compra_seq OWNER TO postgres;

--
-- TOC entry 5646 (class 0 OID 0)
-- Dependencies: 260
-- Name: orden_de_compra_id_orden_compra_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.orden_de_compra_id_orden_compra_seq OWNED BY public.orden_de_compra.id_orden_compra;


--
-- TOC entry 308 (class 1259 OID 38131)
-- Name: orden_detalle_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.orden_detalle_compra (
    id_materia_prima integer NOT NULL,
    id_orden_compra integer NOT NULL,
    oc_cantidad_compra integer NOT NULL,
    oc_precio_compra integer NOT NULL
);


ALTER TABLE public.orden_detalle_compra OWNER TO postgres;

--
-- TOC entry 290 (class 1259 OID 38023)
-- Name: orden_detalle_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.orden_detalle_produccion (
    orden_id integer NOT NULL,
    producto_id integer NOT NULL,
    orden_prod_cantidad integer NOT NULL,
    cantidad_pendiente integer
);


ALTER TABLE public.orden_detalle_produccion OWNER TO postgres;

--
-- TOC entry 5647 (class 0 OID 0)
-- Dependencies: 290
-- Name: COLUMN orden_detalle_produccion.cantidad_pendiente; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.orden_detalle_produccion.cantidad_pendiente IS 'Cantidad aún no procesada en control de producción / PT.';


--
-- TOC entry 263 (class 1259 OID 37841)
-- Name: orden_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.orden_produccion (
    orden_id integer NOT NULL,
    orden_prod_fecha date NOT NULL,
    orden_prod_fecha_entrega date,
    orden_prod_estado character varying(30) NOT NULL,
    id_usuario integer NOT NULL,
    id_pedido_produccion integer NOT NULL,
    CONSTRAINT orden_produccion_estado_chk CHECK (((orden_prod_estado)::text = ANY ((ARRAY['PENDIENTE'::character varying, 'EN_PROCESO'::character varying, 'TERMINADA'::character varying, 'ANULADA'::character varying])::text[])))
);


ALTER TABLE public.orden_produccion OWNER TO postgres;

--
-- TOC entry 5648 (class 0 OID 0)
-- Dependencies: 263
-- Name: TABLE orden_produccion; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.orden_produccion IS 'Orden de producción generada desde pedido_produccion.';


--
-- TOC entry 5649 (class 0 OID 0)
-- Dependencies: 263
-- Name: COLUMN orden_produccion.id_pedido_produccion; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.orden_produccion.id_pedido_produccion IS 'Pedido de producción que origina esta orden (flujo: pedido → orden → control / PT).';


--
-- TOC entry 262 (class 1259 OID 37840)
-- Name: orden_produccion_orden_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.orden_produccion_orden_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.orden_produccion_orden_id_seq OWNER TO postgres;

--
-- TOC entry 5650 (class 0 OID 0)
-- Dependencies: 262
-- Name: orden_produccion_orden_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.orden_produccion_orden_id_seq OWNED BY public.orden_produccion.orden_id;


--
-- TOC entry 292 (class 1259 OID 38053)
-- Name: parametros_control; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.parametros_control (
    parametro_id integer NOT NULL,
    parametro_descri character varying(30) NOT NULL,
    producto_id integer NOT NULL,
    id_usuario integer NOT NULL,
    parametro_estado character varying(30) DEFAULT 'ACTIVO'::character varying NOT NULL,
    CONSTRAINT parametros_control_estado_chk CHECK (((parametro_estado)::text = ANY ((ARRAY['ACTIVO'::character varying, 'INACTIVO'::character varying])::text[])))
);


ALTER TABLE public.parametros_control OWNER TO postgres;

--
-- TOC entry 291 (class 1259 OID 38052)
-- Name: parametros_control_parametro_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.parametros_control_parametro_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.parametros_control_parametro_id_seq OWNER TO postgres;

--
-- TOC entry 5651 (class 0 OID 0)
-- Dependencies: 291
-- Name: parametros_control_parametro_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.parametros_control_parametro_id_seq OWNED BY public.parametros_control.parametro_id;


--
-- TOC entry 301 (class 1259 OID 38093)
-- Name: pedido_detalle_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pedido_detalle_compra (
    id_pedido_compra integer NOT NULL,
    id_materia_prima integer NOT NULL,
    cantidad_pedido integer NOT NULL
);


ALTER TABLE public.pedido_detalle_compra OWNER TO postgres;

--
-- TOC entry 317 (class 1259 OID 66012)
-- Name: pedido_detalle_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pedido_detalle_produccion (
    id_pedido_produccion integer NOT NULL,
    producto_id integer NOT NULL,
    cantidad_pedido integer NOT NULL,
    CONSTRAINT pedido_detalle_produccion_cantidad_chk CHECK ((cantidad_pedido > 0))
);


ALTER TABLE public.pedido_detalle_produccion OWNER TO postgres;

--
-- TOC entry 5652 (class 0 OID 0)
-- Dependencies: 317
-- Name: TABLE pedido_detalle_produccion; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.pedido_detalle_produccion IS 'Ítems del pedido: producto terminado a producir y cantidad solicitada.';


--
-- TOC entry 300 (class 1259 OID 38088)
-- Name: pedido_materia_detalle_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pedido_materia_detalle_produccion (
    id_pedido_mat_prod integer NOT NULL,
    id_materia_prima integer NOT NULL,
    ped_mat_prod_cantidad integer NOT NULL,
    cantidad_repuesta integer DEFAULT 0 NOT NULL,
    CONSTRAINT pedido_materia_detalle_cantidad_chk CHECK (((ped_mat_prod_cantidad > 0) AND (cantidad_repuesta >= 0) AND (cantidad_repuesta <= ped_mat_prod_cantidad)))
);


ALTER TABLE public.pedido_materia_detalle_produccion OWNER TO postgres;

--
-- TOC entry 5653 (class 0 OID 0)
-- Dependencies: 300
-- Name: COLUMN pedido_materia_detalle_produccion.ped_mat_prod_cantidad; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pedido_materia_detalle_produccion.ped_mat_prod_cantidad IS 'Cantidad solicitada.';


--
-- TOC entry 5654 (class 0 OID 0)
-- Dependencies: 300
-- Name: COLUMN pedido_materia_detalle_produccion.cantidad_repuesta; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pedido_materia_detalle_produccion.cantidad_repuesta IS 'Cantidad ya repuesta vía reposicion_materia.';


--
-- TOC entry 228 (class 1259 OID 37668)
-- Name: pedido_materia_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pedido_materia_produccion (
    id_pedido_mat_prod integer NOT NULL,
    ped_mat_prod_fecha date NOT NULL,
    ped_mat_prod_estado character varying(30) NOT NULL,
    id_usuario integer NOT NULL,
    id_sucursal integer NOT NULL,
    deposito_id integer NOT NULL,
    CONSTRAINT pedido_materia_produccion_estado_chk CHECK (((ped_mat_prod_estado)::text = ANY ((ARRAY['PENDIENTE'::character varying, 'PARCIAL'::character varying, 'COMPLETADO'::character varying, 'ANULADO'::character varying])::text[])))
);


ALTER TABLE public.pedido_materia_produccion OWNER TO postgres;

--
-- TOC entry 5655 (class 0 OID 0)
-- Dependencies: 228
-- Name: TABLE pedido_materia_produccion; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.pedido_materia_produccion IS 'Solicitud de MP hacia un depósito; no mueve stock hasta la reposición.';


--
-- TOC entry 227 (class 1259 OID 37667)
-- Name: pedido_materia_produccion_id_pedido_mat_prod_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pedido_materia_produccion_id_pedido_mat_prod_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pedido_materia_produccion_id_pedido_mat_prod_seq OWNER TO postgres;

--
-- TOC entry 5656 (class 0 OID 0)
-- Dependencies: 227
-- Name: pedido_materia_produccion_id_pedido_mat_prod_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pedido_materia_produccion_id_pedido_mat_prod_seq OWNED BY public.pedido_materia_produccion.id_pedido_mat_prod;


--
-- TOC entry 315 (class 1259 OID 66001)
-- Name: pedido_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pedido_produccion (
    id_pedido_produccion integer NOT NULL,
    pedido_prod_fecha_emision date NOT NULL,
    pedido_prod_estado character varying(30) NOT NULL,
    id_tipo_pedido integer NOT NULL,
    id_usuario integer NOT NULL,
    id_sucursal integer NOT NULL,
    pedido_prod_observaciones text,
    pedido_prod_ultima_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pedido_produccion_estado_chk CHECK (((pedido_prod_estado)::text = ANY ((ARRAY['PENDIENTE'::character varying, 'ASIGNADO'::character varying, 'ANULADO'::character varying])::text[])))
);


ALTER TABLE public.pedido_produccion OWNER TO postgres;

--
-- TOC entry 5657 (class 0 OID 0)
-- Dependencies: 315
-- Name: TABLE pedido_produccion; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.pedido_produccion IS 'Inicio del flujo de producción: qué productos elaborar y en qué cantidad.';


--
-- TOC entry 5658 (class 0 OID 0)
-- Dependencies: 315
-- Name: COLUMN pedido_produccion.pedido_prod_estado; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pedido_produccion.pedido_prod_estado IS 'PENDIENTE → listo para generar OP; ASIGNADO → vinculado a orden_produccion; ANULADO.';


--
-- TOC entry 316 (class 1259 OID 66010)
-- Name: pedido_produccion_id_pedido_produccion_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pedido_produccion_id_pedido_produccion_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pedido_produccion_id_pedido_produccion_seq OWNER TO postgres;

--
-- TOC entry 5659 (class 0 OID 0)
-- Dependencies: 316
-- Name: pedido_produccion_id_pedido_produccion_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pedido_produccion_id_pedido_produccion_seq OWNED BY public.pedido_produccion.id_pedido_produccion;


--
-- TOC entry 253 (class 1259 OID 37799)
-- Name: pedidos_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.pedidos_compra (
    id_pedido_compra integer NOT NULL,
    pedido_fecha_emision timestamp without time zone NOT NULL,
    pedido_estado character varying(20) NOT NULL,
    id_usuario integer NOT NULL,
    id_sucursal integer NOT NULL,
    pedido_observaciones text,
    pedido_ultima_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.pedidos_compra OWNER TO postgres;

--
-- TOC entry 5660 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN pedidos_compra.pedido_observaciones; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pedidos_compra.pedido_observaciones IS 'Observaciones o notas adicionales del pedido de compra';


--
-- TOC entry 5661 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN pedidos_compra.pedido_ultima_modificacion; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.pedidos_compra.pedido_ultima_modificacion IS 'Timestamp de última modificación para control de concurrencia';


--
-- TOC entry 252 (class 1259 OID 37798)
-- Name: pedidos_compra_id_pedido_compra_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.pedidos_compra_id_pedido_compra_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.pedidos_compra_id_pedido_compra_seq OWNER TO postgres;

--
-- TOC entry 5662 (class 0 OID 0)
-- Dependencies: 252
-- Name: pedidos_compra_id_pedido_compra_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.pedidos_compra_id_pedido_compra_seq OWNED BY public.pedidos_compra.id_pedido_compra;


--
-- TOC entry 271 (class 1259 OID 37869)
-- Name: perdidas; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.perdidas (
    perdidas_id integer NOT NULL,
    perdida_estado character varying(30) NOT NULL,
    perdida_fecha date NOT NULL,
    tipo_perdida_id integer NOT NULL,
    calidad_id integer,
    control_id integer,
    id_usuario integer NOT NULL,
    CONSTRAINT perdidas_origen_chk CHECK ((((control_id IS NOT NULL) AND (calidad_id IS NULL)) OR ((control_id IS NULL) AND (calidad_id IS NOT NULL))))
);


ALTER TABLE public.perdidas OWNER TO postgres;

--
-- TOC entry 287 (class 1259 OID 38008)
-- Name: perdidas_detalle; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.perdidas_detalle (
    perdidas_id integer NOT NULL,
    producto_id integer NOT NULL,
    perdida_cantidad integer NOT NULL,
    perdida_motivo character varying(30) NOT NULL
);


ALTER TABLE public.perdidas_detalle OWNER TO postgres;

--
-- TOC entry 270 (class 1259 OID 37868)
-- Name: perdidas_perdidas_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.perdidas_perdidas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.perdidas_perdidas_id_seq OWNER TO postgres;

--
-- TOC entry 5663 (class 0 OID 0)
-- Dependencies: 270
-- Name: perdidas_perdidas_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.perdidas_perdidas_id_seq OWNED BY public.perdidas.perdidas_id;


--
-- TOC entry 216 (class 1259 OID 37605)
-- Name: personal; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.personal (
    id_personal integer NOT NULL,
    personal_estado character varying(30) NOT NULL,
    personal_nombre character varying(30) NOT NULL,
    personal_apellido character varying(30) NOT NULL,
    personal_telefono character varying(30) NOT NULL,
    personal_ci character varying(30) NOT NULL,
    id_cargo integer NOT NULL,
    id_usuario integer,
    id_sucursal integer
);


ALTER TABLE public.personal OWNER TO postgres;

--
-- TOC entry 5664 (class 0 OID 0)
-- Dependencies: 216
-- Name: COLUMN personal.id_sucursal; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.personal.id_sucursal IS 'ID de la sucursal a la que pertenece el personal';


--
-- TOC entry 215 (class 1259 OID 37604)
-- Name: personal_id_personal_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.personal_id_personal_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.personal_id_personal_seq OWNER TO postgres;

--
-- TOC entry 5665 (class 0 OID 0)
-- Dependencies: 215
-- Name: personal_id_personal_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.personal_id_personal_seq OWNED BY public.personal.id_personal;


--
-- TOC entry 259 (class 1259 OID 37820)
-- Name: presupuesto_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.presupuesto_compra (
    id_presupuesto_compra integer NOT NULL,
    presu_total integer NOT NULL,
    presu_fecha timestamp without time zone NOT NULL,
    presu_estado character varying(20) NOT NULL,
    id_pedido_compra integer NOT NULL,
    id_usuario integer NOT NULL,
    id_sucursal integer NOT NULL,
    id_proveedor integer NOT NULL,
    descuento_total numeric(12,2) DEFAULT 0,
    presu_observaciones text,
    presu_ultima_modificacion timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT presupuesto_compra_descuento_total_check CHECK ((descuento_total >= (0)::numeric))
);


ALTER TABLE public.presupuesto_compra OWNER TO postgres;

--
-- TOC entry 5666 (class 0 OID 0)
-- Dependencies: 259
-- Name: COLUMN presupuesto_compra.descuento_total; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.presupuesto_compra.descuento_total IS 'Descuento general aplicado al presupuesto (en la misma moneda que el total)';


--
-- TOC entry 5667 (class 0 OID 0)
-- Dependencies: 259
-- Name: COLUMN presupuesto_compra.presu_observaciones; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.presupuesto_compra.presu_observaciones IS 'Observaciones o notas adicionales del presupuesto';


--
-- TOC entry 5668 (class 0 OID 0)
-- Dependencies: 259
-- Name: COLUMN presupuesto_compra.presu_ultima_modificacion; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.presupuesto_compra.presu_ultima_modificacion IS 'Timestamp de última modificación para control de concurrencia';


--
-- TOC entry 258 (class 1259 OID 37819)
-- Name: presupuesto_compra_id_presupuesto_compra_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.presupuesto_compra_id_presupuesto_compra_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.presupuesto_compra_id_presupuesto_compra_seq OWNER TO postgres;

--
-- TOC entry 5669 (class 0 OID 0)
-- Dependencies: 258
-- Name: presupuesto_compra_id_presupuesto_compra_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.presupuesto_compra_id_presupuesto_compra_seq OWNED BY public.presupuesto_compra.id_presupuesto_compra;


--
-- TOC entry 309 (class 1259 OID 38136)
-- Name: presupuesto_detalle_compra; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.presupuesto_detalle_compra (
    id_presupuesto_compra integer NOT NULL,
    id_materia_prima integer NOT NULL,
    detalle_presu_cantidad integer NOT NULL,
    detalle_presu_precio_compra integer NOT NULL,
    descuento numeric(12,2) DEFAULT 0,
    detalle_presu_iva numeric(12,2) DEFAULT 0,
    CONSTRAINT presupuesto_detalle_compra_descuento_check CHECK ((descuento >= (0)::numeric)),
    CONSTRAINT presupuesto_detalle_compra_detalle_presu_iva_check CHECK ((detalle_presu_iva >= (0)::numeric))
);


ALTER TABLE public.presupuesto_detalle_compra OWNER TO postgres;

--
-- TOC entry 5670 (class 0 OID 0)
-- Dependencies: 309
-- Name: COLUMN presupuesto_detalle_compra.descuento; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.presupuesto_detalle_compra.descuento IS 'Descuento aplicado al ítem (en la misma moneda que el precio)';


--
-- TOC entry 5671 (class 0 OID 0)
-- Dependencies: 309
-- Name: COLUMN presupuesto_detalle_compra.detalle_presu_iva; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.presupuesto_detalle_compra.detalle_presu_iva IS 'Monto de IVA calculado para el ítem';


--
-- TOC entry 265 (class 1259 OID 37848)
-- Name: producto_terminado; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.producto_terminado (
    terminado_id integer NOT NULL,
    orden_id integer NOT NULL,
    terminado_fecha date NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.producto_terminado OWNER TO postgres;

--
-- TOC entry 264 (class 1259 OID 37847)
-- Name: producto_terminado_terminado_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.producto_terminado_terminado_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.producto_terminado_terminado_id_seq OWNER TO postgres;

--
-- TOC entry 5672 (class 0 OID 0)
-- Dependencies: 264
-- Name: producto_terminado_terminado_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.producto_terminado_terminado_id_seq OWNED BY public.producto_terminado.terminado_id;


--
-- TOC entry 285 (class 1259 OID 37997)
-- Name: productos; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.productos (
    producto_id integer NOT NULL,
    producto_precio integer NOT NULL,
    producto_descri character varying(30) NOT NULL,
    id_unidad integer NOT NULL,
    id_usuario integer NOT NULL,
    iva_id integer,
    producto_estado character varying(30) DEFAULT 'ACTIVO'::character varying,
    id_tipo_producto integer
);


ALTER TABLE public.productos OWNER TO postgres;

--
-- TOC entry 5673 (class 0 OID 0)
-- Dependencies: 285
-- Name: COLUMN productos.iva_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.productos.iva_id IS 'Tipo de IVA aplicable (5%, 10%, exento)';


--
-- TOC entry 5674 (class 0 OID 0)
-- Dependencies: 285
-- Name: COLUMN productos.producto_estado; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.productos.producto_estado IS 'Estado del producto: ACTIVO, INACTIVO, ANULADO';


--
-- TOC entry 5675 (class 0 OID 0)
-- Dependencies: 285
-- Name: COLUMN productos.id_tipo_producto; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.productos.id_tipo_producto IS 'Tipo de producto (opcional)';


--
-- TOC entry 284 (class 1259 OID 37996)
-- Name: productos_producto_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.productos_producto_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.productos_producto_id_seq OWNER TO postgres;

--
-- TOC entry 5676 (class 0 OID 0)
-- Dependencies: 284
-- Name: productos_producto_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.productos_producto_id_seq OWNED BY public.productos.producto_id;


--
-- TOC entry 288 (class 1259 OID 38013)
-- Name: productos_terminados_detalle; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.productos_terminados_detalle (
    terminado_id integer NOT NULL,
    producto_id integer NOT NULL,
    terminado_cantidad integer NOT NULL,
    deposito_id integer,
    terminado_fecha_elab date,
    terminado_fecha_venc date
);


ALTER TABLE public.productos_terminados_detalle OWNER TO postgres;

--
-- TOC entry 243 (class 1259 OID 37736)
-- Name: proveedor; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.proveedor (
    id_proveedor integer NOT NULL,
    id_usuario integer NOT NULL,
    razon_social character varying(150) NOT NULL,
    ruc_proveedor character varying(20) NOT NULL,
    telefono_proveedor character varying(30),
    direccion_proveedor character varying(200),
    email_proveedor character varying(120),
    estado_proveedor character varying(10) DEFAULT 'ACTIVO'::character varying NOT NULL
);


ALTER TABLE public.proveedor OWNER TO postgres;

--
-- TOC entry 242 (class 1259 OID 37735)
-- Name: proveedor_id_proveedor_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.proveedor_id_proveedor_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.proveedor_id_proveedor_seq OWNER TO postgres;

--
-- TOC entry 5677 (class 0 OID 0)
-- Dependencies: 242
-- Name: proveedor_id_proveedor_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.proveedor_id_proveedor_seq OWNED BY public.proveedor.id_proveedor;


--
-- TOC entry 320 (class 1259 OID 66196)
-- Name: receta_produccion; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.receta_produccion (
    producto_id integer NOT NULL,
    id_materia_prima integer NOT NULL,
    cantidad_por_unidad numeric(12,3) NOT NULL,
    receta_estado character varying(30) DEFAULT 'ACTIVO'::character varying NOT NULL,
    id_usuario integer,
    CONSTRAINT receta_produccion_cant_chk CHECK ((cantidad_por_unidad > (0)::numeric)),
    CONSTRAINT receta_produccion_estado_chk CHECK (((receta_estado)::text = ANY (ARRAY[('ACTIVO'::character varying)::text, ('INACTIVO'::character varying)::text])))
);


ALTER TABLE public.receta_produccion OWNER TO postgres;

--
-- TOC entry 5678 (class 0 OID 0)
-- Dependencies: 320
-- Name: TABLE receta_produccion; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.receta_produccion IS 'Insumos (MP) por unidad de producto terminado — ficha técnica / receta.';


--
-- TOC entry 5679 (class 0 OID 0)
-- Dependencies: 320
-- Name: COLUMN receta_produccion.cantidad_por_unidad; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.receta_produccion.cantidad_por_unidad IS 'Cantidad de MP necesaria para elaborar 1 unidad del producto.';


--
-- TOC entry 257 (class 1259 OID 37813)
-- Name: reposicion_materia; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.reposicion_materia (
    reposicion_id integer NOT NULL,
    reposicion_fecha date NOT NULL,
    reposicion_estado character varying(30) NOT NULL,
    deposito_id integer NOT NULL,
    id_usuario integer NOT NULL,
    id_pedido_mat_prod integer,
    CONSTRAINT reposicion_materia_estado_chk CHECK (((reposicion_estado)::text = ANY ((ARRAY['REGISTRADO'::character varying, 'ANULADO'::character varying])::text[])))
);


ALTER TABLE public.reposicion_materia OWNER TO postgres;

--
-- TOC entry 5680 (class 0 OID 0)
-- Dependencies: 257
-- Name: COLUMN reposicion_materia.id_pedido_mat_prod; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.reposicion_materia.id_pedido_mat_prod IS 'Pedido de materia prima que se está atendiendo con esta reposición.';


--
-- TOC entry 299 (class 1259 OID 38083)
-- Name: reposicion_materia_detalle; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.reposicion_materia_detalle (
    reposicion_id integer NOT NULL,
    id_materia_prima integer NOT NULL,
    reposicion_cantidad integer NOT NULL
);


ALTER TABLE public.reposicion_materia_detalle OWNER TO postgres;

--
-- TOC entry 256 (class 1259 OID 37812)
-- Name: reposicion_materia_reposicion_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.reposicion_materia_reposicion_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.reposicion_materia_reposicion_id_seq OWNER TO postgres;

--
-- TOC entry 5681 (class 0 OID 0)
-- Dependencies: 256
-- Name: reposicion_materia_reposicion_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.reposicion_materia_reposicion_id_seq OWNED BY public.reposicion_materia.reposicion_id;


--
-- TOC entry 306 (class 1259 OID 38120)
-- Name: stock_materia_prima; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.stock_materia_prima (
    id_stock integer NOT NULL,
    id_materia_prima integer NOT NULL,
    stock_cantidad_minima integer NOT NULL,
    stock_cantidad_maxima integer NOT NULL,
    cantidad_existente integer NOT NULL,
    deposito_id integer NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.stock_materia_prima OWNER TO postgres;

--
-- TOC entry 305 (class 1259 OID 38119)
-- Name: stock_materia_prima_id_stock_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.stock_materia_prima_id_stock_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.stock_materia_prima_id_stock_seq OWNER TO postgres;

--
-- TOC entry 5682 (class 0 OID 0)
-- Dependencies: 305
-- Name: stock_materia_prima_id_stock_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.stock_materia_prima_id_stock_seq OWNED BY public.stock_materia_prima.id_stock;


--
-- TOC entry 295 (class 1259 OID 38065)
-- Name: stock_producto; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.stock_producto (
    id_stock_productos integer NOT NULL,
    producto_id integer NOT NULL,
    deposito_id integer NOT NULL,
    stock_prod_ven date NOT NULL,
    stock_prod_existente integer NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.stock_producto OWNER TO postgres;

--
-- TOC entry 294 (class 1259 OID 38064)
-- Name: stock_producto_id_stock_productos_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.stock_producto_id_stock_productos_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.stock_producto_id_stock_productos_seq OWNER TO postgres;

--
-- TOC entry 5683 (class 0 OID 0)
-- Dependencies: 294
-- Name: stock_producto_id_stock_productos_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.stock_producto_id_stock_productos_seq OWNED BY public.stock_producto.id_stock_productos;


--
-- TOC entry 245 (class 1259 OID 37750)
-- Name: sucursales; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sucursales (
    id_sucursal integer NOT NULL,
    descripcion_sucursal character varying(30) NOT NULL,
    estado_sucursal character varying(30) NOT NULL,
    id_usuario integer
);


ALTER TABLE public.sucursales OWNER TO postgres;

--
-- TOC entry 244 (class 1259 OID 37749)
-- Name: sucursales_id_sucursal_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.sucursales_id_sucursal_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sucursales_id_sucursal_seq OWNER TO postgres;

--
-- TOC entry 5684 (class 0 OID 0)
-- Dependencies: 244
-- Name: sucursales_id_sucursal_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.sucursales_id_sucursal_seq OWNED BY public.sucursales.id_sucursal;


--
-- TOC entry 224 (class 1259 OID 37633)
-- Name: tipo_iva; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tipo_iva (
    iva_id integer NOT NULL,
    iva_descri character varying(30) NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.tipo_iva OWNER TO postgres;

--
-- TOC entry 223 (class 1259 OID 37632)
-- Name: tipo_iva_iva_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tipo_iva_iva_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tipo_iva_iva_id_seq OWNER TO postgres;

--
-- TOC entry 5685 (class 0 OID 0)
-- Dependencies: 223
-- Name: tipo_iva_iva_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tipo_iva_iva_id_seq OWNED BY public.tipo_iva.iva_id;


--
-- TOC entry 313 (class 1259 OID 65988)
-- Name: tipo_pedido; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tipo_pedido (
    id_tipo_pedido integer NOT NULL,
    tipo_pedido_descri character varying(50) NOT NULL,
    tipo_pedido_estado character varying(30) DEFAULT 'ACTIVO'::character varying NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.tipo_pedido OWNER TO postgres;

--
-- TOC entry 314 (class 1259 OID 65994)
-- Name: tipo_pedido_id_tipo_pedido_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tipo_pedido_id_tipo_pedido_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tipo_pedido_id_tipo_pedido_seq OWNER TO postgres;

--
-- TOC entry 5686 (class 0 OID 0)
-- Dependencies: 314
-- Name: tipo_pedido_id_tipo_pedido_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tipo_pedido_id_tipo_pedido_seq OWNED BY public.tipo_pedido.id_tipo_pedido;


--
-- TOC entry 226 (class 1259 OID 37661)
-- Name: tipo_perdida; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tipo_perdida (
    tipo_perdida_id integer NOT NULL,
    tipo_perdida_descri character varying(30) NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.tipo_perdida OWNER TO postgres;

--
-- TOC entry 225 (class 1259 OID 37660)
-- Name: tipo_perdida_tipo_perdida_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.tipo_perdida_tipo_perdida_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.tipo_perdida_tipo_perdida_id_seq OWNER TO postgres;

--
-- TOC entry 5687 (class 0 OID 0)
-- Dependencies: 225
-- Name: tipo_perdida_tipo_perdida_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.tipo_perdida_tipo_perdida_id_seq OWNED BY public.tipo_perdida.tipo_perdida_id;


--
-- TOC entry 312 (class 1259 OID 44133)
-- Name: tipo_producto; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.tipo_producto (
    cod_tipo_prod integer NOT NULL,
    t_p_descrip character varying(50) NOT NULL
);


ALTER TABLE public.tipo_producto OWNER TO postgres;

--
-- TOC entry 5688 (class 0 OID 0)
-- Dependencies: 312
-- Name: TABLE tipo_producto; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON TABLE public.tipo_producto IS 'Tipos de productos para clasificación';


--
-- TOC entry 5689 (class 0 OID 0)
-- Dependencies: 312
-- Name: COLUMN tipo_producto.cod_tipo_prod; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tipo_producto.cod_tipo_prod IS 'Código único del tipo de producto';


--
-- TOC entry 5690 (class 0 OID 0)
-- Dependencies: 312
-- Name: COLUMN tipo_producto.t_p_descrip; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.tipo_producto.t_p_descrip IS 'Descripción del tipo de producto';


--
-- TOC entry 238 (class 1259 OID 37710)
-- Name: trabajadores; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.trabajadores (
    trabajadores_id integer NOT NULL,
    id_usuario integer NOT NULL,
    id_personal integer NOT NULL,
    trabajador_estado character varying(30) NOT NULL,
    trabajador_rol character varying(50),
    trabajador_turno character varying(30),
    trabajador_costo_hora numeric(12,2) DEFAULT 0 NOT NULL,
    id_etapa integer,
    CONSTRAINT trabajadores_costo_chk CHECK ((trabajador_costo_hora >= (0)::numeric)),
    CONSTRAINT trabajadores_turno_chk CHECK (((trabajador_turno IS NULL) OR ((trabajador_turno)::text = ANY ((ARRAY['MAÑANA'::character varying, 'TARDE'::character varying, 'NOCHE'::character varying, 'ROTATIVO'::character varying])::text[]))))
);


ALTER TABLE public.trabajadores OWNER TO postgres;

--
-- TOC entry 5691 (class 0 OID 0)
-- Dependencies: 238
-- Name: COLUMN trabajadores.trabajador_costo_hora; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.trabajadores.trabajador_costo_hora IS 'Costo hora para cálculo de mano de obra en costos de producción.';


--
-- TOC entry 237 (class 1259 OID 37709)
-- Name: trabajadores_trabajadores_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.trabajadores_trabajadores_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.trabajadores_trabajadores_id_seq OWNER TO postgres;

--
-- TOC entry 5692 (class 0 OID 0)
-- Dependencies: 237
-- Name: trabajadores_trabajadores_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.trabajadores_trabajadores_id_seq OWNED BY public.trabajadores.trabajadores_id;


--
-- TOC entry 283 (class 1259 OID 37990)
-- Name: unidad_medida; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.unidad_medida (
    id_unidad integer NOT NULL,
    unidad_descri character varying(30) NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.unidad_medida OWNER TO postgres;

--
-- TOC entry 282 (class 1259 OID 37989)
-- Name: unidad_medida_id_unidad_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.unidad_medida_id_unidad_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.unidad_medida_id_unidad_seq OWNER TO postgres;

--
-- TOC entry 5693 (class 0 OID 0)
-- Dependencies: 282
-- Name: unidad_medida_id_unidad_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.unidad_medida_id_unidad_seq OWNED BY public.unidad_medida.id_unidad;


--
-- TOC entry 218 (class 1259 OID 37612)
-- Name: usuarios; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.usuarios (
    id_usuario integer NOT NULL,
    estado_usuario character varying(30) NOT NULL,
    id_sucursal integer NOT NULL,
    modulo_id integer NOT NULL,
    username character varying(30) NOT NULL,
    usua_password character varying(100) NOT NULL,
    id_cargo integer,
    id_personal integer
);


ALTER TABLE public.usuarios OWNER TO postgres;

--
-- TOC entry 5694 (class 0 OID 0)
-- Dependencies: 218
-- Name: COLUMN usuarios.id_personal; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.usuarios.id_personal IS 'Referencia opcional al registro de personal asociado al usuario';


--
-- TOC entry 217 (class 1259 OID 37611)
-- Name: usuarios_id_usuario_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.usuarios_id_usuario_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.usuarios_id_usuario_seq OWNER TO postgres;

--
-- TOC entry 5695 (class 0 OID 0)
-- Dependencies: 217
-- Name: usuarios_id_usuario_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.usuarios_id_usuario_seq OWNED BY public.usuarios.id_usuario;


--
-- TOC entry 230 (class 1259 OID 37675)
-- Name: vehiculos; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.vehiculos (
    vehiculo_id integer NOT NULL,
    vehiculo_marca character varying(30) NOT NULL,
    vehiculo_ano character varying(30) NOT NULL,
    vehiculo_color character varying(30) NOT NULL,
    id_usuario integer NOT NULL
);


ALTER TABLE public.vehiculos OWNER TO postgres;

--
-- TOC entry 229 (class 1259 OID 37674)
-- Name: vehiculos_vehiculo_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.vehiculos_vehiculo_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.vehiculos_vehiculo_id_seq OWNER TO postgres;

--
-- TOC entry 5696 (class 0 OID 0)
-- Dependencies: 229
-- Name: vehiculos_vehiculo_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.vehiculos_vehiculo_id_seq OWNED BY public.vehiculos.vehiculo_id;


--
-- TOC entry 5002 (class 2604 OID 37779)
-- Name: ajustes id_ajuste; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ajustes ALTER COLUMN id_ajuste SET DEFAULT nextval('public.ajuste_stock_id_ajuste_seq'::regclass);


--
-- TOC entry 5040 (class 2604 OID 39044)
-- Name: bitacora id_bitacora; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.bitacora ALTER COLUMN id_bitacora SET DEFAULT nextval('public.bitacora_id_bitacora_seq'::regclass);


--
-- TOC entry 5001 (class 2604 OID 37772)
-- Name: cargos id_cargo; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cargos ALTER COLUMN id_cargo SET DEFAULT nextval('public.cargos_id_cargo_seq'::regclass);


--
-- TOC entry 4991 (class 2604 OID 37685)
-- Name: conductores conductor_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.conductores ALTER COLUMN conductor_id SET DEFAULT nextval('public.conductores_conductor_id_seq'::regclass);


--
-- TOC entry 5017 (class 2604 OID 37858)
-- Name: control_calidad_produccion calidad_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_calidad_produccion ALTER COLUMN calidad_id SET DEFAULT nextval('public.control_calidad_produccion_calidad_id_seq'::regclass);


--
-- TOC entry 5018 (class 2604 OID 37865)
-- Name: control_produccion control_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion ALTER COLUMN control_id SET DEFAULT nextval('public.control_produccion_control_id_seq'::regclass);


--
-- TOC entry 5034 (class 2604 OID 66114)
-- Name: costo_detalle_produccion costo_detalle_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.costo_detalle_produccion ALTER COLUMN costo_detalle_id SET DEFAULT nextval('public.costo_detalle_produccion_costo_detalle_id_seq'::regclass);


--
-- TOC entry 5008 (class 2604 OID 37809)
-- Name: costo_produccion costo_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.costo_produccion ALTER COLUMN costo_id SET DEFAULT nextval('public.costo_produccion_costo_id_seq'::regclass);


--
-- TOC entry 5003 (class 2604 OID 37786)
-- Name: cuentas_pagar id_cuenta_pagar; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cuentas_pagar ALTER COLUMN id_cuenta_pagar SET DEFAULT nextval('public.cuentas_pagar_id_cuenta_pagar_seq'::regclass);


--
-- TOC entry 4992 (class 2604 OID 37692)
-- Name: deposito deposito_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.deposito ALTER COLUMN deposito_id SET DEFAULT nextval('public.deposito_deposito_id_seq'::regclass);


--
-- TOC entry 4985 (class 2604 OID 37629)
-- Name: equipos_produccion equipo_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipos_produccion ALTER COLUMN equipo_id SET DEFAULT nextval('public.equipos_produccion_equipo_id_seq'::regclass);


--
-- TOC entry 4983 (class 2604 OID 37622)
-- Name: etapa_produccion etapa_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.etapa_produccion ALTER COLUMN etapa_id SET DEFAULT nextval('public.etapa_produccion_etapa_id_seq'::regclass);


--
-- TOC entry 5020 (class 2604 OID 37921)
-- Name: factura_compra id_factura_compra; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.factura_compra ALTER COLUMN id_factura_compra SET DEFAULT nextval('public.factura_compra_id_factura_compra_seq'::regclass);


--
-- TOC entry 4993 (class 2604 OID 37706)
-- Name: inspectores id_inspectores; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.inspectores ALTER COLUMN id_inspectores SET DEFAULT nextval('public.inspectores_id_inspectores_seq'::regclass);


--
-- TOC entry 5021 (class 2604 OID 37928)
-- Name: iva_compra iva_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.iva_compra ALTER COLUMN iva_id SET DEFAULT nextval('public.iva_compra_id_libro_compra_seq'::regclass);


--
-- TOC entry 5033 (class 2604 OID 38075)
-- Name: materia_prima id_materia_prima; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.materia_prima ALTER COLUMN id_materia_prima SET DEFAULT nextval('public.materia_prima_id_materia_prima_seq'::regclass);


--
-- TOC entry 4997 (class 2604 OID 37725)
-- Name: modulos modulo_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.modulos ALTER COLUMN modulo_id SET DEFAULT nextval('public.modulos_modulo_id_seq'::regclass);


--
-- TOC entry 5023 (class 2604 OID 37972)
-- Name: motivo id_motivo; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.motivo ALTER COLUMN id_motivo SET DEFAULT nextval('public.motivo_id_motivo_seq'::regclass);


--
-- TOC entry 5025 (class 2604 OID 37986)
-- Name: nota_compra id_nota_compra; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_compra ALTER COLUMN id_nota_compra SET DEFAULT nextval('public.nota_compra_id_nota_compra_seq'::regclass);


--
-- TOC entry 5022 (class 2604 OID 37937)
-- Name: nota_remision_compra id_nota_remision; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_compra ALTER COLUMN id_nota_remision SET DEFAULT nextval('public.nota_remision_compra_id_nota_remision_seq'::regclass);


--
-- TOC entry 5013 (class 2604 OID 37830)
-- Name: orden_de_compra id_orden_compra; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_de_compra ALTER COLUMN id_orden_compra SET DEFAULT nextval('public.orden_de_compra_id_orden_compra_seq'::regclass);


--
-- TOC entry 5015 (class 2604 OID 37844)
-- Name: orden_produccion orden_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_produccion ALTER COLUMN orden_id SET DEFAULT nextval('public.orden_produccion_orden_id_seq'::regclass);


--
-- TOC entry 5030 (class 2604 OID 38056)
-- Name: parametros_control parametro_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.parametros_control ALTER COLUMN parametro_id SET DEFAULT nextval('public.parametros_control_parametro_id_seq'::regclass);


--
-- TOC entry 4989 (class 2604 OID 37671)
-- Name: pedido_materia_produccion id_pedido_mat_prod; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_materia_produccion ALTER COLUMN id_pedido_mat_prod SET DEFAULT nextval('public.pedido_materia_produccion_id_pedido_mat_prod_seq'::regclass);


--
-- TOC entry 5045 (class 2604 OID 66011)
-- Name: pedido_produccion id_pedido_produccion; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_produccion ALTER COLUMN id_pedido_produccion SET DEFAULT nextval('public.pedido_produccion_id_pedido_produccion_seq'::regclass);


--
-- TOC entry 5006 (class 2604 OID 37802)
-- Name: pedidos_compra id_pedido_compra; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedidos_compra ALTER COLUMN id_pedido_compra SET DEFAULT nextval('public.pedidos_compra_id_pedido_compra_seq'::regclass);


--
-- TOC entry 5019 (class 2604 OID 37872)
-- Name: perdidas perdidas_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.perdidas ALTER COLUMN perdidas_id SET DEFAULT nextval('public.perdidas_perdidas_id_seq'::regclass);


--
-- TOC entry 4981 (class 2604 OID 37608)
-- Name: personal id_personal; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal ALTER COLUMN id_personal SET DEFAULT nextval('public.personal_id_personal_seq'::regclass);


--
-- TOC entry 5010 (class 2604 OID 37823)
-- Name: presupuesto_compra id_presupuesto_compra; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.presupuesto_compra ALTER COLUMN id_presupuesto_compra SET DEFAULT nextval('public.presupuesto_compra_id_presupuesto_compra_seq'::regclass);


--
-- TOC entry 5016 (class 2604 OID 37851)
-- Name: producto_terminado terminado_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.producto_terminado ALTER COLUMN terminado_id SET DEFAULT nextval('public.producto_terminado_terminado_id_seq'::regclass);


--
-- TOC entry 5027 (class 2604 OID 38000)
-- Name: productos producto_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.productos ALTER COLUMN producto_id SET DEFAULT nextval('public.productos_producto_id_seq'::regclass);


--
-- TOC entry 4998 (class 2604 OID 37739)
-- Name: proveedor id_proveedor; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.proveedor ALTER COLUMN id_proveedor SET DEFAULT nextval('public.proveedor_id_proveedor_seq'::regclass);


--
-- TOC entry 5009 (class 2604 OID 37816)
-- Name: reposicion_materia reposicion_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reposicion_materia ALTER COLUMN reposicion_id SET DEFAULT nextval('public.reposicion_materia_reposicion_id_seq'::regclass);


--
-- TOC entry 5037 (class 2604 OID 38123)
-- Name: stock_materia_prima id_stock; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_materia_prima ALTER COLUMN id_stock SET DEFAULT nextval('public.stock_materia_prima_id_stock_seq'::regclass);


--
-- TOC entry 5032 (class 2604 OID 38068)
-- Name: stock_producto id_stock_productos; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_producto ALTER COLUMN id_stock_productos SET DEFAULT nextval('public.stock_producto_id_stock_productos_seq'::regclass);


--
-- TOC entry 5000 (class 2604 OID 37753)
-- Name: sucursales id_sucursal; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sucursales ALTER COLUMN id_sucursal SET DEFAULT nextval('public.sucursales_id_sucursal_seq'::regclass);


--
-- TOC entry 4987 (class 2604 OID 37636)
-- Name: tipo_iva iva_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_iva ALTER COLUMN iva_id SET DEFAULT nextval('public.tipo_iva_iva_id_seq'::regclass);


--
-- TOC entry 5043 (class 2604 OID 65995)
-- Name: tipo_pedido id_tipo_pedido; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_pedido ALTER COLUMN id_tipo_pedido SET DEFAULT nextval('public.tipo_pedido_id_tipo_pedido_seq'::regclass);


--
-- TOC entry 4988 (class 2604 OID 37664)
-- Name: tipo_perdida tipo_perdida_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_perdida ALTER COLUMN tipo_perdida_id SET DEFAULT nextval('public.tipo_perdida_tipo_perdida_id_seq'::regclass);


--
-- TOC entry 4994 (class 2604 OID 37713)
-- Name: trabajadores trabajadores_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trabajadores ALTER COLUMN trabajadores_id SET DEFAULT nextval('public.trabajadores_trabajadores_id_seq'::regclass);


--
-- TOC entry 5026 (class 2604 OID 37993)
-- Name: unidad_medida id_unidad; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.unidad_medida ALTER COLUMN id_unidad SET DEFAULT nextval('public.unidad_medida_id_unidad_seq'::regclass);


--
-- TOC entry 4982 (class 2604 OID 37615)
-- Name: usuarios id_usuario; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.usuarios ALTER COLUMN id_usuario SET DEFAULT nextval('public.usuarios_id_usuario_seq'::regclass);


--
-- TOC entry 4990 (class 2604 OID 37678)
-- Name: vehiculos vehiculo_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.vehiculos ALTER COLUMN vehiculo_id SET DEFAULT nextval('public.vehiculos_vehiculo_id_seq'::regclass);


--
-- TOC entry 5540 (class 0 OID 37776)
-- Dependencies: 249
-- Data for Name: ajustes; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.ajustes (id_ajuste, ajuste_fecha, ajuste_estado, id_usuario, deposito_id) FROM stdin;
\.


--
-- TOC entry 5598 (class 0 OID 38126)
-- Dependencies: 307
-- Data for Name: ajustes_detalle; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.ajustes_detalle (id_materia_prima, id_ajuste, id_stock, ajuste_cantidad, id_motivo) FROM stdin;
\.


--
-- TOC entry 5602 (class 0 OID 39041)
-- Dependencies: 311
-- Data for Name: bitacora; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.bitacora (id_bitacora, id_usuario, entidad_afectada, id_registro, accion_realizada, descripcion_cambio, fecha_accion, ip_origen, navegador_usuario, estado_registro) FROM stdin;
1	8	pedido compra	1	ALTA	Se inserta registro cabecera de Pedido Compra	2026-06-23 20:18:51.163097	\N	\N	ACTIVO
2	8	pedido compra	2	ALTA	Detalle agregado (pedido 1, materia prima 2, cant 20)	2026-06-23 20:18:51.163097	\N	\N	ACTIVO
3	8	pedido compra	1	MODIFICACION	[2026-06-23 20:44:54] Modifica cantidad del producto 2 en pedido 1: 20 → 10	2026-06-23 20:44:54.32873	\N	\N	ACTIVO
4	8	pedido compra	1	INACTIVACION	Se anula el Pedido de Compra #1	2026-06-23 20:45:05.859392	\N	\N	ACTIVO
5	8	pedido compra	2	ALTA	Se inserta registro cabecera de Pedido Compra	2026-06-23 20:45:38.175907	\N	\N	ACTIVO
6	8	pedido compra	5	ALTA	Detalle agregado (pedido 2, materia prima 5, cant 5)	2026-06-23 20:45:38.175907	\N	\N	ACTIVO
7	8	Presupuesto compra	1	ALTA	Presupuesto Compra: se crea cabecera #1 (UI Presupuesto: #1) por pedido 2. Total: 50000.	2026-06-23 20:46:56.297666	\N	\N	ACTIVO
8	8	Presupuesto compra	1	ALTA	Detalle Presupuesto Compra: presupuesto #1, materia prima: 5, cantidad: 5, precio 10000.	2026-06-23 20:46:56.297666	\N	\N	ACTIVO
9	8	Presupuesto compra	2	MODIFICACION	Update estado de Pedido Compra: pedido 2 → APROBADO por presupuesto #1.	2026-06-23 20:46:56.297666	\N	\N	ACTIVO
10	8	Presupuesto compra	2	ALTA	Presupuesto Compra: se crea cabecera #2 (UI Presupuesto: #2) por pedido 2. Total: 45000.	2026-06-23 20:47:49.64882	\N	\N	ACTIVO
11	8	Presupuesto compra	2	ALTA	Detalle Presupuesto Compra: presupuesto #2, materia prima: 5, cantidad: 5, precio 9000.	2026-06-23 20:47:49.64882	\N	\N	ACTIVO
12	8	Presupuesto compra	2	MODIFICACION	Update estado de Pedido Compra: pedido 2 → APROBADO por presupuesto #2.	2026-06-23 20:47:49.64882	\N	\N	ACTIVO
13	8	Presupuesto compra	1	MODIFICACION	[2026-06-24 02:48:08] Modifica DETALLE presu #1, prod 5: cant 5 → 5, precio 10000 → 10000, descuento 0 → 20000, iva 2380 → 1425	2026-06-23 20:48:08.163393	\N	\N	ACTIVO
14	8	Presupuesto compra	1	MODIFICACION	[2026-06-24 02:48:08] MODIFICA CABECERA presu #1 | total=50000→30000	2026-06-23 20:48:08.163393	\N	\N	ACTIVO
\.


--
-- TOC entry 5538 (class 0 OID 37769)
-- Dependencies: 247
-- Data for Name: cargos; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cargos (id_cargo, cargo_descripcion, estado_cargo, id_usuario) FROM stdin;
1	ADMIN	ACTIVO	1
2	JEFE DE COMPRAS	ACTIVO	1
3	JEFE DE VENTAS	ACTIVO	1
4	ENCARGADO DE COMPRAS	ACTIVO	1
5	ENCARGADO DE VENTAS	ACTIVO	1
6	JEFE DE PRODUCCION	ACTIVO	1
7	OPERARIO DE PRODUCCION	ACTIVO	1
8	INSPECTOR DE CALIDAD	ACTIVO	1
9	ENCARGADO DE PRODUCCION	ACTIVO	1
\.


--
-- TOC entry 5523 (class 0 OID 37682)
-- Dependencies: 232
-- Data for Name: conductores; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.conductores (conductor_id, conductor_nombre, conductor_apellido, conductor_telefono, id_usuario) FROM stdin;
1	LUCAS	MEDINA	09999999	1
2	ROBERTO	SILVA	0987788990	1
\.


--
-- TOC entry 5584 (class 0 OID 38059)
-- Dependencies: 293
-- Data for Name: control_calidad_detalle; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.control_calidad_detalle (calidad_id, producto_id, calidad_estado, calidad_cantidad, parametro_id, valor_medido, cumple_parametro) FROM stdin;
\.


--
-- TOC entry 5558 (class 0 OID 37855)
-- Dependencies: 267
-- Data for Name: control_calidad_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.control_calidad_produccion (calidad_id, calidad_fecha, calidad_estado, id_inspectores, id_usuario, terminado_id) FROM stdin;
\.


--
-- TOC entry 5560 (class 0 OID 37862)
-- Dependencies: 269
-- Data for Name: control_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.control_produccion (control_id, control_fecha, control_estado, id_inspectores, orden_id, id_usuario, producto_id, etapa_id, control_observacion) FROM stdin;
\.


--
-- TOC entry 5610 (class 0 OID 66159)
-- Dependencies: 319
-- Data for Name: control_produccion_consumo; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.control_produccion_consumo (control_id, id_materia_prima, cantidad_consumida) FROM stdin;
\.


--
-- TOC entry 5580 (class 0 OID 38018)
-- Dependencies: 289
-- Data for Name: control_produccion_detalle; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.control_produccion_detalle (control_id, producto_id, control_cantidad, control_descri) FROM stdin;
\.


--
-- TOC entry 5589 (class 0 OID 38078)
-- Dependencies: 298
-- Data for Name: costo_detalle_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.costo_detalle_produccion (costo_id, id_materia_prima, costo_cantidad, costo_precio, costo_detalle_id, costo_tipo, trabajadores_id, costo_concepto) FROM stdin;
\.


--
-- TOC entry 5546 (class 0 OID 37806)
-- Dependencies: 255
-- Data for Name: costo_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.costo_produccion (costo_id, costo_fecha, costo_estado, costo_total, id_usuario, orden_id) FROM stdin;
\.


--
-- TOC entry 5542 (class 0 OID 37783)
-- Dependencies: 251
-- Data for Name: cuentas_pagar; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cuentas_pagar (id_cuenta_pagar, monto_total, monto_pendiente, estado, fecha_emision, fecha_vencimiento, id_sucursal, id_usuario, id_proveedor, id_factura_compra, plazo_cuenta, nro_cuota) FROM stdin;
\.


--
-- TOC entry 5525 (class 0 OID 37689)
-- Dependencies: 234
-- Data for Name: deposito; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.deposito (deposito_id, deposito_descri, id_usuario) FROM stdin;
1	CENTRAL	1
2	DEPOSITO FRIO	1
3	DEPOSITO SECO	1
\.


--
-- TOC entry 5530 (class 0 OID 37716)
-- Dependencies: 239
-- Data for Name: equipo_detalle; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.equipo_detalle (equipo_id, trabajadores_id, tarea_rol) FROM stdin;
\.


--
-- TOC entry 5513 (class 0 OID 37626)
-- Dependencies: 222
-- Data for Name: equipos_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.equipos_produccion (equipo_id, equipo_descri, id_usuario, equipo_estado, orden_id, equipo_fecha, id_sucursal, etapa_id, etapa_nombre) FROM stdin;
\.


--
-- TOC entry 5577 (class 0 OID 38003)
-- Dependencies: 286
-- Data for Name: etapa_detalle_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.etapa_detalle_produccion (etapa_id, producto_id, etapa_nombre, etapa_procedimiento, etapa_secuencia, etapa_tiempo_estimado, etapa_observaciones) FROM stdin;
1	1	Preparación	Lavar y cortar lechuga y tomate. Porcionar queso y bacon. Preparar salsas.	1	15	Mise en place
2	1	Cocción	Cocinar medallón de carne a punto. Dorar bacon. Tostar pan si aplica.	2	12	Plancha / parrilla
3	1	Armado	Montar hamburguesa: pan, salsa, carne, queso, bacon, vegetales, tapa.	3	8	Estándar Emmanuels
4	1	Empaque	Envolver, incluir papas si corresponde, etiquetar y dejar en zona de despacho.	4	5	Control visual final
11	5	Preparación	Pelar, cortar papas en bastones. Enjuagar y secar.	1	20	Corte uniforme
12	5	Fritura	Freír en oleaje 175-180 °C hasta dorado. Escurrir y salar.	2	15	Crocantes por fuera
13	5	Empaque	Porcionar en envase, cerrar y llevar a zona caliente o despacho.	3	5	\N
\.


--
-- TOC entry 5511 (class 0 OID 37619)
-- Dependencies: 220
-- Data for Name: etapa_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.etapa_produccion (etapa_id, etapa_fecha, etapa_descri, id_usuario, producto_id, etapa_estado) FROM stdin;
2	2026-05-28	HB-02 COC	1	1	ACTIVA
4	2026-05-28	HB-04 EMP	1	1	ACTIVA
1	2026-05-28	HB-01 PREP	1	1	ACTIVA
3	2026-05-28	HB-03 ARM	1	1	ACTIVA
11	2026-05-28	PF-01 PREP	1	5	ACTIVA
12	2026-05-28	PF-02 FRE	1	5	ACTIVA
13	2026-05-28	PF-03 EMP	1	5	ACTIVA
\.


--
-- TOC entry 5564 (class 0 OID 37918)
-- Dependencies: 273
-- Data for Name: factura_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.factura_compra (id_factura_compra, numero_factura, timbrado, fac_fecha_vencimiento, fact_fecha_compra, fac_total, fac_estado, fac_plazo, fac_remision, id_usuario, id_sucursal, id_orden_compra, tipo_operacion) FROM stdin;
\.


--
-- TOC entry 5595 (class 0 OID 38112)
-- Dependencies: 304
-- Data for Name: factura_detalle_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.factura_detalle_compra (id_materia_prima, id_factura_compra, fac_cantidad, fac_iva, fac_precio) FROM stdin;
\.


--
-- TOC entry 5527 (class 0 OID 37703)
-- Dependencies: 236
-- Data for Name: inspectores; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.inspectores (id_inspectores, id_personal, inspector_estado, id_usuario) FROM stdin;
1	1	ACTIVO	1
2	5	ACTIVO	1
\.


--
-- TOC entry 5566 (class 0 OID 37925)
-- Dependencies: 275
-- Data for Name: iva_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.iva_compra (iva_id, id_factura_compra, iva_fecha, iva_exento, iva_5, iva_10) FROM stdin;
\.


--
-- TOC entry 5588 (class 0 OID 38072)
-- Dependencies: 297
-- Data for Name: materia_prima; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.materia_prima (id_materia_prima, materia_prima_descripcion, materia_prima_estado, id_unidad, id_usuario, iva_id) FROM stdin;
10	PAPAS	ACTIVO	1	1	1
5	CARNE DE RES (MEDALLON)	ACTIVO	1	1	1
8	SALSA ESPECIAL	ACTIVO	1	1	1
6	QUESO CHEDDAR	ACTIVO	1	1	1
9	MAYONESA	ACTIVO	1	1	1
7	BACON	ACTIVO	1	1	1
1	LECHUGA	ACTIVO	1	1	1
2	TOMATE	ACTIVO	1	1	1
3	PAN DE HAMBURGUESA	ACTIVO	1	1	1
4	CEBOLLAS	ACTIVO	1	1	1
11	QUESO PY	ACTIVO	1	1	1
\.


--
-- TOC entry 5532 (class 0 OID 37722)
-- Dependencies: 241
-- Data for Name: modulos; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.modulos (modulo_id, modulo_descri, id_usuario) FROM stdin;
1	COMPRAS	1
2	VENTAS	1
3	PRODUCCION	1
4	ADMIN	1
\.


--
-- TOC entry 5570 (class 0 OID 37969)
-- Dependencies: 279
-- Data for Name: motivo; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.motivo (id_motivo, motivo_descripcion, id_usuario, categoria_motivo) FROM stdin;
3	Diferencia de Precio	1	NOTA_CREDITO
4	Descuento / Bonificación	1	NOTA_CREDITO
5	Error en Facturación	1	NOTA_CREDITO
6	Ajuste de Saldo	1	NOTA_CREDITO
7	Faltante	1	AJUSTE
8	Sobrante	1	AJUSTE
9	Merma	1	AJUSTE
10	Regularización	1	AJUSTE
1	Anulación Total	1	NOTA_CREDITO
2	Devolución de Mercadería	1	NOTA_CREDITO
11	Anulación Total Venta	1	NOTA_CREDITO_VENTA
12	Devolución de Mercadería Venta	1	NOTA_CREDITO_VENTA
\.


--
-- TOC entry 5572 (class 0 OID 37983)
-- Dependencies: 281
-- Data for Name: nota_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.nota_compra (id_nota_compra, nota_compra_tipo, nota_compra_fecha, nota_nro, nota_compra_timbrado, nota_compra_inicio, nota_compra_vencimiento, nota_compra_estado, nota_total, id_usuario, id_sucursal, id_proveedor, id_motivo, id_factura_compra) FROM stdin;
\.


--
-- TOC entry 5594 (class 0 OID 38105)
-- Dependencies: 303
-- Data for Name: nota_detalle_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.nota_detalle_compra (id_materia_prima, id_nota_compra, nota_compra_cantidad, tipo_iva, nota_precio) FROM stdin;
\.


--
-- TOC entry 5568 (class 0 OID 37934)
-- Dependencies: 277
-- Data for Name: nota_remision_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.nota_remision_compra (id_nota_remision, id_factura_compra, nota_fecha, nota_remision_total, nota_remision_nro, nota_estado, id_usuario, id_proveedor, deposito_id, conductor_id, vehiculo_id, id_orden_compra) FROM stdin;
\.


--
-- TOC entry 5593 (class 0 OID 38098)
-- Dependencies: 302
-- Data for Name: nota_remision_detalle_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.nota_remision_detalle_compra (id_nota_remision, id_materia_prima, nota_cantidad, nota_remi_iva) FROM stdin;
\.


--
-- TOC entry 5552 (class 0 OID 37827)
-- Dependencies: 261
-- Data for Name: orden_de_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.orden_de_compra (id_orden_compra, orden_fecha, orden_estado, orden_total, id_presupuesto_compra, id_proveedor, id_sucursal, id_usuario, orden_condicion) FROM stdin;
\.


--
-- TOC entry 5599 (class 0 OID 38131)
-- Dependencies: 308
-- Data for Name: orden_detalle_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.orden_detalle_compra (id_materia_prima, id_orden_compra, oc_cantidad_compra, oc_precio_compra) FROM stdin;
\.


--
-- TOC entry 5581 (class 0 OID 38023)
-- Dependencies: 290
-- Data for Name: orden_detalle_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.orden_detalle_produccion (orden_id, producto_id, orden_prod_cantidad, cantidad_pendiente) FROM stdin;
\.


--
-- TOC entry 5554 (class 0 OID 37841)
-- Dependencies: 263
-- Data for Name: orden_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.orden_produccion (orden_id, orden_prod_fecha, orden_prod_fecha_entrega, orden_prod_estado, id_usuario, id_pedido_produccion) FROM stdin;
\.


--
-- TOC entry 5583 (class 0 OID 38053)
-- Dependencies: 292
-- Data for Name: parametros_control; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.parametros_control (parametro_id, parametro_descri, producto_id, id_usuario, parametro_estado) FROM stdin;
2	PESO PORCION (G)	1	1	ACTIVO
1	TEMPERATURA (°C)	1	1	ACTIVO
3	EMPAQUE Y PRESENTACION	1	1	ACTIVO
11	CRUJIENTE / COLOR	5	1	ACTIVO
12	PORCION (G)	5	1	ACTIVO
13	EMPAQUE Y TEMPERATURA	5	1	ACTIVO
\.


--
-- TOC entry 5592 (class 0 OID 38093)
-- Dependencies: 301
-- Data for Name: pedido_detalle_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.pedido_detalle_compra (id_pedido_compra, id_materia_prima, cantidad_pedido) FROM stdin;
1	2	10
2	5	5
\.


--
-- TOC entry 5608 (class 0 OID 66012)
-- Dependencies: 317
-- Data for Name: pedido_detalle_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.pedido_detalle_produccion (id_pedido_produccion, producto_id, cantidad_pedido) FROM stdin;
\.


--
-- TOC entry 5591 (class 0 OID 38088)
-- Dependencies: 300
-- Data for Name: pedido_materia_detalle_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.pedido_materia_detalle_produccion (id_pedido_mat_prod, id_materia_prima, ped_mat_prod_cantidad, cantidad_repuesta) FROM stdin;
\.


--
-- TOC entry 5519 (class 0 OID 37668)
-- Dependencies: 228
-- Data for Name: pedido_materia_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.pedido_materia_produccion (id_pedido_mat_prod, ped_mat_prod_fecha, ped_mat_prod_estado, id_usuario, id_sucursal, deposito_id) FROM stdin;
\.


--
-- TOC entry 5606 (class 0 OID 66001)
-- Dependencies: 315
-- Data for Name: pedido_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.pedido_produccion (id_pedido_produccion, pedido_prod_fecha_emision, pedido_prod_estado, id_tipo_pedido, id_usuario, id_sucursal, pedido_prod_observaciones, pedido_prod_ultima_modificacion) FROM stdin;
\.


--
-- TOC entry 5544 (class 0 OID 37799)
-- Dependencies: 253
-- Data for Name: pedidos_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.pedidos_compra (id_pedido_compra, pedido_fecha_emision, pedido_estado, id_usuario, id_sucursal, pedido_observaciones, pedido_ultima_modificacion) FROM stdin;
1	2026-06-23 00:00:00	ANULADO	8	1		2026-06-23 20:45:05.859392
2	2026-06-23 00:00:00	APROBADO	8	1		2026-06-23 20:47:49.64882
\.


--
-- TOC entry 5562 (class 0 OID 37869)
-- Dependencies: 271
-- Data for Name: perdidas; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.perdidas (perdidas_id, perdida_estado, perdida_fecha, tipo_perdida_id, calidad_id, control_id, id_usuario) FROM stdin;
\.


--
-- TOC entry 5578 (class 0 OID 38008)
-- Dependencies: 287
-- Data for Name: perdidas_detalle; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.perdidas_detalle (perdidas_id, producto_id, perdida_cantidad, perdida_motivo) FROM stdin;
\.


--
-- TOC entry 5507 (class 0 OID 37605)
-- Dependencies: 216
-- Data for Name: personal; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.personal (id_personal, personal_estado, personal_nombre, personal_apellido, personal_telefono, personal_ci, id_cargo, id_usuario, id_sucursal) FROM stdin;
1	ACTIVO	LUCAS	MEDINA	0974203860	5574408	1	1	1
2	ACTIVO	CARLOS	BENITEZ	0981123456	4123456	7	1	1
3	ACTIVO	MARIA	GONZALEZ	0982234567	4234567	7	1	1
4	ACTIVO	JORGE	ACOSTA	0983345678	4345678	7	1	1
5	ACTIVO	ANA	FERNANDEZ	0984456789	4456789	8	1	1
6	ACTIVO	PEDRO	RAMIREZ	0985567890	4567890	6	1	1
7	ACTIVO	DIEGO	MARTINEZ	0986678901	4678901	4	1	1
8	ACTIVO	SOFIA	ACUÑA	0987789012	4789012	9	1	1
\.


--
-- TOC entry 5550 (class 0 OID 37820)
-- Dependencies: 259
-- Data for Name: presupuesto_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.presupuesto_compra (id_presupuesto_compra, presu_total, presu_fecha, presu_estado, id_pedido_compra, id_usuario, id_sucursal, id_proveedor, descuento_total, presu_observaciones, presu_ultima_modificacion) FROM stdin;
2	45000	2026-06-23 00:00:00	EMITIDO	2	8	1	13	0.00		2026-06-23 20:47:49.64882
1	30000	2026-06-23 00:00:00	EMITIDO	2	8	1	10	20000.00		2026-06-23 20:48:08.163393
\.


--
-- TOC entry 5600 (class 0 OID 38136)
-- Dependencies: 309
-- Data for Name: presupuesto_detalle_compra; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.presupuesto_detalle_compra (id_presupuesto_compra, id_materia_prima, detalle_presu_cantidad, detalle_presu_precio_compra, descuento, detalle_presu_iva) FROM stdin;
2	5	5	9000	0.00	2140.00
1	5	5	10000	20000.00	1425.00
\.


--
-- TOC entry 5556 (class 0 OID 37848)
-- Dependencies: 265
-- Data for Name: producto_terminado; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.producto_terminado (terminado_id, orden_id, terminado_fecha, id_usuario) FROM stdin;
\.


--
-- TOC entry 5576 (class 0 OID 37997)
-- Dependencies: 285
-- Data for Name: productos; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.productos (producto_id, producto_precio, producto_descri, id_unidad, id_usuario, iva_id, producto_estado, id_tipo_producto) FROM stdin;
1	25000	HAMBURGUESA CON BACON	1	1	1	ACTIVO	1
5	10000	PAPAS FRITAS	1	1	1	ACTIVO	1
\.


--
-- TOC entry 5579 (class 0 OID 38013)
-- Dependencies: 288
-- Data for Name: productos_terminados_detalle; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.productos_terminados_detalle (terminado_id, producto_id, terminado_cantidad, deposito_id, terminado_fecha_elab, terminado_fecha_venc) FROM stdin;
\.


--
-- TOC entry 5534 (class 0 OID 37736)
-- Dependencies: 243
-- Data for Name: proveedor; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.proveedor (id_proveedor, id_usuario, razon_social, ruc_proveedor, telefono_proveedor, direccion_proveedor, email_proveedor, estado_proveedor) FROM stdin;
9	1	ANA ROLON	5574408-1	0974203860	Capiata	ana@gmail.com	ACTIVO
10	1	DISTRIBUIDORA SAN JOSE S.A.	80012345-1	021-555-010	Av. Eusebio Ayala 1234, Asunción	ventas@sanjose.com.py	ACTIVO
11	1	PANADERIA EL BUEN PAN	4567890-2	021-555-020	Ruta Mcal. Estigarribia Km 12, Capiatá	pedidos@elbuenpan.com.py	ACTIVO
12	1	VERDULERIA CENTRAL	3216549-3	021-555-030	Mercado de Abasto, Luque	central@verduleria.com.py	ACTIVO
13	1	FRIGORIFICO CHACO S.A.	80112233-4	021-555-040	Fernando de la Mora	compras@frigochaco.com.py	ACTIVO
\.


--
-- TOC entry 5611 (class 0 OID 66196)
-- Dependencies: 320
-- Data for Name: receta_produccion; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.receta_produccion (producto_id, id_materia_prima, cantidad_por_unidad, receta_estado, id_usuario) FROM stdin;
1	3	1.000	ACTIVO	1
1	5	1.000	ACTIVO	1
1	6	1.000	ACTIVO	1
1	7	2.000	ACTIVO	1
1	1	1.000	ACTIVO	1
1	2	1.000	ACTIVO	1
1	8	1.000	ACTIVO	1
5	10	1.000	ACTIVO	1
1	9	1.000	ACTIVO	1
\.


--
-- TOC entry 5548 (class 0 OID 37813)
-- Dependencies: 257
-- Data for Name: reposicion_materia; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.reposicion_materia (reposicion_id, reposicion_fecha, reposicion_estado, deposito_id, id_usuario, id_pedido_mat_prod) FROM stdin;
\.


--
-- TOC entry 5590 (class 0 OID 38083)
-- Dependencies: 299
-- Data for Name: reposicion_materia_detalle; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.reposicion_materia_detalle (reposicion_id, id_materia_prima, reposicion_cantidad) FROM stdin;
\.


--
-- TOC entry 5597 (class 0 OID 38120)
-- Dependencies: 306
-- Data for Name: stock_materia_prima; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.stock_materia_prima (id_stock, id_materia_prima, stock_cantidad_minima, stock_cantidad_maxima, cantidad_existente, deposito_id, id_usuario) FROM stdin;
10	4	20	500	0	1	1
9	10	20	500	0	1	1
6	7	20	500	0	1	1
4	5	20	500	0	1	1
1	1	50	300	0	1	1
8	9	20	500	0	1	1
3	3	50	300	0	1	1
5	6	20	500	0	1	1
7	8	20	500	0	1	1
2	2	50	300	0	1	1
11	11	20	500	0	1	1
\.


--
-- TOC entry 5586 (class 0 OID 38065)
-- Dependencies: 295
-- Data for Name: stock_producto; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.stock_producto (id_stock_productos, producto_id, deposito_id, stock_prod_ven, stock_prod_existente, id_usuario) FROM stdin;
2	5	1	2026-01-05	0	1
1	1	1	2026-01-05	0	1
\.


--
-- TOC entry 5536 (class 0 OID 37750)
-- Dependencies: 245
-- Data for Name: sucursales; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.sucursales (id_sucursal, descripcion_sucursal, estado_sucursal, id_usuario) FROM stdin;
1	CENTRAL	ACTIVO	\N
\.


--
-- TOC entry 5515 (class 0 OID 37633)
-- Dependencies: 224
-- Data for Name: tipo_iva; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.tipo_iva (iva_id, iva_descri, id_usuario) FROM stdin;
1	5%	1
2	10%	1
3	EXENTO	1
\.


--
-- TOC entry 5604 (class 0 OID 65988)
-- Dependencies: 313
-- Data for Name: tipo_pedido; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.tipo_pedido (id_tipo_pedido, tipo_pedido_descri, tipo_pedido_estado, id_usuario) FROM stdin;
1	Pedido estándar	ACTIVO	1
2	Pedido urgente	ACTIVO	1
3	Pedido programado	ACTIVO	1
\.


--
-- TOC entry 5517 (class 0 OID 37661)
-- Dependencies: 226
-- Data for Name: tipo_perdida; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.tipo_perdida (tipo_perdida_id, tipo_perdida_descri, id_usuario) FROM stdin;
1	RECHAZO CALIDAD	1
2	VENCIMIENTO	1
3	ROTURA / DANO	1
\.


--
-- TOC entry 5603 (class 0 OID 44133)
-- Dependencies: 312
-- Data for Name: tipo_producto; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.tipo_producto (cod_tipo_prod, t_p_descrip) FROM stdin;
1	HAMBURGUESAS
2	ACOMPAÑAMIENTOS
3	BEBIDAS
\.


--
-- TOC entry 5529 (class 0 OID 37710)
-- Dependencies: 238
-- Data for Name: trabajadores; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.trabajadores (trabajadores_id, id_usuario, id_personal, trabajador_estado, trabajador_rol, trabajador_turno, trabajador_costo_hora, id_etapa) FROM stdin;
1	1	2	ACTIVO	Preparador	MAÑANA	18000.00	1
2	1	3	ACTIVO	Cocinero	MAÑANA	22000.00	2
3	1	4	ACTIVO	Armador	TARDE	20000.00	3
4	1	2	ACTIVO	Empacador	TARDE	18000.00	4
5	1	3	ACTIVO	Fritador	MAÑANA	20000.00	12
\.


--
-- TOC entry 5574 (class 0 OID 37990)
-- Dependencies: 283
-- Data for Name: unidad_medida; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.unidad_medida (id_unidad, unidad_descri, id_usuario) FROM stdin;
1	KG	1
2	UN	1
3	LT	1
4	CJ	1
5	GR	1
\.


--
-- TOC entry 5509 (class 0 OID 37612)
-- Dependencies: 218
-- Data for Name: usuarios; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.usuarios (id_usuario, estado_usuario, id_sucursal, modulo_id, username, usua_password, id_cargo, id_personal) FROM stdin;
1	ACTIVO	1	4	admin	7da80690c8a437586fe8c8763f44ce92	1	\N
11	ACTIVO	1	1	jefe_compras	4671057aaea4d1eeaabed64efcc49e83	2	\N
8	ACTIVO	1	1	enc_compras	6bf4d9407020410cb55a84d68acd21b3	4	7
13	ACTIVO	1	3	jefe_prod	03d3355e389a5d44fafe2f4cfc7eeabf	6	6
14	ACTIVO	1	3	enc_prod	40939b4514d80a5c6295698d93ccd471	9	8
\.


--
-- TOC entry 5521 (class 0 OID 37675)
-- Dependencies: 230
-- Data for Name: vehiculos; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.vehiculos (vehiculo_id, vehiculo_marca, vehiculo_ano, vehiculo_color, id_usuario) FROM stdin;
1	TOYOTA	2010	ROJO	1
2	HYUNDAI	2018	BLANCO	1
3	FORD	2015	AZUL	1
\.


--
-- TOC entry 5697 (class 0 OID 0)
-- Dependencies: 248
-- Name: ajuste_stock_id_ajuste_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.ajuste_stock_id_ajuste_seq', 1, false);


--
-- TOC entry 5698 (class 0 OID 0)
-- Dependencies: 310
-- Name: bitacora_id_bitacora_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.bitacora_id_bitacora_seq', 14, true);


--
-- TOC entry 5699 (class 0 OID 0)
-- Dependencies: 246
-- Name: cargos_id_cargo_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.cargos_id_cargo_seq', 9, true);


--
-- TOC entry 5700 (class 0 OID 0)
-- Dependencies: 231
-- Name: conductores_conductor_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.conductores_conductor_id_seq', 2, true);


--
-- TOC entry 5701 (class 0 OID 0)
-- Dependencies: 266
-- Name: control_calidad_produccion_calidad_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.control_calidad_produccion_calidad_id_seq', 1, false);


--
-- TOC entry 5702 (class 0 OID 0)
-- Dependencies: 268
-- Name: control_produccion_control_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.control_produccion_control_id_seq', 1, false);


--
-- TOC entry 5703 (class 0 OID 0)
-- Dependencies: 318
-- Name: costo_detalle_produccion_costo_detalle_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.costo_detalle_produccion_costo_detalle_id_seq', 1, false);


--
-- TOC entry 5704 (class 0 OID 0)
-- Dependencies: 254
-- Name: costo_produccion_costo_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.costo_produccion_costo_id_seq', 1, false);


--
-- TOC entry 5705 (class 0 OID 0)
-- Dependencies: 250
-- Name: cuentas_pagar_id_cuenta_pagar_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.cuentas_pagar_id_cuenta_pagar_seq', 1, false);


--
-- TOC entry 5706 (class 0 OID 0)
-- Dependencies: 233
-- Name: deposito_deposito_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.deposito_deposito_id_seq', 3, true);


--
-- TOC entry 5707 (class 0 OID 0)
-- Dependencies: 221
-- Name: equipos_produccion_equipo_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.equipos_produccion_equipo_id_seq', 1, false);


--
-- TOC entry 5708 (class 0 OID 0)
-- Dependencies: 219
-- Name: etapa_produccion_etapa_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.etapa_produccion_etapa_id_seq', 13, true);


--
-- TOC entry 5709 (class 0 OID 0)
-- Dependencies: 272
-- Name: factura_compra_id_factura_compra_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.factura_compra_id_factura_compra_seq', 1, false);


--
-- TOC entry 5710 (class 0 OID 0)
-- Dependencies: 235
-- Name: inspectores_id_inspectores_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.inspectores_id_inspectores_seq', 2, true);


--
-- TOC entry 5711 (class 0 OID 0)
-- Dependencies: 274
-- Name: iva_compra_id_libro_compra_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.iva_compra_id_libro_compra_seq', 1, false);


--
-- TOC entry 5712 (class 0 OID 0)
-- Dependencies: 296
-- Name: materia_prima_id_materia_prima_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.materia_prima_id_materia_prima_seq', 11, true);


--
-- TOC entry 5713 (class 0 OID 0)
-- Dependencies: 240
-- Name: modulos_modulo_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.modulos_modulo_id_seq', 1, false);


--
-- TOC entry 5714 (class 0 OID 0)
-- Dependencies: 278
-- Name: motivo_id_motivo_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.motivo_id_motivo_seq', 12, true);


--
-- TOC entry 5715 (class 0 OID 0)
-- Dependencies: 280
-- Name: nota_compra_id_nota_compra_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.nota_compra_id_nota_compra_seq', 1, false);


--
-- TOC entry 5716 (class 0 OID 0)
-- Dependencies: 276
-- Name: nota_remision_compra_id_nota_remision_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.nota_remision_compra_id_nota_remision_seq', 1, false);


--
-- TOC entry 5717 (class 0 OID 0)
-- Dependencies: 260
-- Name: orden_de_compra_id_orden_compra_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.orden_de_compra_id_orden_compra_seq', 1, false);


--
-- TOC entry 5718 (class 0 OID 0)
-- Dependencies: 262
-- Name: orden_produccion_orden_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.orden_produccion_orden_id_seq', 1, false);


--
-- TOC entry 5719 (class 0 OID 0)
-- Dependencies: 291
-- Name: parametros_control_parametro_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.parametros_control_parametro_id_seq', 13, true);


--
-- TOC entry 5720 (class 0 OID 0)
-- Dependencies: 227
-- Name: pedido_materia_produccion_id_pedido_mat_prod_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.pedido_materia_produccion_id_pedido_mat_prod_seq', 1, false);


--
-- TOC entry 5721 (class 0 OID 0)
-- Dependencies: 316
-- Name: pedido_produccion_id_pedido_produccion_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.pedido_produccion_id_pedido_produccion_seq', 1, false);


--
-- TOC entry 5722 (class 0 OID 0)
-- Dependencies: 252
-- Name: pedidos_compra_id_pedido_compra_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.pedidos_compra_id_pedido_compra_seq', 1, false);


--
-- TOC entry 5723 (class 0 OID 0)
-- Dependencies: 270
-- Name: perdidas_perdidas_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.perdidas_perdidas_id_seq', 1, false);


--
-- TOC entry 5724 (class 0 OID 0)
-- Dependencies: 215
-- Name: personal_id_personal_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.personal_id_personal_seq', 8, true);


--
-- TOC entry 5725 (class 0 OID 0)
-- Dependencies: 258
-- Name: presupuesto_compra_id_presupuesto_compra_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.presupuesto_compra_id_presupuesto_compra_seq', 2, true);


--
-- TOC entry 5726 (class 0 OID 0)
-- Dependencies: 264
-- Name: producto_terminado_terminado_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.producto_terminado_terminado_id_seq', 1, false);


--
-- TOC entry 5727 (class 0 OID 0)
-- Dependencies: 284
-- Name: productos_producto_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.productos_producto_id_seq', 5, true);


--
-- TOC entry 5728 (class 0 OID 0)
-- Dependencies: 242
-- Name: proveedor_id_proveedor_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.proveedor_id_proveedor_seq', 13, true);


--
-- TOC entry 5729 (class 0 OID 0)
-- Dependencies: 256
-- Name: reposicion_materia_reposicion_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.reposicion_materia_reposicion_id_seq', 1, false);


--
-- TOC entry 5730 (class 0 OID 0)
-- Dependencies: 305
-- Name: stock_materia_prima_id_stock_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.stock_materia_prima_id_stock_seq', 11, true);


--
-- TOC entry 5731 (class 0 OID 0)
-- Dependencies: 294
-- Name: stock_producto_id_stock_productos_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.stock_producto_id_stock_productos_seq', 5, true);


--
-- TOC entry 5732 (class 0 OID 0)
-- Dependencies: 244
-- Name: sucursales_id_sucursal_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.sucursales_id_sucursal_seq', 1, false);


--
-- TOC entry 5733 (class 0 OID 0)
-- Dependencies: 223
-- Name: tipo_iva_iva_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.tipo_iva_iva_id_seq', 3, true);


--
-- TOC entry 5734 (class 0 OID 0)
-- Dependencies: 314
-- Name: tipo_pedido_id_tipo_pedido_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.tipo_pedido_id_tipo_pedido_seq', 3, true);


--
-- TOC entry 5735 (class 0 OID 0)
-- Dependencies: 225
-- Name: tipo_perdida_tipo_perdida_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.tipo_perdida_tipo_perdida_id_seq', 3, true);


--
-- TOC entry 5736 (class 0 OID 0)
-- Dependencies: 237
-- Name: trabajadores_trabajadores_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.trabajadores_trabajadores_id_seq', 5, true);


--
-- TOC entry 5737 (class 0 OID 0)
-- Dependencies: 282
-- Name: unidad_medida_id_unidad_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.unidad_medida_id_unidad_seq', 5, true);


--
-- TOC entry 5738 (class 0 OID 0)
-- Dependencies: 217
-- Name: usuarios_id_usuario_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.usuarios_id_usuario_seq', 14, true);


--
-- TOC entry 5739 (class 0 OID 0)
-- Dependencies: 229
-- Name: vehiculos_vehiculo_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.vehiculos_vehiculo_id_seq', 3, true);


--
-- TOC entry 5189 (class 2606 OID 38130)
-- Name: ajustes_detalle ajustes_detalle_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ajustes_detalle
    ADD CONSTRAINT ajustes_detalle_pk PRIMARY KEY (id_materia_prima, id_ajuste, id_stock);


--
-- TOC entry 5108 (class 2606 OID 37781)
-- Name: ajustes ajustes_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ajustes
    ADD CONSTRAINT ajustes_pk PRIMARY KEY (id_ajuste);


--
-- TOC entry 5195 (class 2606 OID 39051)
-- Name: bitacora bitacora_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.bitacora
    ADD CONSTRAINT bitacora_pkey PRIMARY KEY (id_bitacora);


--
-- TOC entry 5106 (class 2606 OID 37774)
-- Name: cargos cargos_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cargos
    ADD CONSTRAINT cargos_pk PRIMARY KEY (id_cargo);


--
-- TOC entry 5090 (class 2606 OID 37687)
-- Name: conductores conductores_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.conductores
    ADD CONSTRAINT conductores_pk PRIMARY KEY (conductor_id);


--
-- TOC entry 5165 (class 2606 OID 66221)
-- Name: control_calidad_detalle control_calidad_detalle_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_calidad_detalle
    ADD CONSTRAINT control_calidad_detalle_pk PRIMARY KEY (calidad_id, producto_id, parametro_id);


--
-- TOC entry 5128 (class 2606 OID 37860)
-- Name: control_calidad_produccion control_calidad_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_calidad_produccion
    ADD CONSTRAINT control_calidad_produccion_pk PRIMARY KEY (calidad_id);


--
-- TOC entry 5207 (class 2606 OID 66164)
-- Name: control_produccion_consumo control_produccion_consumo_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion_consumo
    ADD CONSTRAINT control_produccion_consumo_pk PRIMARY KEY (control_id, id_materia_prima);


--
-- TOC entry 5158 (class 2606 OID 38022)
-- Name: control_produccion_detalle control_produccion_detalle_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion_detalle
    ADD CONSTRAINT control_produccion_detalle_pk PRIMARY KEY (control_id, producto_id);


--
-- TOC entry 5131 (class 2606 OID 37867)
-- Name: control_produccion control_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion
    ADD CONSTRAINT control_produccion_pk PRIMARY KEY (control_id);


--
-- TOC entry 5172 (class 2606 OID 66116)
-- Name: costo_detalle_produccion costo_detalle_produccion_id_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.costo_detalle_produccion
    ADD CONSTRAINT costo_detalle_produccion_id_pk PRIMARY KEY (costo_detalle_id);


--
-- TOC entry 5115 (class 2606 OID 37811)
-- Name: costo_produccion costo_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.costo_produccion
    ADD CONSTRAINT costo_produccion_pk PRIMARY KEY (costo_id);


--
-- TOC entry 5110 (class 2606 OID 37788)
-- Name: cuentas_pagar cuentas_pagar_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cuentas_pagar
    ADD CONSTRAINT cuentas_pagar_pk PRIMARY KEY (id_cuenta_pagar);


--
-- TOC entry 5092 (class 2606 OID 37694)
-- Name: deposito deposito_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.deposito
    ADD CONSTRAINT deposito_pk PRIMARY KEY (deposito_id);


--
-- TOC entry 5098 (class 2606 OID 37720)
-- Name: equipo_detalle equipo_detalle_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipo_detalle
    ADD CONSTRAINT equipo_detalle_pk PRIMARY KEY (equipo_id, trabajadores_id);


--
-- TOC entry 5080 (class 2606 OID 37631)
-- Name: equipos_produccion equipos_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipos_produccion
    ADD CONSTRAINT equipos_produccion_pk PRIMARY KEY (equipo_id);


--
-- TOC entry 5152 (class 2606 OID 38007)
-- Name: etapa_detalle_produccion etapa_detalle_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.etapa_detalle_produccion
    ADD CONSTRAINT etapa_detalle_produccion_pk PRIMARY KEY (etapa_id, producto_id);


--
-- TOC entry 5078 (class 2606 OID 37624)
-- Name: etapa_produccion etapa_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.etapa_produccion
    ADD CONSTRAINT etapa_produccion_pk PRIMARY KEY (etapa_id);


--
-- TOC entry 5135 (class 2606 OID 37923)
-- Name: factura_compra factura_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.factura_compra
    ADD CONSTRAINT factura_compra_pk PRIMARY KEY (id_factura_compra);


--
-- TOC entry 5185 (class 2606 OID 38118)
-- Name: factura_detalle_compra factura_detalle_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.factura_detalle_compra
    ADD CONSTRAINT factura_detalle_compra_pk PRIMARY KEY (id_materia_prima, id_factura_compra);


--
-- TOC entry 5094 (class 2606 OID 37708)
-- Name: inspectores inspectores_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.inspectores
    ADD CONSTRAINT inspectores_pk PRIMARY KEY (id_inspectores);


--
-- TOC entry 5137 (class 2606 OID 37932)
-- Name: iva_compra iva_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.iva_compra
    ADD CONSTRAINT iva_compra_pk PRIMARY KEY (iva_id);


--
-- TOC entry 5170 (class 2606 OID 38077)
-- Name: materia_prima materia_prima_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.materia_prima
    ADD CONSTRAINT materia_prima_pk PRIMARY KEY (id_materia_prima);


--
-- TOC entry 5100 (class 2606 OID 37727)
-- Name: modulos modulos_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.modulos
    ADD CONSTRAINT modulos_pk PRIMARY KEY (modulo_id);


--
-- TOC entry 5143 (class 2606 OID 37974)
-- Name: motivo motivo_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.motivo
    ADD CONSTRAINT motivo_pk PRIMARY KEY (id_motivo);


--
-- TOC entry 5145 (class 2606 OID 37988)
-- Name: nota_compra nota_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_compra
    ADD CONSTRAINT nota_compra_pk PRIMARY KEY (id_nota_compra);


--
-- TOC entry 5183 (class 2606 OID 38111)
-- Name: nota_detalle_compra nota_detalle_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_detalle_compra
    ADD CONSTRAINT nota_detalle_compra_pk PRIMARY KEY (id_materia_prima, id_nota_compra);


--
-- TOC entry 5140 (class 2606 OID 37939)
-- Name: nota_remision_compra nota_remision_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_compra
    ADD CONSTRAINT nota_remision_compra_pk PRIMARY KEY (id_nota_remision);


--
-- TOC entry 5181 (class 2606 OID 38104)
-- Name: nota_remision_detalle_compra nota_remision_detalle_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_detalle_compra
    ADD CONSTRAINT nota_remision_detalle_compra_pk PRIMARY KEY (id_nota_remision, id_materia_prima);


--
-- TOC entry 5121 (class 2606 OID 37832)
-- Name: orden_de_compra orden_de_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_de_compra
    ADD CONSTRAINT orden_de_compra_pk PRIMARY KEY (id_orden_compra);


--
-- TOC entry 5191 (class 2606 OID 38135)
-- Name: orden_detalle_compra orden_detalle_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_detalle_compra
    ADD CONSTRAINT orden_detalle_compra_pk PRIMARY KEY (id_materia_prima, id_orden_compra);


--
-- TOC entry 5160 (class 2606 OID 38027)
-- Name: orden_detalle_produccion orden_detalle_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_detalle_produccion
    ADD CONSTRAINT orden_detalle_produccion_pk PRIMARY KEY (orden_id, producto_id);


--
-- TOC entry 5124 (class 2606 OID 37846)
-- Name: orden_produccion orden_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_produccion
    ADD CONSTRAINT orden_produccion_pk PRIMARY KEY (orden_id);


--
-- TOC entry 5162 (class 2606 OID 38058)
-- Name: parametros_control parametros_control_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.parametros_control
    ADD CONSTRAINT parametros_control_pk PRIMARY KEY (parametro_id);


--
-- TOC entry 5179 (class 2606 OID 38097)
-- Name: pedido_detalle_compra pedido_detalle_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_detalle_compra
    ADD CONSTRAINT pedido_detalle_compra_pk PRIMARY KEY (id_pedido_compra, id_materia_prima);


--
-- TOC entry 5205 (class 2606 OID 66017)
-- Name: pedido_detalle_produccion pedido_detalle_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_detalle_produccion
    ADD CONSTRAINT pedido_detalle_produccion_pk PRIMARY KEY (id_pedido_produccion, producto_id);


--
-- TOC entry 5177 (class 2606 OID 38092)
-- Name: pedido_materia_detalle_produccion pedido_materia_detalle_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_materia_detalle_produccion
    ADD CONSTRAINT pedido_materia_detalle_produccion_pk PRIMARY KEY (id_pedido_mat_prod, id_materia_prima);


--
-- TOC entry 5086 (class 2606 OID 37673)
-- Name: pedido_materia_produccion pedido_materia_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_materia_produccion
    ADD CONSTRAINT pedido_materia_produccion_pk PRIMARY KEY (id_pedido_mat_prod);


--
-- TOC entry 5203 (class 2606 OID 66009)
-- Name: pedido_produccion pedido_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_produccion
    ADD CONSTRAINT pedido_produccion_pk PRIMARY KEY (id_pedido_produccion);


--
-- TOC entry 5113 (class 2606 OID 37804)
-- Name: pedidos_compra pedidos_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedidos_compra
    ADD CONSTRAINT pedidos_compra_pk PRIMARY KEY (id_pedido_compra);


--
-- TOC entry 5154 (class 2606 OID 38012)
-- Name: perdidas_detalle perdidas_detalle_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.perdidas_detalle
    ADD CONSTRAINT perdidas_detalle_pk PRIMARY KEY (perdidas_id, producto_id);


--
-- TOC entry 5133 (class 2606 OID 37874)
-- Name: perdidas perdidas_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.perdidas
    ADD CONSTRAINT perdidas_pk PRIMARY KEY (perdidas_id);


--
-- TOC entry 5073 (class 2606 OID 37610)
-- Name: personal personal_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal
    ADD CONSTRAINT personal_pk PRIMARY KEY (id_personal);


--
-- TOC entry 5119 (class 2606 OID 37825)
-- Name: presupuesto_compra presupuesto_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.presupuesto_compra
    ADD CONSTRAINT presupuesto_compra_pk PRIMARY KEY (id_presupuesto_compra);


--
-- TOC entry 5193 (class 2606 OID 38140)
-- Name: presupuesto_detalle_compra presupuesto_detalle_compra_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.presupuesto_detalle_compra
    ADD CONSTRAINT presupuesto_detalle_compra_pk PRIMARY KEY (id_presupuesto_compra, id_materia_prima);


--
-- TOC entry 5126 (class 2606 OID 37853)
-- Name: producto_terminado producto_terminado_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.producto_terminado
    ADD CONSTRAINT producto_terminado_pk PRIMARY KEY (terminado_id);


--
-- TOC entry 5150 (class 2606 OID 38002)
-- Name: productos productos_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_pk PRIMARY KEY (producto_id);


--
-- TOC entry 5156 (class 2606 OID 38017)
-- Name: productos_terminados_detalle productos_terminados_detalle_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.productos_terminados_detalle
    ADD CONSTRAINT productos_terminados_detalle_pk PRIMARY KEY (terminado_id, producto_id);


--
-- TOC entry 5102 (class 2606 OID 37741)
-- Name: proveedor proveedor_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.proveedor
    ADD CONSTRAINT proveedor_pk PRIMARY KEY (id_proveedor);


--
-- TOC entry 5210 (class 2606 OID 66203)
-- Name: receta_produccion receta_produccion_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.receta_produccion
    ADD CONSTRAINT receta_produccion_pk PRIMARY KEY (producto_id, id_materia_prima);


--
-- TOC entry 5175 (class 2606 OID 38087)
-- Name: reposicion_materia_detalle reposicion_materia_detalle_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reposicion_materia_detalle
    ADD CONSTRAINT reposicion_materia_detalle_pk PRIMARY KEY (reposicion_id, id_materia_prima);


--
-- TOC entry 5117 (class 2606 OID 37818)
-- Name: reposicion_materia reposicion_materia_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reposicion_materia
    ADD CONSTRAINT reposicion_materia_pk PRIMARY KEY (reposicion_id);


--
-- TOC entry 5187 (class 2606 OID 38125)
-- Name: stock_materia_prima stock_materia_prima_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_materia_prima
    ADD CONSTRAINT stock_materia_prima_pk PRIMARY KEY (id_stock);


--
-- TOC entry 5168 (class 2606 OID 38070)
-- Name: stock_producto stock_producto_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_producto
    ADD CONSTRAINT stock_producto_pk PRIMARY KEY (id_stock_productos);


--
-- TOC entry 5104 (class 2606 OID 37755)
-- Name: sucursales sucursales_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sucursales
    ADD CONSTRAINT sucursales_pk PRIMARY KEY (id_sucursal);


--
-- TOC entry 5082 (class 2606 OID 37638)
-- Name: tipo_iva tipo_iva_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_iva
    ADD CONSTRAINT tipo_iva_pk PRIMARY KEY (iva_id);


--
-- TOC entry 5200 (class 2606 OID 65993)
-- Name: tipo_pedido tipo_pedido_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_pedido
    ADD CONSTRAINT tipo_pedido_pk PRIMARY KEY (id_tipo_pedido);


--
-- TOC entry 5084 (class 2606 OID 37666)
-- Name: tipo_perdida tipo_perdida_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_perdida
    ADD CONSTRAINT tipo_perdida_pk PRIMARY KEY (tipo_perdida_id);


--
-- TOC entry 5198 (class 2606 OID 44137)
-- Name: tipo_producto tipo_producto_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_producto
    ADD CONSTRAINT tipo_producto_pk PRIMARY KEY (cod_tipo_prod);


--
-- TOC entry 5096 (class 2606 OID 37715)
-- Name: trabajadores trabajadores_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trabajadores
    ADD CONSTRAINT trabajadores_pk PRIMARY KEY (trabajadores_id);


--
-- TOC entry 5147 (class 2606 OID 37995)
-- Name: unidad_medida unidad_medida_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.unidad_medida
    ADD CONSTRAINT unidad_medida_pk PRIMARY KEY (id_unidad);


--
-- TOC entry 5076 (class 2606 OID 37617)
-- Name: usuarios usuarios_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_pk PRIMARY KEY (id_usuario);


--
-- TOC entry 5088 (class 2606 OID 37680)
-- Name: vehiculos vehiculos_pk; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.vehiculos
    ADD CONSTRAINT vehiculos_pk PRIMARY KEY (vehiculo_id);


--
-- TOC entry 5111 (class 1259 OID 44162)
-- Name: idx_cuentas_pagar_id_factura_compra; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cuentas_pagar_id_factura_compra ON public.cuentas_pagar USING btree (id_factura_compra);


--
-- TOC entry 5141 (class 1259 OID 44199)
-- Name: idx_motivo_categoria; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_motivo_categoria ON public.motivo USING btree (categoria_motivo);


--
-- TOC entry 5138 (class 1259 OID 44186)
-- Name: idx_nota_remision_compra_orden_compra; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_nota_remision_compra_orden_compra ON public.nota_remision_compra USING btree (id_orden_compra);


--
-- TOC entry 5122 (class 1259 OID 66051)
-- Name: idx_orden_produccion_pedido; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_orden_produccion_pedido ON public.orden_produccion USING btree (id_pedido_produccion);


--
-- TOC entry 5201 (class 1259 OID 66050)
-- Name: idx_pedido_produccion_estado_fecha; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_pedido_produccion_estado_fecha ON public.pedido_produccion USING btree (pedido_prod_estado, pedido_prod_fecha_emision DESC);


--
-- TOC entry 5071 (class 1259 OID 44205)
-- Name: idx_personal_sucursal; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_personal_sucursal ON public.personal USING btree (id_sucursal);


--
-- TOC entry 5148 (class 1259 OID 44102)
-- Name: idx_productos_tipo_producto; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_productos_tipo_producto ON public.productos USING btree (id_tipo_producto);


--
-- TOC entry 5208 (class 1259 OID 66219)
-- Name: idx_receta_produccion_producto; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_receta_produccion_producto ON public.receta_produccion USING btree (producto_id);


--
-- TOC entry 5166 (class 1259 OID 43502)
-- Name: idx_stock_producto_producto_deposito; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_stock_producto_producto_deposito ON public.stock_producto USING btree (producto_id, deposito_id);


--
-- TOC entry 5196 (class 1259 OID 44138)
-- Name: idx_tipo_producto_descrip; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_tipo_producto_descrip ON public.tipo_producto USING btree (t_p_descrip);


--
-- TOC entry 5074 (class 1259 OID 43512)
-- Name: idx_usuarios_username; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_usuarios_username ON public.usuarios USING btree (username);


--
-- TOC entry 5129 (class 1259 OID 66222)
-- Name: uq_control_calidad_terminado_activo; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX uq_control_calidad_terminado_activo ON public.control_calidad_produccion USING btree (terminado_id) WHERE (upper(TRIM(BOTH FROM calidad_estado)) = ANY (ARRAY['REGISTRADO'::text, 'APROBADO'::text, 'NO CONFORME'::text]));


--
-- TOC entry 5173 (class 1259 OID 66117)
-- Name: uq_costo_detalle_mp; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX uq_costo_detalle_mp ON public.costo_detalle_produccion USING btree (costo_id, id_materia_prima) WHERE (((costo_tipo)::text = 'MP'::text) AND (id_materia_prima IS NOT NULL));


--
-- TOC entry 5163 (class 1259 OID 66135)
-- Name: uq_parametros_producto_descri_activo; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX uq_parametros_producto_descri_activo ON public.parametros_control USING btree (producto_id, lower(TRIM(BOTH FROM parametro_descri))) WHERE ((parametro_estado)::text = 'ACTIVO'::text);


--
-- TOC entry 5362 (class 2620 OID 66044)
-- Name: pedido_produccion trg_pedido_produccion_ultima_modificacion; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trg_pedido_produccion_ultima_modificacion BEFORE UPDATE ON public.pedido_produccion FOR EACH ROW EXECUTE FUNCTION public.fn_pedido_produccion_ultima_modificacion();


--
-- TOC entry 5360 (class 2620 OID 44143)
-- Name: pedidos_compra trg_pedidos_compra_ultima_modificacion; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trg_pedidos_compra_ultima_modificacion BEFORE UPDATE ON public.pedidos_compra FOR EACH ROW EXECUTE FUNCTION public.fn_pedidos_compra_ultima_modificacion();


--
-- TOC entry 5361 (class 2620 OID 44154)
-- Name: presupuesto_compra trg_presupuesto_compra_ultima_modificacion; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trg_presupuesto_compra_ultima_modificacion BEFORE UPDATE ON public.presupuesto_compra FOR EACH ROW EXECUTE FUNCTION public.fn_presupuesto_compra_ultima_modificacion();


--
-- TOC entry 5340 (class 2606 OID 38666)
-- Name: ajustes_detalle ajuste_stock_detalle_ajuste_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ajustes_detalle
    ADD CONSTRAINT ajuste_stock_detalle_ajuste_fk FOREIGN KEY (id_ajuste) REFERENCES public.ajustes(id_ajuste);


--
-- TOC entry 5341 (class 2606 OID 39001)
-- Name: ajustes_detalle articulos_detalle_ajuste_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ajustes_detalle
    ADD CONSTRAINT articulos_detalle_ajuste_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5333 (class 2606 OID 38996)
-- Name: nota_detalle_compra articulos_detalle_credito_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_detalle_compra
    ADD CONSTRAINT articulos_detalle_credito_compra_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5331 (class 2606 OID 39006)
-- Name: nota_remision_detalle_compra articulos_detalle_nota_remision_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_detalle_compra
    ADD CONSTRAINT articulos_detalle_nota_remision_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5344 (class 2606 OID 38981)
-- Name: orden_detalle_compra articulos_detalle_orden_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_detalle_compra
    ADD CONSTRAINT articulos_detalle_orden_compra_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5346 (class 2606 OID 38976)
-- Name: presupuesto_detalle_compra articulos_detalle_presu_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.presupuesto_detalle_compra
    ADD CONSTRAINT articulos_detalle_presu_compra_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5335 (class 2606 OID 38991)
-- Name: factura_detalle_compra articulos_detalle_registro_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.factura_detalle_compra
    ADD CONSTRAINT articulos_detalle_registro_compra_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5337 (class 2606 OID 38986)
-- Name: stock_materia_prima articulos_stock_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_materia_prima
    ADD CONSTRAINT articulos_stock_detalle_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5211 (class 2606 OID 38661)
-- Name: personal cargos_empleados_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal
    ADD CONSTRAINT cargos_empleados_fk FOREIGN KEY (id_cargo) REFERENCES public.cargos(id_cargo);


--
-- TOC entry 5319 (class 2606 OID 38901)
-- Name: materia_prima categoria_articulos_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.materia_prima
    ADD CONSTRAINT categoria_articulos_fk FOREIGN KEY (id_unidad) REFERENCES public.unidad_medida(id_unidad);


--
-- TOC entry 5283 (class 2606 OID 38476)
-- Name: nota_remision_compra conductores_nota_remision_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_compra
    ADD CONSTRAINT conductores_nota_remision_compra_fk FOREIGN KEY (conductor_id) REFERENCES public.conductores(conductor_id);


--
-- TOC entry 5313 (class 2606 OID 38766)
-- Name: control_calidad_detalle control_calidad_produccion_control_calidad_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_calidad_detalle
    ADD CONSTRAINT control_calidad_produccion_control_calidad_detalle_fk FOREIGN KEY (calidad_id) REFERENCES public.control_calidad_produccion(calidad_id);


--
-- TOC entry 5275 (class 2606 OID 38771)
-- Name: perdidas control_calidad_produccion_perdidas_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.perdidas
    ADD CONSTRAINT control_calidad_produccion_perdidas_fk FOREIGN KEY (calidad_id) REFERENCES public.control_calidad_produccion(calidad_id);


--
-- TOC entry 5355 (class 2606 OID 66165)
-- Name: control_produccion_consumo control_produccion_consumo_control_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion_consumo
    ADD CONSTRAINT control_produccion_consumo_control_fk FOREIGN KEY (control_id) REFERENCES public.control_produccion(control_id) ON DELETE CASCADE;


--
-- TOC entry 5307 (class 2606 OID 38776)
-- Name: control_produccion_detalle control_produccion_control_produccion_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion_detalle
    ADD CONSTRAINT control_produccion_control_produccion_detalle_fk FOREIGN KEY (control_id) REFERENCES public.control_produccion(control_id);


--
-- TOC entry 5276 (class 2606 OID 38781)
-- Name: perdidas control_produccion_perdidas_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.perdidas
    ADD CONSTRAINT control_produccion_perdidas_fk FOREIGN KEY (control_id) REFERENCES public.control_produccion(control_id);


--
-- TOC entry 5322 (class 2606 OID 38696)
-- Name: costo_detalle_produccion costo_produccion_costo_detalle_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.costo_detalle_produccion
    ADD CONSTRAINT costo_produccion_costo_detalle_produccion_fk FOREIGN KEY (costo_id) REFERENCES public.costo_produccion(costo_id);


--
-- TOC entry 5242 (class 2606 OID 38481)
-- Name: ajustes deposito_ajustes_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ajustes
    ADD CONSTRAINT deposito_ajustes_fk FOREIGN KEY (deposito_id) REFERENCES public.deposito(deposito_id);


--
-- TOC entry 5284 (class 2606 OID 38486)
-- Name: nota_remision_compra deposito_nota_remision_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_compra
    ADD CONSTRAINT deposito_nota_remision_compra_fk FOREIGN KEY (deposito_id) REFERENCES public.deposito(deposito_id);


--
-- TOC entry 5225 (class 2606 OID 66058)
-- Name: pedido_materia_produccion deposito_pedido_materia_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_materia_produccion
    ADD CONSTRAINT deposito_pedido_materia_produccion_fk FOREIGN KEY (deposito_id) REFERENCES public.deposito(deposito_id);


--
-- TOC entry 5304 (class 2606 OID 66175)
-- Name: productos_terminados_detalle deposito_productos_terminados_det_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.productos_terminados_detalle
    ADD CONSTRAINT deposito_productos_terminados_det_fk FOREIGN KEY (deposito_id) REFERENCES public.deposito(deposito_id);


--
-- TOC entry 5252 (class 2606 OID 38501)
-- Name: reposicion_materia deposito_reposicion_materia_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reposicion_materia
    ADD CONSTRAINT deposito_reposicion_materia_fk FOREIGN KEY (deposito_id) REFERENCES public.deposito(deposito_id);


--
-- TOC entry 5338 (class 2606 OID 38491)
-- Name: stock_materia_prima deposito_stock_materia_prima_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_materia_prima
    ADD CONSTRAINT deposito_stock_materia_prima_fk FOREIGN KEY (deposito_id) REFERENCES public.deposito(deposito_id);


--
-- TOC entry 5316 (class 2606 OID 38496)
-- Name: stock_producto deposito_stock_producto_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_producto
    ADD CONSTRAINT deposito_stock_producto_fk FOREIGN KEY (deposito_id) REFERENCES public.deposito(deposito_id);


--
-- TOC entry 5236 (class 2606 OID 38431)
-- Name: equipo_detalle equipos_produccion_equipo_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipo_detalle
    ADD CONSTRAINT equipos_produccion_equipo_detalle_fk FOREIGN KEY (equipo_id) REFERENCES public.equipos_produccion(equipo_id);


--
-- TOC entry 5270 (class 2606 OID 66154)
-- Name: control_produccion etapa_control_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion
    ADD CONSTRAINT etapa_control_produccion_fk FOREIGN KEY (etapa_id) REFERENCES public.etapa_produccion(etapa_id);


--
-- TOC entry 5300 (class 2606 OID 38421)
-- Name: etapa_detalle_produccion etapa_produccion_etapa_detalle_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.etapa_detalle_produccion
    ADD CONSTRAINT etapa_produccion_etapa_detalle_produccion_fk FOREIGN KEY (etapa_id) REFERENCES public.etapa_produccion(etapa_id);


--
-- TOC entry 5233 (class 2606 OID 66128)
-- Name: trabajadores etapa_trabajadores_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trabajadores
    ADD CONSTRAINT etapa_trabajadores_fk FOREIGN KEY (id_etapa) REFERENCES public.etapa_produccion(etapa_id);


--
-- TOC entry 5244 (class 2606 OID 44157)
-- Name: cuentas_pagar factura_compra_cuentas_pagar_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cuentas_pagar
    ADD CONSTRAINT factura_compra_cuentas_pagar_fk FOREIGN KEY (id_factura_compra) REFERENCES public.factura_compra(id_factura_compra);


--
-- TOC entry 5336 (class 2606 OID 38826)
-- Name: factura_detalle_compra factura_compra_detalle_registro_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.factura_detalle_compra
    ADD CONSTRAINT factura_compra_detalle_registro_compra_fk FOREIGN KEY (id_factura_compra) REFERENCES public.factura_compra(id_factura_compra);


--
-- TOC entry 5282 (class 2606 OID 38836)
-- Name: iva_compra factura_compra_iva_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.iva_compra
    ADD CONSTRAINT factura_compra_iva_compra_fk FOREIGN KEY (id_factura_compra) REFERENCES public.factura_compra(id_factura_compra);


--
-- TOC entry 5285 (class 2606 OID 38831)
-- Name: nota_remision_compra factura_compra_nota_remision_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_compra
    ADD CONSTRAINT factura_compra_nota_remision_compra_fk FOREIGN KEY (id_factura_compra) REFERENCES public.factura_compra(id_factura_compra);


--
-- TOC entry 5348 (class 2606 OID 39052)
-- Name: bitacora fk_bitacora_usuario; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.bitacora
    ADD CONSTRAINT fk_bitacora_usuario FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 5212 (class 2606 OID 44200)
-- Name: personal fk_personal_sucursal; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal
    ADD CONSTRAINT fk_personal_sucursal FOREIGN KEY (id_sucursal) REFERENCES public.sucursales(id_sucursal);


--
-- TOC entry 5267 (class 2606 OID 38521)
-- Name: control_calidad_produccion inspectores_control_calidad_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_calidad_produccion
    ADD CONSTRAINT inspectores_control_calidad_produccion_fk FOREIGN KEY (id_inspectores) REFERENCES public.inspectores(id_inspectores);


--
-- TOC entry 5271 (class 2606 OID 38516)
-- Name: control_produccion inspectores_control_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion
    ADD CONSTRAINT inspectores_control_produccion_fk FOREIGN KEY (id_inspectores) REFERENCES public.inspectores(id_inspectores);


--
-- TOC entry 5356 (class 2606 OID 66170)
-- Name: control_produccion_consumo materia_prima_control_consumo_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion_consumo
    ADD CONSTRAINT materia_prima_control_consumo_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5323 (class 2606 OID 39026)
-- Name: costo_detalle_produccion materia_prima_costo_detalle_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.costo_detalle_produccion
    ADD CONSTRAINT materia_prima_costo_detalle_produccion_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5329 (class 2606 OID 39011)
-- Name: pedido_detalle_compra materia_prima_pedido_detalle_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_detalle_compra
    ADD CONSTRAINT materia_prima_pedido_detalle_compra_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5327 (class 2606 OID 39016)
-- Name: pedido_materia_detalle_produccion materia_prima_pedido_materia_detalle_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_materia_detalle_produccion
    ADD CONSTRAINT materia_prima_pedido_materia_detalle_produccion_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5357 (class 2606 OID 66209)
-- Name: receta_produccion materia_prima_receta_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.receta_produccion
    ADD CONSTRAINT materia_prima_receta_produccion_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5325 (class 2606 OID 39021)
-- Name: reposicion_materia_detalle materia_prima_reposicion_materia_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reposicion_materia_detalle
    ADD CONSTRAINT materia_prima_reposicion_materia_detalle_fk FOREIGN KEY (id_materia_prima) REFERENCES public.materia_prima(id_materia_prima);


--
-- TOC entry 5214 (class 2606 OID 38531)
-- Name: usuarios modulos_usuarios_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT modulos_usuarios_fk FOREIGN KEY (modulo_id) REFERENCES public.modulos(modulo_id);


--
-- TOC entry 5342 (class 2606 OID 38876)
-- Name: ajustes_detalle motivo_detalle_ajuste_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ajustes_detalle
    ADD CONSTRAINT motivo_detalle_ajuste_fk FOREIGN KEY (id_motivo) REFERENCES public.motivo(id_motivo);


--
-- TOC entry 5291 (class 2606 OID 38881)
-- Name: nota_compra motivo_nota_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_compra
    ADD CONSTRAINT motivo_nota_compra_fk FOREIGN KEY (id_motivo) REFERENCES public.motivo(id_motivo);


--
-- TOC entry 5292 (class 2606 OID 44170)
-- Name: nota_compra nota_compra_id_factura_compra_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_compra
    ADD CONSTRAINT nota_compra_id_factura_compra_fkey FOREIGN KEY (id_factura_compra) REFERENCES public.factura_compra(id_factura_compra);


--
-- TOC entry 5334 (class 2606 OID 38896)
-- Name: nota_detalle_compra nota_credi_compra_detalle_credito_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_detalle_compra
    ADD CONSTRAINT nota_credi_compra_detalle_credito_compra_fk FOREIGN KEY (id_nota_compra) REFERENCES public.nota_compra(id_nota_compra);


--
-- TOC entry 5286 (class 2606 OID 44181)
-- Name: nota_remision_compra nota_remision_compra_orden_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_compra
    ADD CONSTRAINT nota_remision_compra_orden_compra_fk FOREIGN KEY (id_orden_compra) REFERENCES public.orden_de_compra(id_orden_compra);


--
-- TOC entry 5332 (class 2606 OID 38841)
-- Name: nota_remision_detalle_compra nota_remision_detalle_nota_remision_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_detalle_compra
    ADD CONSTRAINT nota_remision_detalle_nota_remision_fk FOREIGN KEY (id_nota_remision) REFERENCES public.nota_remision_compra(id_nota_remision);


--
-- TOC entry 5345 (class 2606 OID 38716)
-- Name: orden_detalle_compra orden_de_compra_detalle_orden_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_detalle_compra
    ADD CONSTRAINT orden_de_compra_detalle_orden_compra_fk FOREIGN KEY (id_orden_compra) REFERENCES public.orden_de_compra(id_orden_compra);


--
-- TOC entry 5279 (class 2606 OID 38721)
-- Name: factura_compra orden_de_compra_factura_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.factura_compra
    ADD CONSTRAINT orden_de_compra_factura_compra_fk FOREIGN KEY (id_orden_compra) REFERENCES public.orden_de_compra(id_orden_compra);


--
-- TOC entry 5272 (class 2606 OID 38746)
-- Name: control_produccion orden_produccion_control_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion
    ADD CONSTRAINT orden_produccion_control_produccion_fk FOREIGN KEY (orden_id) REFERENCES public.orden_produccion(orden_id);


--
-- TOC entry 5250 (class 2606 OID 66106)
-- Name: costo_produccion orden_produccion_costo_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.costo_produccion
    ADD CONSTRAINT orden_produccion_costo_produccion_fk FOREIGN KEY (orden_id) REFERENCES public.orden_produccion(orden_id);


--
-- TOC entry 5220 (class 2606 OID 66095)
-- Name: equipos_produccion orden_produccion_equipos_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipos_produccion
    ADD CONSTRAINT orden_produccion_equipos_fk FOREIGN KEY (orden_id) REFERENCES public.orden_produccion(orden_id);


--
-- TOC entry 5309 (class 2606 OID 38741)
-- Name: orden_detalle_produccion orden_produccion_orden_detalle_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_detalle_produccion
    ADD CONSTRAINT orden_produccion_orden_detalle_produccion_fk FOREIGN KEY (orden_id) REFERENCES public.orden_produccion(orden_id);


--
-- TOC entry 5265 (class 2606 OID 38751)
-- Name: producto_terminado orden_produccion_producto_terminado_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.producto_terminado
    ADD CONSTRAINT orden_produccion_producto_terminado_fk FOREIGN KEY (orden_id) REFERENCES public.orden_produccion(orden_id);


--
-- TOC entry 5314 (class 2606 OID 38971)
-- Name: control_calidad_detalle parametros_control_control_calidad_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_calidad_detalle
    ADD CONSTRAINT parametros_control_control_calidad_detalle_fk FOREIGN KEY (parametro_id) REFERENCES public.parametros_control(parametro_id);


--
-- TOC entry 5328 (class 2606 OID 38466)
-- Name: pedido_materia_detalle_produccion pedido_materia_produccion_pedido_materia_detalle_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_materia_detalle_produccion
    ADD CONSTRAINT pedido_materia_produccion_pedido_materia_detalle_produccion_fk FOREIGN KEY (id_pedido_mat_prod) REFERENCES public.pedido_materia_produccion(id_pedido_mat_prod);


--
-- TOC entry 5253 (class 2606 OID 66065)
-- Name: reposicion_materia pedido_materia_reposicion_materia_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reposicion_materia
    ADD CONSTRAINT pedido_materia_reposicion_materia_fk FOREIGN KEY (id_pedido_mat_prod) REFERENCES public.pedido_materia_produccion(id_pedido_mat_prod);


--
-- TOC entry 5263 (class 2606 OID 66045)
-- Name: orden_produccion pedido_produccion_orden_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_produccion
    ADD CONSTRAINT pedido_produccion_orden_produccion_fk FOREIGN KEY (id_pedido_produccion) REFERENCES public.pedido_produccion(id_pedido_produccion);


--
-- TOC entry 5353 (class 2606 OID 66033)
-- Name: pedido_detalle_produccion pedido_produccion_pedido_detalle_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_detalle_produccion
    ADD CONSTRAINT pedido_produccion_pedido_detalle_produccion_fk FOREIGN KEY (id_pedido_produccion) REFERENCES public.pedido_produccion(id_pedido_produccion) ON DELETE CASCADE;


--
-- TOC entry 5330 (class 2606 OID 38681)
-- Name: pedido_detalle_compra pedidos_compra_pedido_detalle_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_detalle_compra
    ADD CONSTRAINT pedidos_compra_pedido_detalle_compra_fk FOREIGN KEY (id_pedido_compra) REFERENCES public.pedidos_compra(id_pedido_compra);


--
-- TOC entry 5255 (class 2606 OID 38676)
-- Name: presupuesto_compra pedidos_compra_presupuesto_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.presupuesto_compra
    ADD CONSTRAINT pedidos_compra_presupuesto_compra_fk FOREIGN KEY (id_pedido_compra) REFERENCES public.pedidos_compra(id_pedido_compra);


--
-- TOC entry 5302 (class 2606 OID 38786)
-- Name: perdidas_detalle perdidas_perdidas_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.perdidas_detalle
    ADD CONSTRAINT perdidas_perdidas_detalle_fk FOREIGN KEY (perdidas_id) REFERENCES public.perdidas(perdidas_id);


--
-- TOC entry 5231 (class 2606 OID 38151)
-- Name: inspectores personal_inspectores_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.inspectores
    ADD CONSTRAINT personal_inspectores_fk FOREIGN KEY (id_personal) REFERENCES public.personal(id_personal);


--
-- TOC entry 5234 (class 2606 OID 38146)
-- Name: trabajadores personal_trabajadores_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trabajadores
    ADD CONSTRAINT personal_trabajadores_fk FOREIGN KEY (id_personal) REFERENCES public.personal(id_personal);


--
-- TOC entry 5347 (class 2606 OID 38706)
-- Name: presupuesto_detalle_compra presupuesto_compra_detalle_presu_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.presupuesto_detalle_compra
    ADD CONSTRAINT presupuesto_compra_detalle_presu_compra_fk FOREIGN KEY (id_presupuesto_compra) REFERENCES public.presupuesto_compra(id_presupuesto_compra);


--
-- TOC entry 5259 (class 2606 OID 38711)
-- Name: orden_de_compra presupuesto_compra_orden_de_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_de_compra
    ADD CONSTRAINT presupuesto_compra_orden_de_compra_fk FOREIGN KEY (id_presupuesto_compra) REFERENCES public.presupuesto_compra(id_presupuesto_compra);


--
-- TOC entry 5268 (class 2606 OID 38761)
-- Name: control_calidad_produccion producto_terminado_control_calidad_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_calidad_produccion
    ADD CONSTRAINT producto_terminado_control_calidad_produccion_fk FOREIGN KEY (terminado_id) REFERENCES public.producto_terminado(terminado_id);


--
-- TOC entry 5305 (class 2606 OID 38756)
-- Name: productos_terminados_detalle producto_terminado_productos_terminados_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.productos_terminados_detalle
    ADD CONSTRAINT producto_terminado_productos_terminados_detalle_fk FOREIGN KEY (terminado_id) REFERENCES public.producto_terminado(terminado_id);


--
-- TOC entry 5315 (class 2606 OID 38956)
-- Name: control_calidad_detalle productos_control_calidad_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_calidad_detalle
    ADD CONSTRAINT productos_control_calidad_detalle_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id);


--
-- TOC entry 5308 (class 2606 OID 38946)
-- Name: control_produccion_detalle productos_control_produccion_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion_detalle
    ADD CONSTRAINT productos_control_produccion_detalle_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id);


--
-- TOC entry 5273 (class 2606 OID 66149)
-- Name: control_produccion productos_control_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion
    ADD CONSTRAINT productos_control_produccion_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id);


--
-- TOC entry 5301 (class 2606 OID 38966)
-- Name: etapa_detalle_produccion productos_etapa_detalle_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.etapa_detalle_produccion
    ADD CONSTRAINT productos_etapa_detalle_produccion_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id);


--
-- TOC entry 5218 (class 2606 OID 66141)
-- Name: etapa_produccion productos_etapa_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.etapa_produccion
    ADD CONSTRAINT productos_etapa_produccion_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id);


--
-- TOC entry 5310 (class 2606 OID 38941)
-- Name: orden_detalle_produccion productos_orden_detalle_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_detalle_produccion
    ADD CONSTRAINT productos_orden_detalle_produccion_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id);


--
-- TOC entry 5311 (class 2606 OID 38916)
-- Name: parametros_control productos_parametros_control_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.parametros_control
    ADD CONSTRAINT productos_parametros_control_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id);


--
-- TOC entry 5354 (class 2606 OID 66038)
-- Name: pedido_detalle_produccion productos_pedido_detalle_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_detalle_produccion
    ADD CONSTRAINT productos_pedido_detalle_produccion_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id);


--
-- TOC entry 5303 (class 2606 OID 38961)
-- Name: perdidas_detalle productos_perdidas_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.perdidas_detalle
    ADD CONSTRAINT productos_perdidas_detalle_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id);


--
-- TOC entry 5306 (class 2606 OID 38951)
-- Name: productos_terminados_detalle productos_productos_terminados_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.productos_terminados_detalle
    ADD CONSTRAINT productos_productos_terminados_detalle_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id);


--
-- TOC entry 5358 (class 2606 OID 66204)
-- Name: receta_produccion productos_receta_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.receta_produccion
    ADD CONSTRAINT productos_receta_produccion_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id);


--
-- TOC entry 5317 (class 2606 OID 38911)
-- Name: stock_producto productos_stock_producto_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_producto
    ADD CONSTRAINT productos_stock_producto_fk FOREIGN KEY (producto_id) REFERENCES public.productos(producto_id);


--
-- TOC entry 5297 (class 2606 OID 43493)
-- Name: productos productos_tipo_iva_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT productos_tipo_iva_fk FOREIGN KEY (iva_id) REFERENCES public.tipo_iva(iva_id);


--
-- TOC entry 5245 (class 2606 OID 38576)
-- Name: cuentas_pagar proveedor_cuentas_pagar_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cuentas_pagar
    ADD CONSTRAINT proveedor_cuentas_pagar_fk FOREIGN KEY (id_proveedor) REFERENCES public.proveedor(id_proveedor);


--
-- TOC entry 5293 (class 2606 OID 38581)
-- Name: nota_compra proveedor_nota_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_compra
    ADD CONSTRAINT proveedor_nota_compra_fk FOREIGN KEY (id_proveedor) REFERENCES public.proveedor(id_proveedor);


--
-- TOC entry 5287 (class 2606 OID 38571)
-- Name: nota_remision_compra proveedor_nota_remision_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_compra
    ADD CONSTRAINT proveedor_nota_remision_fk FOREIGN KEY (id_proveedor) REFERENCES public.proveedor(id_proveedor);


--
-- TOC entry 5260 (class 2606 OID 38566)
-- Name: orden_de_compra proveedor_orden_de_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_de_compra
    ADD CONSTRAINT proveedor_orden_de_compra_fk FOREIGN KEY (id_proveedor) REFERENCES public.proveedor(id_proveedor);


--
-- TOC entry 5256 (class 2606 OID 38561)
-- Name: presupuesto_compra proveedor_presupuesto_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.presupuesto_compra
    ADD CONSTRAINT proveedor_presupuesto_compra_fk FOREIGN KEY (id_proveedor) REFERENCES public.proveedor(id_proveedor);


--
-- TOC entry 5326 (class 2606 OID 38701)
-- Name: reposicion_materia_detalle reposicion_materia_reposicion_materia_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reposicion_materia_detalle
    ADD CONSTRAINT reposicion_materia_reposicion_materia_detalle_fk FOREIGN KEY (reposicion_id) REFERENCES public.reposicion_materia(reposicion_id);


--
-- TOC entry 5343 (class 2606 OID 39031)
-- Name: ajustes_detalle stock_materia_prima_ajustes_detallle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ajustes_detalle
    ADD CONSTRAINT stock_materia_prima_ajustes_detallle_fk FOREIGN KEY (id_stock) REFERENCES public.stock_materia_prima(id_stock);


--
-- TOC entry 5246 (class 2606 OID 38646)
-- Name: cuentas_pagar sucursales_cuentas_pagar_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cuentas_pagar
    ADD CONSTRAINT sucursales_cuentas_pagar_fk FOREIGN KEY (id_sucursal) REFERENCES public.sucursales(id_sucursal);


--
-- TOC entry 5221 (class 2606 OID 66100)
-- Name: equipos_produccion sucursales_equipos_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipos_produccion
    ADD CONSTRAINT sucursales_equipos_produccion_fk FOREIGN KEY (id_sucursal) REFERENCES public.sucursales(id_sucursal);


--
-- TOC entry 5280 (class 2606 OID 38621)
-- Name: factura_compra sucursales_factura_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.factura_compra
    ADD CONSTRAINT sucursales_factura_compra_fk FOREIGN KEY (id_sucursal) REFERENCES public.sucursales(id_sucursal);


--
-- TOC entry 5294 (class 2606 OID 38626)
-- Name: nota_compra sucursales_nota_credi_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_compra
    ADD CONSTRAINT sucursales_nota_credi_compra_fk FOREIGN KEY (id_sucursal) REFERENCES public.sucursales(id_sucursal);


--
-- TOC entry 5261 (class 2606 OID 38616)
-- Name: orden_de_compra sucursales_orden_de_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_de_compra
    ADD CONSTRAINT sucursales_orden_de_compra_fk FOREIGN KEY (id_sucursal) REFERENCES public.sucursales(id_sucursal);


--
-- TOC entry 5226 (class 2606 OID 66053)
-- Name: pedido_materia_produccion sucursales_pedido_materia_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_materia_produccion
    ADD CONSTRAINT sucursales_pedido_materia_produccion_fk FOREIGN KEY (id_sucursal) REFERENCES public.sucursales(id_sucursal);


--
-- TOC entry 5350 (class 2606 OID 66028)
-- Name: pedido_produccion sucursales_pedido_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_produccion
    ADD CONSTRAINT sucursales_pedido_produccion_fk FOREIGN KEY (id_sucursal) REFERENCES public.sucursales(id_sucursal);


--
-- TOC entry 5248 (class 2606 OID 38606)
-- Name: pedidos_compra sucursales_pedidos_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedidos_compra
    ADD CONSTRAINT sucursales_pedidos_compra_fk FOREIGN KEY (id_sucursal) REFERENCES public.sucursales(id_sucursal);


--
-- TOC entry 5257 (class 2606 OID 38611)
-- Name: presupuesto_compra sucursales_presupuesto_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.presupuesto_compra
    ADD CONSTRAINT sucursales_presupuesto_compra_fk FOREIGN KEY (id_sucursal) REFERENCES public.sucursales(id_sucursal);


--
-- TOC entry 5215 (class 2606 OID 38631)
-- Name: usuarios sucursales_usuarios_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT sucursales_usuarios_fk FOREIGN KEY (id_sucursal) REFERENCES public.sucursales(id_sucursal);


--
-- TOC entry 5320 (class 2606 OID 38436)
-- Name: materia_prima tipo_iva_materia_prima_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.materia_prima
    ADD CONSTRAINT tipo_iva_materia_prima_fk FOREIGN KEY (iva_id) REFERENCES public.tipo_iva(iva_id);


--
-- TOC entry 5351 (class 2606 OID 66018)
-- Name: pedido_produccion tipo_pedido_pedido_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_produccion
    ADD CONSTRAINT tipo_pedido_pedido_produccion_fk FOREIGN KEY (id_tipo_pedido) REFERENCES public.tipo_pedido(id_tipo_pedido);


--
-- TOC entry 5277 (class 2606 OID 38461)
-- Name: perdidas tipo_perdida_perdidas_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.perdidas
    ADD CONSTRAINT tipo_perdida_perdidas_fk FOREIGN KEY (tipo_perdida_id) REFERENCES public.tipo_perdida(tipo_perdida_id);


--
-- TOC entry 5324 (class 2606 OID 66120)
-- Name: costo_detalle_produccion trabajadores_costo_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.costo_detalle_produccion
    ADD CONSTRAINT trabajadores_costo_detalle_fk FOREIGN KEY (trabajadores_id) REFERENCES public.trabajadores(trabajadores_id);


--
-- TOC entry 5237 (class 2606 OID 38526)
-- Name: equipo_detalle trabajadores_equipo_detalle_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipo_detalle
    ADD CONSTRAINT trabajadores_equipo_detalle_fk FOREIGN KEY (trabajadores_id) REFERENCES public.trabajadores(trabajadores_id);


--
-- TOC entry 5298 (class 2606 OID 38906)
-- Name: productos unidad_medida_productos_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT unidad_medida_productos_fk FOREIGN KEY (id_unidad) REFERENCES public.unidad_medida(id_unidad);


--
-- TOC entry 5243 (class 2606 OID 38231)
-- Name: ajustes usuarios_ajustes_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.ajustes
    ADD CONSTRAINT usuarios_ajustes_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5241 (class 2606 OID 38236)
-- Name: cargos usuarios_cargos_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cargos
    ADD CONSTRAINT usuarios_cargos_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5296 (class 2606 OID 38271)
-- Name: unidad_medida usuarios_categoria_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.unidad_medida
    ADD CONSTRAINT usuarios_categoria_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5229 (class 2606 OID 38316)
-- Name: conductores usuarios_conductores_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.conductores
    ADD CONSTRAINT usuarios_conductores_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5269 (class 2606 OID 38396)
-- Name: control_calidad_produccion usuarios_control_calidad_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_calidad_produccion
    ADD CONSTRAINT usuarios_control_calidad_produccion_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5274 (class 2606 OID 38386)
-- Name: control_produccion usuarios_control_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.control_produccion
    ADD CONSTRAINT usuarios_control_produccion_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5251 (class 2606 OID 38416)
-- Name: costo_produccion usuarios_costo_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.costo_produccion
    ADD CONSTRAINT usuarios_costo_produccion_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5247 (class 2606 OID 38226)
-- Name: cuentas_pagar usuarios_cuentas_pagar_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cuentas_pagar
    ADD CONSTRAINT usuarios_cuentas_pagar_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5230 (class 2606 OID 38306)
-- Name: deposito usuarios_deposito_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.deposito
    ADD CONSTRAINT usuarios_deposito_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5222 (class 2606 OID 38381)
-- Name: equipos_produccion usuarios_equipos_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.equipos_produccion
    ADD CONSTRAINT usuarios_equipos_produccion_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5219 (class 2606 OID 38391)
-- Name: etapa_produccion usuarios_etapa_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.etapa_produccion
    ADD CONSTRAINT usuarios_etapa_produccion_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5281 (class 2606 OID 38201)
-- Name: factura_compra usuarios_factura_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.factura_compra
    ADD CONSTRAINT usuarios_factura_compra_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5216 (class 2606 OID 43507)
-- Name: usuarios usuarios_id_cargo_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_id_cargo_fk FOREIGN KEY (id_cargo) REFERENCES public.cargos(id_cargo);


--
-- TOC entry 5217 (class 2606 OID 44228)
-- Name: usuarios usuarios_id_personal_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_id_personal_fk FOREIGN KEY (id_personal) REFERENCES public.personal(id_personal) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 5232 (class 2606 OID 38296)
-- Name: inspectores usuarios_inspectores_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.inspectores
    ADD CONSTRAINT usuarios_inspectores_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5321 (class 2606 OID 38256)
-- Name: materia_prima usuarios_materia_prima_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.materia_prima
    ADD CONSTRAINT usuarios_materia_prima_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5238 (class 2606 OID 38286)
-- Name: modulos usuarios_modulos_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.modulos
    ADD CONSTRAINT usuarios_modulos_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5290 (class 2606 OID 38311)
-- Name: motivo usuarios_motivo_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.motivo
    ADD CONSTRAINT usuarios_motivo_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5295 (class 2606 OID 38206)
-- Name: nota_compra usuarios_nota_credi_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_compra
    ADD CONSTRAINT usuarios_nota_credi_compra_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5288 (class 2606 OID 38211)
-- Name: nota_remision_compra usuarios_nota_remision_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_compra
    ADD CONSTRAINT usuarios_nota_remision_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5262 (class 2606 OID 38196)
-- Name: orden_de_compra usuarios_orden_de_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_de_compra
    ADD CONSTRAINT usuarios_orden_de_compra_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5264 (class 2606 OID 38376)
-- Name: orden_produccion usuarios_orden_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.orden_produccion
    ADD CONSTRAINT usuarios_orden_produccion_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5312 (class 2606 OID 38371)
-- Name: parametros_control usuarios_parametros_control_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.parametros_control
    ADD CONSTRAINT usuarios_parametros_control_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5227 (class 2606 OID 38326)
-- Name: pedido_materia_produccion usuarios_pedido_materia_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_materia_produccion
    ADD CONSTRAINT usuarios_pedido_materia_produccion_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5352 (class 2606 OID 66023)
-- Name: pedido_produccion usuarios_pedido_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedido_produccion
    ADD CONSTRAINT usuarios_pedido_produccion_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5249 (class 2606 OID 38186)
-- Name: pedidos_compra usuarios_pedidos_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.pedidos_compra
    ADD CONSTRAINT usuarios_pedidos_compra_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5278 (class 2606 OID 38406)
-- Name: perdidas usuarios_perdidas_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.perdidas
    ADD CONSTRAINT usuarios_perdidas_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5213 (class 2606 OID 38266)
-- Name: personal usuarios_personal_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.personal
    ADD CONSTRAINT usuarios_personal_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5258 (class 2606 OID 38191)
-- Name: presupuesto_compra usuarios_presupuesto_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.presupuesto_compra
    ADD CONSTRAINT usuarios_presupuesto_compra_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5266 (class 2606 OID 38401)
-- Name: producto_terminado usuarios_producto_terminado_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.producto_terminado
    ADD CONSTRAINT usuarios_producto_terminado_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5299 (class 2606 OID 38356)
-- Name: productos usuarios_productos_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.productos
    ADD CONSTRAINT usuarios_productos_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5239 (class 2606 OID 38276)
-- Name: proveedor usuarios_proveedor_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.proveedor
    ADD CONSTRAINT usuarios_proveedor_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5359 (class 2606 OID 66214)
-- Name: receta_produccion usuarios_receta_produccion_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.receta_produccion
    ADD CONSTRAINT usuarios_receta_produccion_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5254 (class 2606 OID 38411)
-- Name: reposicion_materia usuarios_reposicion_materia_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.reposicion_materia
    ADD CONSTRAINT usuarios_reposicion_materia_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5339 (class 2606 OID 38366)
-- Name: stock_materia_prima usuarios_stock_materia_prima_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_materia_prima
    ADD CONSTRAINT usuarios_stock_materia_prima_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5318 (class 2606 OID 38361)
-- Name: stock_producto usuarios_stock_producto_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.stock_producto
    ADD CONSTRAINT usuarios_stock_producto_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5240 (class 2606 OID 38251)
-- Name: sucursales usuarios_sucursales_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sucursales
    ADD CONSTRAINT usuarios_sucursales_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5223 (class 2606 OID 38351)
-- Name: tipo_iva usuarios_tipo_iva_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_iva
    ADD CONSTRAINT usuarios_tipo_iva_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5349 (class 2606 OID 65996)
-- Name: tipo_pedido usuarios_tipo_pedido_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_pedido
    ADD CONSTRAINT usuarios_tipo_pedido_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5224 (class 2606 OID 38331)
-- Name: tipo_perdida usuarios_tipo_perdida_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.tipo_perdida
    ADD CONSTRAINT usuarios_tipo_perdida_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5235 (class 2606 OID 38291)
-- Name: trabajadores usuarios_trabajadores_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trabajadores
    ADD CONSTRAINT usuarios_trabajadores_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5228 (class 2606 OID 38321)
-- Name: vehiculos usuarios_vehiculos_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.vehiculos
    ADD CONSTRAINT usuarios_vehiculos_fk FOREIGN KEY (id_usuario) REFERENCES public.usuarios(id_usuario);


--
-- TOC entry 5289 (class 2606 OID 38471)
-- Name: nota_remision_compra vehiculos_nota_remision_compra_fk; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.nota_remision_compra
    ADD CONSTRAINT vehiculos_nota_remision_compra_fk FOREIGN KEY (vehiculo_id) REFERENCES public.vehiculos(vehiculo_id);


-- Completed on 2026-06-24 21:07:17

--
-- PostgreSQL database dump complete
--

