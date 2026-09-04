import { useState } from "react";
import type { ShoppingItemResponse } from "@/api/generated/models";
import {
  useAddShoppingItem,
  useDeleteShoppingItem,
  useShoppingList,
  useUpdateShoppingItem,
} from "@/api/queries";
import {
  EditItemModal,
  ProductSearchInput,
  ShoppingListItem,
} from "./components";

export function ShoppingPage(): React.ReactElement {
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
      <div className="flex items-center justify-between mb-6">
        <div>
          <h2 className="text-3xl font-bold text-stone-800">Список покупок</h2>
          <p className="text-stone-500 mt-1">Общий список для всей семьи</p>
        </div>
        <div className="text-right">
          <p className="text-2xl font-bold text-amber-600">
            {pendingItems.length}
          </p>
          <p className="text-sm text-stone-500">к покупке</p>
        </div>
      </div>

      <div className="bg-white rounded-xl p-4 shadow-sm border border-stone-200 mb-6">
        <ProductSearchInput
          onAddProduct={handleAddProduct}
          onAddCustom={handleAddCustom}
          isAdding={addMutation.isPending}
        />
      </div>

      <div className="bg-white rounded-xl shadow-sm border border-stone-200">
        <div className="p-4 border-b border-stone-100">
          <h3 className="font-semibold text-stone-800">К покупке</h3>
        </div>
        <div className="divide-y divide-stone-100">
          {isLoading ? (
            <div className="p-4 text-center text-stone-500">Загрузка...</div>
          ) : pendingItems.length === 0 ? (
            <p className="p-4 text-stone-500">
              Список пуст. Найдите товар выше, чтобы добавить.
            </p>
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

      <EditItemModal
        item={editingItem}
        isOpen={editingItem !== null}
        onClose={() => setEditingItem(null)}
        onSave={handleSave}
        isSaving={updateMutation.isPending}
      />
    </div>
  );
}
