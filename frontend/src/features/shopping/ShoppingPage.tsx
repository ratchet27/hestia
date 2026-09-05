import { type ReactElement, useState } from "react";
import { useTranslation } from "react-i18next";
import type { ShoppingItemResponse } from "@/api/generated/models";
import {
  useAddShoppingItem,
  useDeleteShoppingItem,
  useShoppingList,
  useUpdateShoppingItem,
} from "@/api/queries";
import { PageHeader } from "@/components/PageHeader";
import {
  EditItemModal,
  ProductSearchInput,
  ShoppingListItem,
} from "./components";

export function ShoppingPage(): ReactElement {
  const { t } = useTranslation();
  const [editingItem, setEditingItem] = useState<ShoppingItemResponse | null>(
    null,
  );

  const { data: items = [], isLoading } = useShoppingList();
  const addMutation = useAddShoppingItem();
  const updateMutation = useUpdateShoppingItem();
  const deleteMutation = useDeleteShoppingItem();

  const pendingItems = items.filter((item) => !item.done);

  const handleAddProduct = (productId: string) => {
    addMutation.mutate({ product_id: productId, amount: 1 });
  };

  const handleAddCustom = (name: string) => {
    addMutation.mutate({ custom_name: name, amount: 1 });
  };

  const handleDelete = (id: string) => {
    deleteMutation.mutate(id);
  };

  const handleSave = (id: string, amount: number, note: string) => {
    updateMutation.mutate(
      { id, data: { amount, note: note || null } },
      {
        onSuccess: () => setEditingItem(null),
      },
    );
  };

  return (
    <div className="p-8">
      <PageHeader
        title={t("shopping.title")}
        subtitle={t("shopping.subtitle")}
        actions={
          <div className="text-right">
            <p className="text-2xl font-bold text-amber-600">
              {pendingItems.length}
            </p>
            <p className="text-sm text-stone-500">{t("shopping.toBuy")}</p>
          </div>
        }
      />

      <div className="bg-white rounded-xl p-4 shadow-sm border border-stone-200 mb-6">
        <ProductSearchInput
          onAddProduct={handleAddProduct}
          onAddCustom={handleAddCustom}
          isAdding={addMutation.isPending}
        />
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-stone-200">
        <div className="p-4 border-b border-stone-100">
          <h3 className="font-semibold text-stone-800">
            {t("shopping.pending")}
          </h3>
        </div>
        <div className="divide-y divide-stone-100">
          {isLoading ? (
            <div className="p-4 text-center text-stone-500">
              {t("common.loading")}
            </div>
          ) : pendingItems.length === 0 ? (
            <p className="p-4 text-stone-500">{t("shopping.empty")}</p>
          ) : (
            pendingItems.map((item) => (
              <ShoppingListItem
                key={item.id}
                item={item}
                onClick={() => setEditingItem(item)}
                onDelete={() => handleDelete(item.id)}
                isDeleting={deleteMutation.isPending}
              />
            ))
          )}
        </div>
      </div>

      {editingItem && (
        <EditItemModal
          key={editingItem.id}
          item={editingItem}
          onClose={() => setEditingItem(null)}
          onSave={handleSave}
          isSaving={updateMutation.isPending}
        />
      )}
    </div>
  );
}
