import type { ReactElement } from "react";
export function ProductSkeleton(): ReactElement {
  return (
    <div className="bg-white rounded-xl p-4 shadow-sm border border-stone-200 animate-pulse">
      <div className="flex justify-between items-start mb-3">
        <div className="h-6 w-20 bg-stone-200 rounded" />
      </div>
      <div className="h-5 w-3/4 bg-stone-200 rounded mb-2" />
      <div className="space-y-2">
        <div className="h-4 w-1/2 bg-stone-200 rounded" />
        <div className="h-4 w-2/3 bg-stone-200 rounded" />
        <div className="h-4 w-1/3 bg-stone-200 rounded" />
      </div>
      <div className="mt-3 pt-3 border-t border-stone-100 flex justify-between items-center">
        <div className="h-6 w-12 bg-stone-200 rounded" />
        <div className="h-4 w-16 bg-stone-200 rounded" />
      </div>
    </div>
  );
}

export function ProductsGridSkeleton({
  count = 6,
}: {
  count?: number;
}): ReactElement {
  return (
    <div className="grid grid-cols-3 gap-4">
      {Array.from({ length: count }).map((_, i) => (
        <ProductSkeleton key={i} />
      ))}
    </div>
  );
}
