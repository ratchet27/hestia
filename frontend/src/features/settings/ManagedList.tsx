import { useState } from "react";
import { useTranslation } from "react-i18next";

export interface ManagedItem {
  id: string;
  name: string;
  usage_count: number;
}

interface ManagedListProps {
  title: string;
  items: ManagedItem[];
  onAdd: (name: string) => Promise<unknown>;
  onRename: (id: string, name: string) => Promise<unknown>;
  onDelete: (id: string) => Promise<unknown>;
}

export function ManagedList({
  title,
  items,
  onAdd,
  onRename,
  onDelete,
}: ManagedListProps): React.ReactElement {
  const { t } = useTranslation();
  const [newName, setNewName] = useState("");
  const [editingId, setEditingId] = useState<string | null>(null);
  const [editName, setEditName] = useState("");

  const submitAdd = async () => {
    const name = newName.trim();
    if (!name) return;
    await onAdd(name);
    setNewName("");
  };

  const submitRename = async (id: string) => {
    const name = editName.trim();
    if (name) await onRename(id, name);
    setEditingId(null);
  };

  return (
    <div className="bg-white rounded-xl p-6 shadow-sm border border-stone-200">
      <h3 className="font-semibold text-stone-800 mb-4">{title}</h3>
      <div className="space-y-2">
        {items.map((item) => (
          <div
            key={item.id}
            className="flex items-center justify-between py-2 border-b border-stone-100 last:border-0"
          >
            {editingId === item.id ? (
              <input
                // biome-ignore lint/a11y/noAutofocus: inline rename field needs focus to work
                autoFocus
                value={editName}
                onChange={(e) => setEditName(e.target.value)}
                onBlur={() => submitRename(item.id)}
                onKeyDown={(e) => e.key === "Enter" && submitRename(item.id)}
                className="px-2 py-1 border border-stone-300 rounded"
              />
            ) : (
              <button
                type="button"
                onClick={() => {
                  setEditingId(item.id);
                  setEditName(item.name);
                }}
                className="text-stone-800 hover:underline"
              >
                {item.name}
              </button>
            )}
            <div className="flex items-center gap-3">
              {item.usage_count > 0 && (
                <span className="text-xs text-stone-400">
                  {t("settings.usageCount", { n: item.usage_count })}
                </span>
              )}
              <button
                type="button"
                aria-label={t("settings.deleteAria", { name: item.name })}
                title={
                  item.usage_count > 0
                    ? t("settings.deleteDisabledReason", {
                        n: item.usage_count,
                      })
                    : undefined
                }
                disabled={item.usage_count > 0}
                onClick={() => {
                  if (
                    window.confirm(
                      t("settings.deleteConfirm", { name: item.name }),
                    )
                  )
                    onDelete(item.id);
                }}
                className="text-sm text-stone-500 hover:text-red-500 disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:text-stone-500"
              >
                {t("settings.delete")}
              </button>
            </div>
          </div>
        ))}
      </div>
      <div className="mt-4 flex gap-2">
        <input
          value={newName}
          onChange={(e) => setNewName(e.target.value)}
          onKeyDown={(e) => e.key === "Enter" && submitAdd()}
          placeholder={t("settings.addPlaceholder")}
          className="flex-1 px-3 py-2 border border-stone-300 rounded-lg text-sm"
        />
        <button
          type="button"
          onClick={submitAdd}
          className="px-4 py-2 text-sm text-amber-600 hover:underline"
        >
          {t("settings.add")}
        </button>
      </div>
    </div>
  );
}
