-- Observaciones en orden de compra (alineado a pedido_observaciones / presu_observaciones).
ALTER TABLE orden_de_compra
    ADD COLUMN IF NOT EXISTS orden_observaciones TEXT;

COMMENT ON COLUMN orden_de_compra.orden_observaciones IS
    'Observaciones o condiciones adicionales de la orden de compra';
