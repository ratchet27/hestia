import type {
  BarcodeResponse,
  ProductResponse,
} from "../../api/generated/models";

// The API serialises `barcodes` either as an array or as an object keyed by
// index (a Doctrine collection quirk the generated type reflects); normalise
// once here instead of at every read site.
export function barcodesOf(
  product: Pick<ProductResponse, "barcodes"> | undefined,
): BarcodeResponse[] {
  const barcodes = product?.barcodes;
  if (!barcodes) return [];
  return Array.isArray(barcodes) ? barcodes : Object.values(barcodes);
}
