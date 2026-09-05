import { useForm, useWatch } from "react-hook-form";
import { useTranslation } from "react-i18next";
import type { ChoreResponse } from "../../../api/generated/models";
import { FormActions } from "../../../components/FormActions";

interface ChoreFormProps {
  chore?: ChoreResponse;
  onSubmit: (data: ChoreFormValues) => Promise<void>;
  onCancel: () => void;
  onDelete?: () => Promise<void>;
  isSubmitting: boolean;
  isDeleting?: boolean;
}

export interface ChoreFormValues {
  name: string;
  schedule_type: string;
  schedule_value: string;
  assignee: string;
}

export function ChoreForm({
  chore,
  onSubmit,
  onCancel,
  onDelete,
  isSubmitting,
  isDeleting,
}: ChoreFormProps): React.ReactElement {
  const { t } = useTranslation();

  const {
    register,
    handleSubmit,
    control,
    formState: { errors },
  } = useForm<ChoreFormValues>({
    defaultValues: {
      name: chore?.name ?? "",
      schedule_type: chore?.schedule_type ?? "interval",
      schedule_value: chore?.schedule_value?.toString() ?? "7",
      assignee: chore?.assignee ?? "",
    },
  });

  const scheduleType = useWatch({ control, name: "schedule_type" });

  const onFormSubmit = async (values: ChoreFormValues): Promise<void> => {
    await onSubmit(values);
  };

  return (
    <form onSubmit={handleSubmit(onFormSubmit)} className="space-y-4">
      <div>
        <label
          htmlFor="chore-name"
          className="block text-sm font-medium text-stone-700 mb-1"
        >
          {t("tasks.chores.form.name")}
        </label>
        <input
          id="chore-name"
          type="text"
          placeholder={t("tasks.chores.form.namePlaceholder")}
          {...register("name", {
            required: t("tasks.chores.form.nameRequired"),
          })}
          className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
        />
        {errors.name && (
          <p className="mt-1 text-sm text-red-600">{errors.name.message}</p>
        )}
      </div>

      <div>
        <label
          htmlFor="chore-schedule-type"
          className="block text-sm font-medium text-stone-700 mb-1"
        >
          {t("tasks.chores.form.scheduleType")}
        </label>
        <select
          id="chore-schedule-type"
          {...register("schedule_type", {
            required: t("tasks.chores.form.scheduleTypeRequired"),
          })}
          className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
        >
          <option value="interval">{t("tasks.chores.form.interval")}</option>
          <option value="fixed_weekly">
            {t("tasks.chores.form.fixedWeekly")}
          </option>
          <option value="fixed_monthly">
            {t("tasks.chores.form.fixedMonthly")}
          </option>
        </select>
      </div>

      <div>
        <label
          htmlFor="chore-schedule-value"
          className="block text-sm font-medium text-stone-700 mb-1"
        >
          {t("tasks.chores.form.scheduleValue")}
        </label>
        {scheduleType === "fixed_weekly" ? (
          <select
            id="chore-schedule-value"
            {...register("schedule_value", {
              required: t("tasks.chores.form.scheduleValueRequired"),
            })}
            className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
          >
            {[1, 2, 3, 4, 5, 6, 7].map((day) => (
              <option key={day} value={day}>
                {t(`tasks.weekdays.${day}`)}
              </option>
            ))}
          </select>
        ) : (
          <input
            id="chore-schedule-value"
            type="number"
            min={1}
            max={scheduleType === "fixed_monthly" ? 28 : 365}
            placeholder={
              scheduleType === "fixed_monthly"
                ? t("tasks.chores.form.dayOfMonth")
                : t("tasks.chores.form.days")
            }
            {...register("schedule_value", {
              required: t("tasks.chores.form.scheduleValueRequired"),
              min: {
                value: 1,
                message: t("tasks.chores.form.scheduleValueRequired"),
              },
            })}
            className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
          />
        )}
        {errors.schedule_value && (
          <p className="mt-1 text-sm text-red-600">
            {errors.schedule_value.message}
          </p>
        )}
      </div>

      <div>
        <label
          htmlFor="chore-assignee"
          className="block text-sm font-medium text-stone-700 mb-1"
        >
          {t("tasks.chores.form.assignee")}
        </label>
        <input
          id="chore-assignee"
          type="text"
          placeholder={t("tasks.chores.form.assigneePlaceholder")}
          {...register("assignee")}
          className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
        />
      </div>

      <FormActions
        onCancel={onCancel}
        isSubmitting={isSubmitting}
        submitLabel={chore ? t("tasks.form.save") : t("tasks.form.create")}
        submittingLabel={
          chore ? t("tasks.form.saving") : t("tasks.form.creating")
        }
        onDelete={chore && onDelete ? onDelete : undefined}
        isDeleting={isDeleting}
        deleteLabel={t("tasks.form.delete")}
        deletingLabel={t("tasks.form.deleting")}
      />
    </form>
  );
}
