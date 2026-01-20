import { useMemo, useState } from "react";
import type {
  ExpiringEntryResponse,
  StockEntryResponse,
} from "../../api/generated/models";
import { useLocations, useProducts } from "../../api/queries";
import {
  useAddStock,
  useConsumeStock,
  useExpiringStock,
  useStockEntries,
} from "../../api/queries/stocks";
import {
  type AddStockFormData,
  AddStockModal,
} from "./components/AddStockModal";
import { AttentionSection } from "./components/AttentionSection";
import { LocationTabs } from "./components/LocationTabs";
import { StockPageHeader } from "./components/StockPageHeader";
import { StockTable } from "./components/StockTable";

export function StockPage(): React.ReactElement {
  const [selectedLocationId, setSelectedLocationId] = useState<string | null>(
    null,
  );
  const [searchTerm, setSearchTerm] = useState("");
  const [showAddModal, setShowAddModal] = useState(false);

  const { data: locations = [] } = useLocations();
  const { data: products = [] } = useProducts();
  const { data: allEntries = [], isLoading: entriesLoading } =
    useStockEntries();
  const { data: expiringItems = [] } = useExpiringStock(7);

  const addStock = useAddStock();
  const consumeStock = useConsumeStock();

  // Filter entries by location and search term (client-side)
  const filteredEntries = useMemo(() => {
    let result = allEntries;

    // Filter by location
    if (selectedLocationId) {
      result = result.filter((e) => e.location.id === selectedLocationId);
    }

    // Filter by search term
    if (searchTerm.trim()) {
      const term = searchTerm.toLowerCase();
      result = result.filter((e) =>
        e.product.name.toLowerCase().includes(term),
      );
    }

    return result;
  }, [allEntries, selectedLocationId, searchTerm]);

  // Sort by expiry date
  const sortedEntries = useMemo(() => {
    return [...filteredEntries].sort((a, b) => {
      if (!a.best_before) return 1;
      if (!b.best_before) return -1;
      return (
        new Date(a.best_before).getTime() - new Date(b.best_before).getTime()
      );
    });
  }, [filteredEntries]);

  // Count entries per location (from ALL entries, not filtered)
  const locationCounts = useMemo(() => {
    const counts: Record<string, number> = {};
    for (const entry of allEntries) {
      counts[entry.location.id] = (counts[entry.location.id] || 0) + 1;
    }
    return counts;
  }, [allEntries]);

  // Count expired and soon-to-expire items
  const expiredCount = expiringItems.filter(
    (item) => item.days_until_expiry < 0,
  ).length;
  const soonCount = expiringItems.filter(
    (item) => item.days_until_expiry >= 0,
  ).length;

  const handleAddStock = (data: AddStockFormData) => {
    addStock.mutate(
      {
        product_id: data.productId,
        location_id: data.locationId,
        quantity: data.quantity,
        best_before: data.bestBefore || null,
      },
      {
        onSuccess: () => setShowAddModal(false),
      },
    );
  };

  const handleConsume = (entry: StockEntryResponse) => {
    consumeStock.mutate({
      product_id: entry.product.id,
      location_id: entry.location.id,
      quantity: 1,
    });
  };

  const handleDone = (entry: ExpiringEntryResponse) => {
    consumeStock.mutate({
      product_id: entry.product.id,
      location_id: entry.location.id,
      quantity: 1,
    });
  };

  const handleThrow = (entry: ExpiringEntryResponse) => {
    // For now, throw also consumes (removes) the item
    consumeStock.mutate({
      product_id: entry.product.id,
      location_id: entry.location.id,
      quantity: 1,
    });
  };

  return (
    <div className="p-8 max-w-[1200px]">
      <StockPageHeader
        expiredCount={expiredCount}
        soonCount={soonCount}
        onScanClick={() => setShowAddModal(true)}
        onAddClick={() => setShowAddModal(true)}
      />

      <AttentionSection
        items={expiringItems}
        onDone={handleDone}
        onThrow={handleThrow}
      />

      <section>
        <div className="flex justify-between items-center mb-4">
          <h2 className="text-stone-800 font-semibold">&#x1f4e6; Все запасы</h2>
          <div className="relative">
            <input
              type="text"
              placeholder="Поиск по названию..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="w-60 px-3.5 py-2 border border-stone-200 rounded-md text-sm focus:outline-none focus:border-amber-500"
            />
          </div>
        </div>

        <LocationTabs
          locations={locations}
          selectedLocationId={selectedLocationId}
          onSelect={setSelectedLocationId}
          counts={locationCounts}
          totalCount={allEntries.length}
        />

        <StockTable
          entries={sortedEntries}
          onConsume={handleConsume}
          isLoading={entriesLoading}
        />
      </section>

      {showAddModal && (
        <AddStockModal
          products={products}
          locations={locations}
          onSubmit={handleAddStock}
          onClose={() => setShowAddModal(false)}
          isSubmitting={addStock.isPending}
        />
      )}
    </div>
  );
}
