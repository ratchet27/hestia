import { describe, expect, it, vi } from "vitest";
import { createTaskResponse } from "@/test/mocks/data";
import { render, screen, waitFor } from "@/test/utils";
import { TaskForm } from "./TaskForm";

describe("TaskForm", () => {
  const defaultProps = {
    onSubmit: vi.fn(),
    onCancel: vi.fn(),
    isSubmitting: false,
  };

  it("renders empty form for create mode", () => {
    render(<TaskForm {...defaultProps} />);
    expect(screen.getByLabelText("Название")).toHaveValue("");
  });

  it("renders prefilled form for edit mode", () => {
    const task = createTaskResponse({ name: "Test task", priority: "high" });
    render(<TaskForm {...defaultProps} task={task} />);
    expect(screen.getByLabelText("Название")).toHaveValue("Test task");
  });

  it("validates required name field", async () => {
    const { user } = render(<TaskForm {...defaultProps} />);
    await user.click(screen.getByText("Создать"));
    expect(screen.getByText("Название обязательно")).toBeInTheDocument();
    expect(defaultProps.onSubmit).not.toHaveBeenCalled();
  });

  it("submits form with valid data", async () => {
    const onSubmit = vi.fn();
    const { user } = render(<TaskForm {...defaultProps} onSubmit={onSubmit} />);

    await user.type(screen.getByLabelText("Название"), "New task");
    await user.click(screen.getByText("Создать"));

    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalledWith(
        expect.objectContaining({ name: "New task" }),
      );
    });
  });

  it("shows delete button only in edit mode", () => {
    const task = createTaskResponse();
    const onDelete = vi.fn();
    render(<TaskForm {...defaultProps} task={task} onDelete={onDelete} />);
    expect(screen.getByText("Удалить")).toBeInTheDocument();
  });

  it("does not show delete button in create mode", () => {
    render(<TaskForm {...defaultProps} />);
    expect(screen.queryByText("Удалить")).not.toBeInTheDocument();
  });

  it("shows delete confirmation when delete clicked", async () => {
    const task = createTaskResponse();
    const onDelete = vi.fn();
    const { user } = render(
      <TaskForm {...defaultProps} task={task} onDelete={onDelete} />,
    );

    await user.click(screen.getByRole("button", { name: "Удалить" }));
    // First click only asks for confirmation.
    expect(onDelete).not.toHaveBeenCalled();
    expect(screen.getAllByRole("button", { name: "Отмена" })).toHaveLength(2);

    await user.click(screen.getByRole("button", { name: "Удалить" }));
    expect(onDelete).toHaveBeenCalledTimes(1);
  });

  it("disables buttons when submitting", () => {
    render(<TaskForm {...defaultProps} isSubmitting={true} />);
    expect(screen.getByText("Создание...")).toBeDisabled();
  });
});
