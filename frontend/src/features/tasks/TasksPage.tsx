import { useState } from "react";
import toast from "react-hot-toast";
import { useTranslation } from "react-i18next";
import type { ChoreResponse, TaskResponse } from "../../api/generated/models";
import {
  useChores,
  useCreateChore,
  useDeleteChore,
  useMarkChoreDone,
  useUpdateChore,
} from "../../api/queries/chores";
import {
  useCreateTask,
  useDeleteTask,
  useTasks,
  useToggleTaskDone,
  useUpdateTask,
} from "../../api/queries/tasks";
import { ChoreCard } from "./components/ChoreCard";
import { ChoreForm, type ChoreFormValues } from "./components/ChoreForm";
import { TaskCard } from "./components/TaskCard";
import { TaskForm, type TaskFormValues } from "./components/TaskForm";

export function TasksPage(): React.ReactElement {
  const { t } = useTranslation();

  const {
    data: activeTasks = [],
    isLoading: tasksLoading,
    isError: tasksError,
  } = useTasks("active");
  const { data: completedTasks = [] } = useTasks("completed");
  const {
    data: chores = [],
    isLoading: choresLoading,
    isError: choresError,
  } = useChores();

  const createTask = useCreateTask();
  const updateTask = useUpdateTask();
  const deleteTask = useDeleteTask();
  const toggleTaskDone = useToggleTaskDone();
  const createChore = useCreateChore();
  const updateChore = useUpdateChore();
  const deleteChore = useDeleteChore();
  const markChoreDone = useMarkChoreDone();

  const [showTaskForm, setShowTaskForm] = useState(false);
  const [editingTask, setEditingTask] = useState<TaskResponse | null>(null);
  const [showChoreForm, setShowChoreForm] = useState(false);
  const [editingChore, setEditingChore] = useState<ChoreResponse | null>(null);

  const handleCreateTask = async (data: TaskFormValues): Promise<void> => {
    await createTask.mutateAsync({
      name: data.name,
      due_date: data.due_date || undefined,
      priority: data.priority as "low" | "medium" | "high",
    });
    toast.success(t("tasks.items.form.created"));
    setShowTaskForm(false);
  };

  const handleUpdateTask = async (data: TaskFormValues): Promise<void> => {
    if (!editingTask) return;
    await updateTask.mutateAsync({
      id: editingTask.id,
      data: {
        name: data.name,
        due_date: data.due_date || undefined,
        priority: data.priority as "low" | "medium" | "high",
      },
    });
    toast.success(t("tasks.items.form.updated"));
    setEditingTask(null);
  };

  const handleDeleteTask = async (): Promise<void> => {
    if (!editingTask) return;
    await deleteTask.mutateAsync(editingTask.id);
    toast.success(t("tasks.items.form.deleted"));
    setEditingTask(null);
  };

  const handleToggleTaskDone = async (id: string): Promise<void> => {
    await toggleTaskDone.mutateAsync(id);
    toast.success(t("tasks.items.form.toggled"));
  };

  const handleCreateChore = async (data: ChoreFormValues): Promise<void> => {
    await createChore.mutateAsync({
      name: data.name,
      schedule_type: data.schedule_type as
        | "interval"
        | "fixed_weekly"
        | "fixed_monthly",
      schedule_value: Number.parseInt(data.schedule_value, 10),
      assignee: data.assignee || undefined,
    });
    toast.success(t("tasks.chores.form.created"));
    setShowChoreForm(false);
  };

  const handleUpdateChore = async (data: ChoreFormValues): Promise<void> => {
    if (!editingChore) return;
    await updateChore.mutateAsync({
      id: editingChore.id,
      data: {
        name: data.name,
        schedule_type: data.schedule_type as
          | "interval"
          | "fixed_weekly"
          | "fixed_monthly",
        schedule_value: Number.parseInt(data.schedule_value, 10),
        assignee: data.assignee || undefined,
      },
    });
    toast.success(t("tasks.chores.form.updated"));
    setEditingChore(null);
  };

  const handleDeleteChore = async (): Promise<void> => {
    if (!editingChore) return;
    await deleteChore.mutateAsync(editingChore.id);
    toast.success(t("tasks.chores.form.deleted"));
    setEditingChore(null);
  };

  const handleMarkChoreDone = async (id: string): Promise<void> => {
    await markChoreDone.mutateAsync(id);
    toast.success(t("tasks.chores.form.markedDone"));
  };

  const isLoading = tasksLoading || choresLoading;
  const isError = tasksError || choresError;

  if (isLoading) {
    return (
      <div className="p-8">
        <div className="mb-6">
          <h2 className="text-3xl font-bold text-stone-800">
            {t("tasks.title")}
          </h2>
          <p className="text-stone-500 mt-1">{t("tasks.subtitle")}</p>
        </div>
        <div className="text-stone-500">{t("common.loading")}</div>
      </div>
    );
  }

  if (isError) {
    return (
      <div className="p-8">
        <div className="mb-6">
          <h2 className="text-3xl font-bold text-stone-800">
            {t("tasks.title")}
          </h2>
          <p className="text-stone-500 mt-1">{t("tasks.subtitle")}</p>
        </div>
        <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
          {t("tasks.errors.loadFailed")}
        </div>
      </div>
    );
  }

  return (
    <div className="p-8">
      <div className="mb-6">
        <h2 className="text-3xl font-bold text-stone-800">
          {t("tasks.title")}
        </h2>
        <p className="text-stone-500 mt-1">{t("tasks.subtitle")}</p>
      </div>

      <div className="grid grid-cols-2 gap-6">
        <div>
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-xl font-semibold text-stone-800">
              {t("tasks.chores.title")}
            </h3>
            <button
              type="button"
              onClick={() => setShowChoreForm(true)}
              className="text-sm text-amber-600 hover:underline"
            >
              + {t("tasks.chores.add")}
            </button>
          </div>
          <div className="space-y-3">
            {chores.map((chore) => (
              <ChoreCard
                key={chore.id}
                chore={chore}
                onMarkDone={handleMarkChoreDone}
                onClick={setEditingChore}
              />
            ))}
            {chores.length === 0 && (
              <p className="text-stone-500 text-sm">{t("common.noItems")}</p>
            )}
          </div>
        </div>

        <div>
          <div className="flex items-center justify-between mb-4">
            <h3 className="text-xl font-semibold text-stone-800">
              {t("tasks.items.title")}
            </h3>
            <button
              type="button"
              onClick={() => setShowTaskForm(true)}
              className="text-sm text-amber-600 hover:underline"
            >
              + {t("tasks.items.add")}
            </button>
          </div>

          <div className="space-y-3">
            {activeTasks.map((task) => (
              <TaskCard
                key={task.id}
                task={task}
                onToggleDone={handleToggleTaskDone}
                onClick={setEditingTask}
              />
            ))}
            {activeTasks.length === 0 && (
              <p className="text-stone-500 text-sm">{t("common.noItems")}</p>
            )}
          </div>

          {completedTasks.length > 0 && (
            <div className="mt-6">
              <h4 className="text-sm font-medium text-stone-500 mb-3">
                {t("tasks.items.completed")}
              </h4>
              <div className="space-y-2 opacity-60">
                {completedTasks.map((task) => (
                  <TaskCard
                    key={task.id}
                    task={task}
                    onToggleDone={handleToggleTaskDone}
                    onClick={setEditingTask}
                  />
                ))}
              </div>
            </div>
          )}
        </div>
      </div>

      {showTaskForm && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
            <h3 className="text-xl font-bold text-stone-800 mb-4">
              {t("tasks.items.form.createTitle")}
            </h3>
            <TaskForm
              onSubmit={handleCreateTask}
              onCancel={() => setShowTaskForm(false)}
              isSubmitting={createTask.isPending}
            />
          </div>
        </div>
      )}

      {editingTask && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
            <h3 className="text-xl font-bold text-stone-800 mb-4">
              {t("tasks.items.form.editTitle")}
            </h3>
            <TaskForm
              task={editingTask}
              onSubmit={handleUpdateTask}
              onCancel={() => setEditingTask(null)}
              onDelete={handleDeleteTask}
              isSubmitting={updateTask.isPending}
              isDeleting={deleteTask.isPending}
            />
          </div>
        </div>
      )}

      {showChoreForm && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
            <h3 className="text-xl font-bold text-stone-800 mb-4">
              {t("tasks.chores.form.createTitle")}
            </h3>
            <ChoreForm
              onSubmit={handleCreateChore}
              onCancel={() => setShowChoreForm(false)}
              isSubmitting={createChore.isPending}
            />
          </div>
        </div>
      )}

      {editingChore && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
            <h3 className="text-xl font-bold text-stone-800 mb-4">
              {t("tasks.chores.form.editTitle")}
            </h3>
            <ChoreForm
              chore={editingChore}
              onSubmit={handleUpdateChore}
              onCancel={() => setEditingChore(null)}
              onDelete={handleDeleteChore}
              isSubmitting={updateChore.isPending}
              isDeleting={deleteChore.isPending}
            />
          </div>
        </div>
      )}
    </div>
  );
}
