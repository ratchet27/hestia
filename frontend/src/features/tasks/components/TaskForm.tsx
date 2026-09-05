import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import type { TaskResponse } from "../../../api/generated/models";
import { FormActions } from "../../../components/FormActions";

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

      <FormActions
        onCancel={onCancel}
        isSubmitting={isSubmitting}
        submitLabel={task ? t("tasks.form.save") : t("tasks.form.create")}
        submittingLabel={
          task ? t("tasks.form.saving") : t("tasks.form.creating")
        }
        onDelete={task && onDelete ? onDelete : undefined}
        isDeleting={isDeleting}
        deleteLabel={t("tasks.form.delete")}
        deletingLabel={t("tasks.form.deleting")}
      />
    </form>
  );
}
