import { useTranslation } from "react-i18next";
import type { ChoreResponse } from "../../../api/generated/models";

interface ChoreCardProps {
  chore: ChoreResponse;
  onMarkDone: (id: string) => void;
  onClick: (chore: ChoreResponse) => void;
}

function getDaysUntil(dateStr: string): number {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const target = new Date(dateStr);
  target.setHours(0, 0, 0, 0);
  return Math.round(
    (target.getTime() - today.getTime()) / (1000 * 60 * 60 * 24),
  );
}

function getScheduleLabel(
  scheduleType: string,
  scheduleValue: number,
  t: (key: string, opts?: Record<string, unknown>) => string,
): string {
  switch (scheduleType) {
    case "interval":
      return t("tasks.chores.schedule.interval", { count: scheduleValue });
    case "fixed_weekly":
      return t("tasks.chores.schedule.fixedWeekly", {
        day: t(`tasks.weekdays.${scheduleValue}`),
      });
    case "fixed_monthly":
      return t("tasks.chores.schedule.fixedMonthly", { day: scheduleValue });
    default:
      return "";
  }
}

export function ChoreCard({
  chore,
  onMarkDone,
  onClick,
}: ChoreCardProps): React.ReactElement {
  const { t } = useTranslation();

  const days = getDaysUntil(chore.next_due_at);
  const isOverdue = days < 0;
  const isDueToday = days === 0;

  const dueLabel = isOverdue
    ? t("tasks.chores.overdue", { count: Math.abs(days) })
    : isDueToday
      ? t("tasks.chores.today")
      : t("tasks.chores.daysLeft", { count: days });

  const scheduleLabel = getScheduleLabel(
    chore.schedule_type,
    chore.schedule_value,
    t,
  );

  return (
    <button
      type="button"
      className={`bg-white rounded-xl p-4 shadow-sm border cursor-pointer hover:border-amber-400 transition-colors w-full text-left ${
        isOverdue
          ? "border-red-300 bg-red-50"
          : isDueToday
            ? "border-amber-300 bg-amber-50"
            : "border-stone-200"
      }`}
      onClick={() => onClick(chore)}
      onKeyDown={(e) => e.key === "Enter" && onClick(chore)}
    >
      <div className="flex items-center justify-between">
        <div className="min-w-0 flex-1">
          <p className="font-medium text-stone-800">{chore.name}</p>
          <p className="text-sm text-stone-500">
            {scheduleLabel} · {dueLabel}
          </p>
          {chore.assignee && (
            <p className="text-xs text-stone-400 mt-1">{chore.assignee}</p>
          )}
        </div>
        <button
          type="button"
          onClick={(e) => {
            e.stopPropagation();
            onMarkDone(chore.id);
          }}
          className="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors text-sm shrink-0 ml-3"
        >
          {t("tasks.chores.done")}
        </button>
      </div>
    </button>
  );
}
