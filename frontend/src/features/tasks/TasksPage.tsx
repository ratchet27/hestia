import { type ReactElement, useState } from "react";
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
import { Modal } from "../../components/Modal";
import { PageHeader } from "../../components/PageHeader";
import { ChoreCard } from "./components/ChoreCard";
import { ChoreForm, type ChoreFormValues } from "./components/ChoreForm";
import { TaskCard } from "./components/TaskCard";
import { TaskForm, type TaskFormValues } from "./components/TaskForm";
import { groupChores, groupTasks } from "./grouping";

export function TasksPage(): ReactElement {
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
      priority: data.priority,
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
        priority: data.priority,
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
      schedule_type: data.schedule_type,
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
        schedule_type: data.schedule_type,
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
  const choreGroups = groupChores(chores);
  const taskGroups = groupTasks(activeTasks, completedTasks);

  // The header is rendered once; only the body below it changes with state.
  let body: ReactElement;
  if (isLoading) {
    body = <div className="text-stone-500">{t("common.loading")}</div>;
  } else if (isError) {
    body = (
      <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
        {t("tasks.errors.loadFailed")}
      </div>
    );
  } else {
    body = (
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
          <div className="space-y-4">
            {choreGroups.overdue.length > 0 && (
              <div className="space-y-3">
                <h4 className="text-sm font-medium text-red-600">
                  {t("tasks.chores.sections.overdue")}
                </h4>
                {choreGroups.overdue.map((chore) => (
                  <ChoreCard
                    key={chore.id}
                    chore={chore}
                    onMarkDone={handleMarkChoreDone}
                    onClick={setEditingChore}
                  />
                ))}
              </div>
            )}
            {choreGroups.upcoming.length > 0 && (
              <div className="space-y-3">
                {choreGroups.overdue.length > 0 && (
                  <h4 className="text-sm font-medium text-stone-500">
                    {t("tasks.chores.sections.upcoming")}
                  </h4>
                )}
                {choreGroups.upcoming.map((chore) => (
                  <ChoreCard
                    key={chore.id}
                    chore={chore}
                    onMarkDone={handleMarkChoreDone}
                    onClick={setEditingChore}
                  />
                ))}
              </div>
            )}
            {choreGroups.overdue.length === 0 &&
              choreGroups.upcoming.length === 0 && (
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

          <div className="space-y-4">
            {taskGroups.overdue.length > 0 && (
              <div className="space-y-3">
                <h4 className="text-sm font-medium text-red-600">
                  {t("tasks.items.sections.overdue")}
                </h4>
                {taskGroups.overdue.map((task) => (
                  <TaskCard
                    key={task.id}
                    task={task}
                    onToggleDone={handleToggleTaskDone}
                    onClick={setEditingTask}
                  />
                ))}
              </div>
            )}
            {taskGroups.active.length > 0 && (
              <div className="space-y-3">
                {taskGroups.overdue.length > 0 && (
                  <h4 className="text-sm font-medium text-stone-500">
                    {t("tasks.items.sections.active")}
                  </h4>
                )}
                {taskGroups.active.map((task) => (
                  <TaskCard
                    key={task.id}
                    task={task}
                    onToggleDone={handleToggleTaskDone}
                    onClick={setEditingTask}
                  />
                ))}
              </div>
            )}
            {taskGroups.overdue.length === 0 &&
              taskGroups.active.length === 0 && (
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
    );
  }

  return (
    <div className="p-8">
      <PageHeader title={t("tasks.title")} subtitle={t("tasks.subtitle")} />

      {body}

      {showTaskForm && (
        <Modal
          title={t("tasks.items.form.createTitle")}
          onClose={() => setShowTaskForm(false)}
        >
          <TaskForm
            onSubmit={handleCreateTask}
            onCancel={() => setShowTaskForm(false)}
            isSubmitting={createTask.isPending}
          />
        </Modal>
      )}

      {editingTask && (
        <Modal
          title={t("tasks.items.form.editTitle")}
          onClose={() => setEditingTask(null)}
        >
          <TaskForm
            task={editingTask}
            onSubmit={handleUpdateTask}
            onCancel={() => setEditingTask(null)}
            onDelete={handleDeleteTask}
            isSubmitting={updateTask.isPending}
            isDeleting={deleteTask.isPending}
          />
        </Modal>
      )}

      {showChoreForm && (
        <Modal
          title={t("tasks.chores.form.createTitle")}
          onClose={() => setShowChoreForm(false)}
        >
          <ChoreForm
            onSubmit={handleCreateChore}
            onCancel={() => setShowChoreForm(false)}
            isSubmitting={createChore.isPending}
          />
        </Modal>
      )}

      {editingChore && (
        <Modal
          title={t("tasks.chores.form.editTitle")}
          onClose={() => setEditingChore(null)}
        >
          <ChoreForm
            chore={editingChore}
            onSubmit={handleUpdateChore}
            onCancel={() => setEditingChore(null)}
            onDelete={handleDeleteChore}
            isSubmitting={updateChore.isPending}
            isDeleting={deleteChore.isPending}
          />
        </Modal>
      )}
    </div>
  );
}
