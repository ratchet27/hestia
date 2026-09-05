// One stock entry is exactly one unit of a product: `addStock` creates N
// entries for quantity N and `consumeStock` deletes entries one by one. The UI
// therefore never reads a quantity field off an entry; it is always 1.
export function entryQuantity(unit: string): string {
  return `1 ${unit}`;
}
