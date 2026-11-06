-- Migración desde v0.2.0-dev. Respaldar antes; ejecutar una sola vez.
-- No elimina cálculos. El de mayor id por registro/escala queda como vigente.

ALTER TABLE record_calculations
  ADD COLUMN is_current TINYINT(1) NOT NULL DEFAULT 1,
  ADD INDEX idx_calc_current(record_id,is_current,scale);

UPDATE record_calculations SET is_current=0;

UPDATE record_calculations rc
JOIN (
  SELECT record_id,scale,MAX(id) AS latest_id
  FROM record_calculations
  GROUP BY record_id,scale
) latest ON latest.record_id=rc.record_id AND latest.scale=rc.scale AND latest.latest_id=rc.id
SET rc.is_current=1;

INSERT INTO schema_migrations(version) VALUES('003_current_calculation_marker');
