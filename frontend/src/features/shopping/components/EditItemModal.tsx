import { useState } from "react";
import { useTranslation } from "react-i18next";
import type { ShoppingItemResponse } from "@/api/generated/models";
import { FormActions } from "@/components/FormActions";
import { Modal } from "@/components/Modal";

interface EditItemModalProps {
  item: ShoppingItemResponse;
  onClose: () => void;
  onSave: (id: string, amount: number, note: string) => void;
  isSaving?: boolean;
}

/**
 * Keyed on item.id by the parent, so each opened item mounts a fresh form and
 * the initial values come straight from props (no prop→state sync effect).
 */
export function EditItemModal({
  item,
  onClose,
  onSave,
  isSaving = false,
}: EditItemModalProps): React.ReactElement {
  const { t } = useTranslation();
  const [amount, setAmount] = useState(item.amount);
  const [note, setNote] = useState(item.note ?? "");

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (amount > 0) {
      onSave(item.id, amount, note);
    }
  };

  return (
    <Modal title={item.name} onClose={onClose}>
      <form onSubmit={handleSubmit}>
        <div className="space-y-4">
          <div>
            <label
              htmlFor="amount"
              className="block text-sm font-medium text-stone-700 mb-1"
            >
              {t("shopping.edit.amount")}
            </label>
            <input
              id="amount"
              type="number"
              min="1"
              value={amount}
              onChange={(e) => setAmount(Number(e.target.value))}
              className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
            />
          </div>

          <div>
            <label
              htmlFor="note"
              className="block text-sm font-medium text-stone-700 mb-1"
            >
              {t("shopping.edit.note")}
            </label>
            <input
              id="note"
              type="text"
              value={note}
              onChange={(e) => setNote(e.target.value)}
              placeholder={t("shopping.edit.notePlaceholder")}
              className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
            />
          </div>
        </div>

        <FormActions
          onCancel={onClose}
          isSubmitting={isSaving}
          submitLabel={t("shopping.edit.save")}
          submittingLabel={t("shopping.edit.saving")}
          submitDisabled={amount < 1}
        />
      </form>
    </Modal>
  );
}
