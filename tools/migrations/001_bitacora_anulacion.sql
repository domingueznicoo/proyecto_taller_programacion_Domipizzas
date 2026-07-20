-- Permite registrar anulaciones documentales en bitácora (casos de uso Compras).
ALTER TABLE bitacora DROP CONSTRAINT IF EXISTS ck_bitacora_accion;
ALTER TABLE bitacora ADD CONSTRAINT ck_bitacora_accion CHECK (
    accion_realizada IN ('ALTA', 'MODIFICACION', 'ELIMINACION', 'INACTIVACION', 'ANULACION')
);
