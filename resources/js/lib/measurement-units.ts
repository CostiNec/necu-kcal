export type NutritionBasisUnit = 'g' | 'ml';
export type MeasurementUnit = 'g' | 'kg' | 'oz' | 'lb' | 'ml' | 'l';

export const massUnits: MeasurementUnit[] = ['g', 'kg', 'oz', 'lb'];
export const volumeUnits: MeasurementUnit[] = ['ml', 'l'];

const baseAmountPerUnit: Record<MeasurementUnit, number> = {
    g: 1,
    kg: 1000,
    oz: 28.349523125,
    lb: 453.59237,
    ml: 1,
    l: 1000,
};

export function unitsForBasis(
    basisUnit: NutritionBasisUnit,
): MeasurementUnit[] {
    return basisUnit === 'ml' ? volumeUnits : massUnits;
}

export function basisForUnit(unit: MeasurementUnit): NutritionBasisUnit {
    return volumeUnits.includes(unit) ? 'ml' : 'g';
}

export function toBaseAmount(
    amount: number,
    unit: MeasurementUnit,
    quantity = 1,
): number {
    return amount * baseAmountPerUnit[unit] * quantity;
}
