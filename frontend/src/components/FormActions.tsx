import { type ReactElement, useState } from "react";
import { useTranslation } from "react-i18next";

interface FormActionsProps {
  onCancel: () => void;
  isSubmitting: boolean;
  submitLabel: string;
  submittingLabel: string;
  /** When set, a two-step "Delete → confirm" block renders below the main row. */
  onDelete?: () => void;
  isDeleting?: boolean;
  deleteLabel?: string;
  deletingLabel?: string;
  submitDisabled?: boolean;
}

/**
 * Cancel / submit row (plus the optional two-step delete) shared by every form
 * so keyboard and disabled behaviour cannot drift between features.
 */
export function FormActions({
  onCancel,
  isSubmitting,
  submitLabel,
  submittingLabel,
  onDelete,
  isDeleting = false,
  deleteLabel,
  deletingLabel,
  submitDisabled = false,
}: FormActionsProps): ReactElement {
  const { t } = useTranslation();
  const [confirmingDelete, setConfirmingDelete] = useState(false);
  const busy = isSubmitting || isDeleting;

  return (
    <>
      <div className="flex gap-3 mt-6">
        <button
          type="button"
          onClick={onCancel}
          disabled={busy}
          className="flex-1 px-4 py-2 border border-stone-300 rounded-lg hover:bg-stone-50 transition-colors disabled:opacity-50"
        >
          {t("common.cancel")}
        </button>
        <button
          type="submit"
          disabled={busy || submitDisabled}
          className="flex-1 px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors disabled:opacity-50"
        >
          {isSubmitting ? submittingLabel : submitLabel}
        </button>
      </div>

      {onDelete && (
        <div className="border-t border-stone-200 pt-4 mt-4">
          {confirmingDelete ? (
            <div className="flex gap-3">
              <button
                type="button"
                onClick={() => setConfirmingDelete(false)}
                disabled={isDeleting}
                className="flex-1 px-4 py-2 border border-stone-300 rounded-lg hover:bg-stone-50 transition-colors disabled:opacity-50"
              >
                {t("common.cancel")}
              </button>
              <button
                type="button"
                onClick={onDelete}
                disabled={isDeleting}
                className="flex-1 px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors disabled:opacity-50"
              >
                {isDeleting ? deletingLabel : deleteLabel}
              </button>
            </div>
          ) : (
            <button
              type="button"
              onClick={() => setConfirmingDelete(true)}
              className="w-full px-4 py-2 text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors"
            >
              {deleteLabel}
            </button>
          )}
        </div>
      )}
    </>
  );
}
