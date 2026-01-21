// Palette of colors with good contrast on white cards
const categoryPalette = [
  "#dc2626", // red-600
  "#ea580c", // orange-600
  "#d97706", // amber-600
  "#65a30d", // lime-600
  "#16a34a", // green-600
  "#0d9488", // teal-600
  "#0891b2", // cyan-600
  "#2563eb", // blue-600
  "#7c3aed", // violet-600
  "#c026d3", // fuchsia-600
  "#db2777", // pink-600
  "#059669", // emerald-600
];

/**
 * Generate a consistent color for a category based on its name.
 * Same name always produces the same color.
 */
export function getCategoryColor(categoryName: string): string {
  let hash = 0;
  for (let i = 0; i < categoryName.length; i++) {
    const char = categoryName.charCodeAt(i);
    hash = (hash << 5) - hash + char;
    hash = hash & hash; // Convert to 32-bit integer
  }
  const index = Math.abs(hash) % categoryPalette.length;
  return categoryPalette[index] ?? "#78716c"; // fallback to stone-500
}
