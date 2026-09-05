import { render, screen } from "@testing-library/react";
import { userEvent } from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";
import { Modal } from "./Modal";

describe("Modal", () => {
  it("renders an accessible dialog labelled by its title", () => {
    render(
      <Modal title="Новый товар" onClose={() => {}}>
        <p>body</p>
      </Modal>,
    );
    expect(
      screen.getByRole("dialog", { name: "Новый товар" }),
    ).toBeInTheDocument();
  });

  it("closes on Escape from a focused field inside the dialog", async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();
    render(
      <Modal title="Edit" onClose={onClose}>
        <input aria-label="name" />
      </Modal>,
    );
    await user.click(screen.getByLabelText("name"));
    await user.keyboard("{Escape}");
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it("closes on backdrop click but not on clicks inside the panel", async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();
    render(
      <Modal title="Edit" onClose={onClose}>
        <button type="button">inside</button>
      </Modal>,
    );
    await user.click(screen.getByRole("button", { name: "inside" }));
    expect(onClose).not.toHaveBeenCalled();
    const backdrop = screen.getByRole("dialog").parentElement;
    if (!backdrop) throw new Error("backdrop missing");
    await user.click(backdrop);
    expect(onClose).toHaveBeenCalledTimes(1);
  });
});
