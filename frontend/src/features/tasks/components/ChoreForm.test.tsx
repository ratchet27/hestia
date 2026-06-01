import { describe, expect, it, vi } from "vitest";
import { createChoreResponse } from "@/test/mocks/data";
import { render, screen, waitFor } from "@/test/utils";
import { ChoreForm } from "./ChoreForm";

describe("ChoreForm", () => {
  const defaultProps = {
    onSubmit: vi.fn(),
    onCancel: vi.fn(),
    isSubmitting: false,
  };

  it("renders empty form for create mode", () => {
    render(<ChoreForm {...defaultProps} />);
    expect(screen.getByLabelText("Название")).toHaveValue("");
  });

  it("renders prefilled form for edit mode", () => {
    const chore = createChoreResponse({ name: "Clean house" });
    render(<ChoreForm {...defaultProps} chore={chore} />);
    expect(screen.getByLabelText("Название")).toHaveValue("Clean house");
  });

  it("validates required name field", async () => {
    const { user } = render(<ChoreForm {...defaultProps} />);
    const nameInput = screen.getByLabelText("Название");
    await user.clear(nameInput);
    await user.click(screen.getByText("Создать"));
    expect(screen.getByText("Название обязательно")).toBeInTheDocument();
  });

  it("shows weekday dropdown when fixed_weekly selected", async () => {
    const { user } = render(<ChoreForm {...defaultProps} />);
    const scheduleSelect = screen.getByLabelText("Тип расписания");
    await user.selectOptions(scheduleSelect, "fixed_weekly");

    await waitFor(() => {
      expect(screen.getByText("понедельник")).toBeInTheDocument();
    });
  });

  it("shows number input for interval type", () => {
    render(<ChoreForm {...defaultProps} />);
    // Default is interval, so should have number input
    const valueInput = screen.getByLabelText("Значение");
    expect(valueInput).toHaveAttribute("type", "number");
  });

  it("submits form with valid data", async () => {
    const onSubmit = vi.fn();
    const { user } = render(
      <ChoreForm {...defaultProps} onSubmit={onSubmit} />,
    );

    const nameInput = screen.getByLabelText("Название");
    await user.clear(nameInput);
    await user.type(nameInput, "Vacuum");
    await user.click(screen.getByText("Создать"));

    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalledWith(
        expect.objectContaining({ name: "Vacuum" }),
      );
    });
  });

  it("shows delete button only in edit mode", () => {
    const chore = createChoreResponse();
    const onDelete = vi.fn();
    render(<ChoreForm {...defaultProps} chore={chore} onDelete={onDelete} />);
    expect(screen.getByText("Удалить")).toBeInTheDocument();
  });
});
