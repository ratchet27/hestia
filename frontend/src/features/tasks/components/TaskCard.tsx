import { useTranslation } from "react-i18next";
import type { TaskResponse } from "../../../api/generated/models";
import { Icons } from "../../../components/Icons";

interface TaskCardProps {
  task: TaskResponse;
  onToggleDone: (id: string) => void;
  onClick: (task: TaskResponse) => void;
}

const priorityStyles = {
  low: "bg-green-100 text-green-700",
  medium: "bg-amber-100 text-amber-700",
  high: "bg-red-100 text-red-700",
} as const;

export function TaskCard({
  task,
  onToggleDone,
  onClick,
}: TaskCardProps): React.ReactElement {
  const { t } = useTranslation();

  const formattedDueDate = task.due_date
    ? new Date(task.due_date).toLocaleDateString("ru-RU", {
        day: "numeric",
        month: "short",
      })
    : null;

  return (
    // biome-ignore lint/a11y/useSemanticElements: card wraps an action button; a real <button> would nest interactive elements
    <div
      role="button"
      tabIndex={0}
      className="bg-white rounded-xl p-4 shadow-sm border border-stone-200 hover:border-amber-400 transition-colors cursor-pointer w-full text-left"
      onClick={() => onClick(task)}
      onKeyDown={(e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          onClick(task);
        }
      }}
    >
      <div className="flex items-center gap-3">
        <button
          type="button"
          onClick={(e) => {
            e.stopPropagation();
            onToggleDone(task.id);
          }}
          className={`w-6 h-6 rounded-full flex items-center justify-center shrink-0 ${
            task.done
              ? "bg-green-500 text-white"
              : "border-2 border-stone-300 hover:border-green-500 transition-colors"
          }`}
        >
          {task.done && <Icons.Check />}
        </button>
        <div className="flex-1 min-w-0">
          <p
            className={`font-medium ${task.done ? "line-through text-stone-400" : "text-stone-800"}`}
          >
            {task.name}
          </p>
          <div className="flex items-center gap-2 mt-1">
            {formattedDueDate && (
              <span className="text-sm text-stone-500">{formattedDueDate}</span>
            )}
            <span
              className={`px-2 py-0.5 rounded-full text-xs font-medium ${priorityStyles[task.priority] ?? priorityStyles.medium}`}
            >
              {t(`tasks.items.priority.${task.priority}`)}
            </span>
          </div>
        </div>
      </div>
    </div>
  );
}
