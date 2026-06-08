export const CAR_FIELD_LABELS = {
  americaPrice: 'سعر السيارة امريكا',
  americaTransfer: 'نقل امريكا',
  recovery: 'ريكفري',
  repairExpenses: 'مصاريف تصليح',
  erbilTransfer: 'نقل اربيل',
  erbilShipping: 'شحن اربيل وتخليص',
  erbilClearance: 'تخليص',
  erbilTransferFee: 'نقل',
  erbilBorderRepair: 'تصليح حدود',
  erbilCustoms: 'جمرك',
};

export const asCarNumber = (v) => {
  if (v === null || v === undefined || v === '') return 0;
  if (typeof v === 'number') return Number.isFinite(v) ? v : 0;
  const n = Number.parseFloat(String(v).replace(/,/g, ''));
  return Number.isFinite(n) ? n : 0;
};

/** Sum of the 5 نقل اربيل fields (+ legacy commission for old rows). */
export const erbilTransferTotal = (car, sales = false) => {
  const suffix = sales ? '_s' : '';
  return (
    asCarNumber(car?.[`expenses${suffix}`]) +
    asCarNumber(car?.[`erbil_clearance${suffix}`]) +
    asCarNumber(car?.[`erbil_transfer${suffix}`]) +
    asCarNumber(car?.[`erbil_border_repair${suffix}`]) +
    asCarNumber(car?.[`erbil_customs${suffix}`]) +
    asCarNumber(car?.[`commission${suffix}`])
  );
};

/** Ensure edit forms have the new erbil fields (old data stays in expenses). */
export const ensureErbilFormFields = (formData, sales = false) => {
  const fields = sales
    ? [
        'expenses_s',
        'erbil_clearance_s',
        'erbil_transfer_s',
        'erbil_border_repair_s',
        'erbil_customs_s',
        'commission_s',
      ]
    : [
        'expenses',
        'erbil_clearance',
        'erbil_transfer',
        'erbil_border_repair',
        'erbil_customs',
        'commission',
      ];

  fields.forEach((key) => {
    if (formData[key] === undefined || formData[key] === null) {
      formData[key] = 0;
    }
  });

  return formData;
};
