export function lineDiscountMethod(line) {
  const raw = line && (line.discount_method != null ? line.discount_method : line.discount_Method);
  const method = String(raw == null ? '1' : raw).trim().toLowerCase();
  return ['2', 'fixed', 'amount', 'value'].includes(method) ? '2' : '1';
}

export function lineDiscountValue(line) {
  const raw = line && (line.discount_value != null ? line.discount_value : line.discount);
  const value = Number(raw || 0);
  return Number.isFinite(value) && value > 0 ? value : 0;
}

export function lineDiscountUnitAmount(line) {
  const supplied = Number(line && line.discount_unit_amount);
  if (Number.isFinite(supplied) && supplied >= 0) return supplied;

  const legacy = Number(line && line.DiscountNet);
  if (Number.isFinite(legacy) && legacy >= 0) return legacy;

  const value = lineDiscountValue(line);
  const price = Number(line && (line.price != null ? line.price : line.Unit_price));
  if (lineDiscountMethod(line) === '2') return value;
  return Number.isFinite(price) && price > 0 ? price * value / 100 : 0;
}

export function lineDiscountAmount(line) {
  const supplied = Number(line && line.discount_line_amount);
  if (Number.isFinite(supplied) && supplied >= 0) return supplied;

  const quantity = Number(line && line.quantity);
  return lineDiscountUnitAmount(line) * (Number.isFinite(quantity) && quantity > 0 ? quantity : 0);
}

export function hasLineDiscount(line) {
  return lineDiscountValue(line) > 0 && lineDiscountAmount(line) > 0;
}
