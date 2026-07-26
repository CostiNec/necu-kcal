export type MassUnit = 'g' | 'kg' | 'oz' | 'lb';

export const massUnits: MassUnit[] = ['g', 'kg', 'oz', 'lb'];

const gramsPerUnit: Record<MassUnit, number> = {
    g: 1,
    kg: 1000,
    oz: 28.349523125,
    lb: 453.59237,
};

export function toGrams(
    amount: number,
    unit: MassUnit,
    quantity = 1,
): number {
    return amount * gramsPerUnit[unit] * quantity;
}
