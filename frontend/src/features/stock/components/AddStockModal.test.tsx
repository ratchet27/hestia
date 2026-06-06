import { describe, expect, it, vi } from "vitest";
import { createLocation, createProductResponse } from "@/test/mocks/data";
import { render, screen, waitFor } from "@/test/utils";
import { AddStockModal } from "./AddStockModal";

describe("AddStockModal", () => {
  const locations = [
    createLocation({ id: "loc-1", name: "Холодильник" }),
    createLocation({ id: "loc-2", name: "Кладовка" }),
  ];

  const products = [
    createProductResponse({
      id: "prod-1",
      name: "Молоко",
      default_location: { id: "loc-1", name: "Холодильник" },
    }),
    createProductResponse({
      id: "prod-2",
      name: "Крупа",
      default_location: { id: "loc-2", name: "Кладовка" },
    }),
  ];

  it("renders form with product and location selects", () => {
    render(
      <AddStockModal
        products={products}
        locations={locations}
        onSubmit={vi.fn()}
        onClose={vi.fn()}
      />,
    );

    expect(screen.getByLabelText(/Товар/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/Место/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/Количество/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/Годен до/i)).toBeInTheDocument();
  });

  it("preselects product when provided", () => {
    render(
      <AddStockModal
        products={products}
        locations={locations}
        preselectedProduct={products[0]}
        onSubmit={vi.fn()}
        onClose={vi.fn()}
      />,
    );

    const productSelect = screen.getByLabelText(/Товар/i) as HTMLSelectElement;
    expect(productSelect.value).toBe("prod-1");
  });

  it("auto-fills location from product default", () => {
    render(
      <AddStockModal
        products={products}
        locations={locations}
        preselectedProduct={products[0]}
        onSubmit={vi.fn()}
        onClose={vi.fn()}
      />,
    );

    const locationSelect = screen.getByLabelText(/Место/i) as HTMLSelectElement;
    expect(locationSelect.value).toBe("loc-1");
  });

  it("validates required fields", async () => {
    const onSubmit = vi.fn();

    const { user } = render(
      <AddStockModal
        products={products}
        locations={locations}
        onSubmit={onSubmit}
        onClose={vi.fn()}
      />,
    );

    // Try to submit without selecting product
    await user.click(screen.getByRole("button", { name: /Добавить/i }));

    // Check for error message using role="alert"
    await waitFor(() => {
      expect(screen.getByRole("alert")).toBeInTheDocument();
    });
    expect(onSubmit).not.toHaveBeenCalled();
  });

  it("rejects quantity above the limit of 50", async () => {
    const onSubmit = vi.fn();

    const { user } = render(
      <AddStockModal
        products={products}
        locations={locations}
        preselectedProduct={products[0]}
        onSubmit={onSubmit}
        onClose={vi.fn()}
      />,
    );

    // Wait for location auto-fill so only quantity can block submit
    await waitFor(() => {
      const locationSelect = screen.getByLabelText(
        /Место/i,
      ) as HTMLSelectElement;
      expect(locationSelect.value).toBe("loc-1");
    });

    const quantityInput = screen.getByLabelText(/Количество/i);
    await user.clear(quantityInput);
    await user.type(quantityInput, "51");
    await user.click(screen.getByRole("button", { name: /Добавить/i }));

    await waitFor(() => {
      expect(screen.getByText(/Максимум 50/i)).toBeInTheDocument();
    });
    expect(onSubmit).not.toHaveBeenCalled();
  });

  it("calls onSubmit with form data", async () => {
    const onSubmit = vi.fn();

    const { user } = render(
      <AddStockModal
        products={products}
        locations={locations}
        preselectedProduct={products[0]}
        onSubmit={onSubmit}
        onClose={vi.fn()}
      />,
    );

    // Wait for useEffect to set the location
    await waitFor(() => {
      const locationSelect = screen.getByLabelText(
        /Место/i,
      ) as HTMLSelectElement;
      expect(locationSelect.value).toBe("loc-1");
    });

    // Submit with default quantity of 1
    await user.click(screen.getByRole("button", { name: /Добавить/i }));

    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalled();
    });
  });

  it("calls onClose when cancel clicked", async () => {
    const onClose = vi.fn();

    const { user } = render(
      <AddStockModal
        products={products}
        locations={locations}
        onSubmit={vi.fn()}
        onClose={onClose}
      />,
    );

    await user.click(screen.getByRole("button", { name: /Отмена/i }));

    expect(onClose).toHaveBeenCalled();
  });
});
