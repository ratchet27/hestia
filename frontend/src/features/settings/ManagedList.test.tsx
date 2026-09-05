import { describe, expect, it, vi } from "vitest";
import { render, screen, userEvent } from "@/test/utils";
import { ManagedList } from "./ManagedList";

const items = [
  { id: "a", name: "Холодильник", usage_count: 0 },
  { id: "b", name: "Кладовая", usage_count: 4 },
];

function setup(overrides = {}) {
  const props = {
    title: "Места хранения",
    items,
    onAdd: vi.fn().mockResolvedValue(undefined),
    onRename: vi.fn().mockResolvedValue(undefined),
    onDelete: vi.fn().mockResolvedValue(undefined),
    ...overrides,
  };
  render(<ManagedList {...props} />);
  return props;
}

describe("ManagedList", () => {
  it("disables delete for in-use items and enables it for empty ones", () => {
    setup();
    expect(
      screen.getByRole("button", { name: /удалить «Холодильник»/i }),
    ).toBeEnabled();
    expect(
      screen.getByRole("button", { name: /удалить «Кладовая»/i }),
    ).toBeDisabled();
  });

  it("shows the usage count for in-use items", () => {
    setup();
    expect(screen.getByText(/используется: 4/i)).toBeInTheDocument();
  });

  it("calls onAdd with the typed name", async () => {
    const props = setup();
    const user = userEvent.setup();
    await user.type(screen.getByPlaceholderText(/добавить/i), "Балкон");
    await user.click(screen.getByRole("button", { name: /^добавить$/i }));
    expect(props.onAdd).toHaveBeenCalledWith("Балкон");
  });

  it("calls onDelete for an empty item", async () => {
    vi.spyOn(window, "confirm").mockReturnValue(true);
    const props = setup();
    const user = userEvent.setup();
    await user.click(
      screen.getByRole("button", { name: /удалить «Холодильник»/i }),
    );
    expect(props.onDelete).toHaveBeenCalledWith("a");
  });

  it("submits a rename once when Enter is followed by blur", async () => {
    const props = setup();
    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: "Холодильник" }));
    const input = screen.getByDisplayValue("Холодильник");
    await user.clear(input);
    await user.type(input, "Морозилка{Enter}");
    // Enter unmounts the input, which fires its blur handler as well.
    expect(props.onRename).toHaveBeenCalledTimes(1);
    expect(props.onRename).toHaveBeenCalledWith("a", "Морозилка");
  });

  it("does not PATCH when the name is unchanged", async () => {
    const props = setup();
    const user = userEvent.setup();
    await user.click(screen.getByRole("button", { name: "Холодильник" }));
    await user.keyboard("{Enter}");
    expect(props.onRename).not.toHaveBeenCalled();
    expect(
      screen.getByRole("button", { name: "Холодильник" }),
    ).toBeInTheDocument();
  });

  it("does not call onDelete when confirm is cancelled", async () => {
    vi.spyOn(window, "confirm").mockReturnValue(false);
    const props = setup();
    const user = userEvent.setup();
    await user.click(
      screen.getByRole("button", { name: /удалить «Холодильник»/i }),
    );
    expect(props.onDelete).not.toHaveBeenCalled();
  });
});
