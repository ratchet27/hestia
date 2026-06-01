import { HttpResponse, http } from "msw";
import { describe, expect, it } from "vitest";
import {
  createChoreResponse,
  createTaskResponse,
  wrapResponse,
} from "@/test/mocks/data";
import { server } from "@/test/mocks/server";
import { render, screen, waitFor } from "@/test/utils";
import { TasksPage } from "./TasksPage";

describe("TasksPage", () => {
  it("renders page title", async () => {
    render(<TasksPage />);
    await waitFor(() => {
      expect(screen.getByText("Задачи и дела")).toBeInTheDocument();
    });
  });

  it("renders loading state initially", () => {
    render(<TasksPage />);
    expect(screen.getByText("Загрузка...")).toBeInTheDocument();
  });

  it("renders error state when API fails", async () => {
    server.use(
      http.get("*/api/internal/v1/tasks", () =>
        HttpResponse.json({ message: "Server error" }, { status: 500 }),
      ),
    );

    render(<TasksPage />);
    await waitFor(() => {
      expect(
        screen.getByText(
          "Не удалось загрузить данные. Проверьте подключение к серверу.",
        ),
      ).toBeInTheDocument();
    });
  });

  it("renders tasks from API", async () => {
    const task = createTaskResponse({ name: "Купить молоко" });
    server.use(
      http.get("*/api/internal/v1/tasks", ({ request }) => {
        const url = new URL(request.url);
        const status = url.searchParams.get("status");
        if (status === "completed") return HttpResponse.json(wrapResponse([]));
        return HttpResponse.json(wrapResponse([task]));
      }),
    );

    render(<TasksPage />);
    await waitFor(() => {
      expect(screen.getByText("Купить молоко")).toBeInTheDocument();
    });
  });

  it("renders chores from API", async () => {
    const chore = createChoreResponse({ name: "Пылесосить" });
    server.use(
      http.get("*/api/internal/v1/chores", () =>
        HttpResponse.json(wrapResponse([chore])),
      ),
    );

    render(<TasksPage />);
    await waitFor(() => {
      expect(screen.getByText("Пылесосить")).toBeInTheDocument();
    });
  });

  it("shows priority badge on tasks", async () => {
    const task = createTaskResponse({
      name: "Urgent task",
      priority: "high",
    });
    server.use(
      http.get("*/api/internal/v1/tasks", ({ request }) => {
        const url = new URL(request.url);
        if (url.searchParams.get("status") === "completed")
          return HttpResponse.json(wrapResponse([]));
        return HttpResponse.json(wrapResponse([task]));
      }),
    );

    render(<TasksPage />);
    await waitFor(() => {
      expect(screen.getByText("Высокий")).toBeInTheDocument();
    });
  });

  it("opens create task modal when add button clicked", async () => {
    const { user } = render(<TasksPage />);
    await waitFor(() => {
      expect(screen.queryByText("Загрузка...")).not.toBeInTheDocument();
    });

    const addButtons = screen.getAllByText(/\+ Добавить/);
    // Second add button is for tasks (first is chores)
    await user.click(addButtons[1]!);

    expect(screen.getByText("Новая задача")).toBeInTheDocument();
  });

  it("opens create chore modal when add button clicked", async () => {
    const { user } = render(<TasksPage />);
    await waitFor(() => {
      expect(screen.queryByText("Загрузка...")).not.toBeInTheDocument();
    });

    const addButtons = screen.getAllByText(/\+ Добавить/);
    // First add button is for chores
    await user.click(addButtons[0]!);

    expect(screen.getByText("Новое дело")).toBeInTheDocument();
  });

  it("renders overdue chores under 'Просрочено' section heading", async () => {
    const yesterday = new Date();
    yesterday.setUTCDate(yesterday.getUTCDate() - 1);
    yesterday.setUTCHours(0, 0, 0, 0);

    const tomorrow = new Date();
    tomorrow.setUTCDate(tomorrow.getUTCDate() + 3);
    tomorrow.setUTCHours(0, 0, 0, 0);

    const overdueChore = createChoreResponse({
      name: "Просроченное дело",
      next_due_at: yesterday.toISOString(),
    });
    const upcomingChore = createChoreResponse({
      name: "Предстоящее дело",
      next_due_at: tomorrow.toISOString(),
    });

    server.use(
      http.get("*/api/internal/v1/chores", () =>
        HttpResponse.json(wrapResponse([overdueChore, upcomingChore])),
      ),
    );

    render(<TasksPage />);
    await waitFor(() => {
      expect(screen.getByText("Просрочено")).toBeInTheDocument();
    });
    expect(screen.getByText("Просроченное дело")).toBeInTheDocument();
    expect(screen.getByText("Предстоящее дело")).toBeInTheDocument();
  });
});
