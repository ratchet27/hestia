import { useState } from "react";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import type { TaskResponse } from "../../../api/generated/models";

interface TaskFormProps {
  task?: TaskResponse;
  onSubmit: (data: TaskFormValues) => Promise<void>;
  onCancel: () => void;
  onDelete?: () => Promise<void>;
  isSubmitting: boolean;
  isDeleting?: boolean;
}

export interface TaskFormValues {
  name: string;
  due_date: string;
  priority: string;
}

export function TaskForm({
  task,
  onSubmit,
  onCancel,
  onDelete,
  isSubmitting,
  isDeleting,
}: TaskFormProps): React.ReactElement {
  const { t } = useTranslation();
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<TaskFormValues>({
    defaultValues: {
      name: task?.name ?? "",
      due_date: task?.due_date ? task.due_date.split("T")[0] : "",
      priority: task?.priority ?? "medium",
    },
  });

  const onFormSubmit = async (values: TaskFormValues): Promise<void> => {
    await onSubmit(values);
  };

  return (
    <form onSubmit={handleSubmit(onFormSubmit)} className="space-y-4">
      <div>
        <label
          htmlFor="task-name"
          className="block text-sm font-medium text-stone-700 mb-1"
        >
          {t("tasks.items.form.name")}
        </label>
        <input
          id="task-name"
          type="text"
          placeholder={t("tasks.items.form.namePlaceholder")}
          {...register("name", {
            required: t("tasks.items.form.nameRequired"),
          })}
          className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
        />
        {errors.name && (
          <p className="mt-1 text-sm text-red-600">{errors.name.message}</p>
        )}
      </div>

      <div>
        <label
          htmlFor="task-due-date"
          className="block text-sm font-medium text-stone-700 mb-1"
        >
          {t("tasks.items.form.dueDate")}
        </label>
        <input
          id="task-due-date"
          type="date"
          {...register("due_date")}
          className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
        />
      </div>

      <div>
        <label
          htmlFor="task-priority"
          className="block text-sm font-medium text-stone-700 mb-1"
        >
          {t("tasks.items.form.priority")}
        </label>
        <select
          id="task-priority"
          {...register("priority")}
          className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
        >
          <option value="low">{t("tasks.items.priority.low")}</option>
          <option value="medium">{t("tasks.items.priority.medium")}</option>
          <option value="high">{t("tasks.items.priority.high")}</option>
        </select>
      </div>

      <div className="flex gap-3 mt-6">
        <button
          type="button"
          onClick={onCancel}
          disabled={isSubmitting || isDeleting}
          className="flex-1 px-4 py-2 border border-stone-300 rounded-lg hover:bg-stone-50 transition-colors disabled:opacity-50"
        >
          {t("tasks.form.cancel")}
        </button>
        <button
          type="submit"
          disabled={isSubmitting || isDeleting}
          className="flex-1 px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors disabled:opacity-50"
        >
          {isSubmitting
            ? task
              ? t("tasks.form.saving")
              : t("tasks.form.creating")
            : task
              ? t("tasks.form.save")
              : t("tasks.form.create")}
        </button>
      </div>

      {task && onDelete && (
        <div className="border-t border-stone-200 pt-4 mt-4">
          {showDeleteConfirm ? (
            <div className="flex gap-3">
              <button
                type="button"
                onClick={() => setShowDeleteConfirm(false)}
                disabled={isDeleting}
                className="flex-1 px-4 py-2 border border-stone-300 rounded-lg hover:bg-stone-50 transition-colors disabled:opacity-50"
              >
                {t("tasks.form.cancel")}
              </button>
              <button
                type="button"
                onClick={onDelete}
                disabled={isDeleting}
                className="flex-1 px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors disabled:opacity-50"
              >
                {isDeleting ? t("tasks.form.deleting") : t("tasks.form.delete")}
              </button>
            </div>
          ) : (
            <button
              type="button"
              onClick={() => setShowDeleteConfirm(true)}
              className="w-full px-4 py-2 text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition-colors"
            >
              {t("tasks.form.delete")}
            </button>
          )}
        </div>
      )}
    </form>
  );
}
