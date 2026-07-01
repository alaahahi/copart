/**
 * يبني نص الوصف/الملاحظة من حقول السائق والشحنة.
 */
export function buildWalletTransactionNote(fields = {}) {
  const parts = [];
  const driver = fields.driver_name != null ? String(fields.driver_name).trim() : '';
  const cmr = fields.cmr != null ? String(fields.cmr).trim() : '';
  const carsCount = fields.cars_count;
  const entryDate = fields.entry_date != null ? String(fields.entry_date).trim() : '';
  const tag = fields.tag != null ? String(fields.tag).trim() : '';

  if (driver) {
    parts.push(`سائق: ${driver}`);
  }
  if (cmr) {
    parts.push(`CMR: ${cmr}`);
  }
  if (carsCount !== '' && carsCount != null && !Number.isNaN(Number(carsCount))) {
    parts.push(`${carsCount} سيارة`);
  }
  if (entryDate) {
    parts.push(`دخول: ${entryDate}`);
  }
  if (tag) {
    parts.push(`تاغ: ${tag}`);
  }

  return parts.join(' | ');
}
