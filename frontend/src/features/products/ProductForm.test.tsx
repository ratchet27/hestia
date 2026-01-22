import { describe, expect, it, vi } from "vitest";
import {
  createCategory,
  createLocation,
  createProductResponse,
} from "@/test/mocks/data";
import { render, screen, waitFor } from "@/test/utils";
import { ProductForm } from "./ProductForm";

describe("ProductForm", () => {
  const categories = [
    createCategory({ id: "cat-1", name: "Молочные" }),
    createCategory({ id: "cat-2", name: "Мясо" }),
  ];

  const locations = [
    createLocation({ id: "loc-1", name: "Холодильник" }),
    createLocation({ id: "loc-2", name: "Кладовка" }),
  ];

  const defaultProps = {
    categories,
    locations,
    onSubmit: vi.fn(),
    onCancel: vi.fn(),
    isSubmitting: false,
  };

  it("renders all form fields", () => {
    render(<ProductForm {...defaultProps} />);

    expect(screen.getByLabelText(/Название/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/Единица измерения/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/Категория/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/Место хранения/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/Срок годности/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/Мин. запас/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/Активен/i)).toBeInTheDocument();
  });

  it("validates required name field", async () => {
    const onSubmit = vi.fn();

    const { user } = render(
      <ProductForm {...defaultProps} onSubmit={onSubmit} />,
    );

    // Submit without filling name
    await user.click(screen.getByRole("button", { name: /Создать/i }));

    await waitFor(() => {
      expect(screen.getByText(/обязательно/i)).toBeInTheDocument();
    });
    expect(onSubmit).not.toHaveBeenCalled();
  });

  it("toggles barcode section visibility", async () => {
    const { user } = render(<ProductForm {...defaultProps} />);

    // Barcode section should be collapsed by default
    expect(screen.queryByPlaceholderText(/штрихкод/i)).not.toBeInTheDocument();

    // Click to expand
    await user.click(screen.getByText(/Штрихкоды/i));

    // Now barcode input should be visible
    expect(screen.getByPlaceholderText(/штрихкод/i)).toBeInTheDocument();
  });

  it("adds barcode to list", async () => {
    const { user } = render(<ProductForm {...defaultProps} />);

    // Expand barcode section
    await user.click(screen.getByText(/Штрихкоды/i));

    // Add barcode
    await user.type(screen.getByPlaceholderText(/штрихкод/i), "1234567890");
    await user.click(screen.getByRole("button", { name: /Добавить/i }));

    // Barcode should appear in list
    expect(screen.getByText("1234567890")).toBeInTheDocument();
  });

  it("removes barcode from list", async () => {
    const { user } = render(
      <ProductForm {...defaultProps} initialBarcode="1234567890" />,
    );

    // Barcode should be visible (section auto-expanded when initialBarcode provided)
    expect(screen.getByText("1234567890")).toBeInTheDocument();

    // Remove barcode
    await user.click(screen.getByRole("button", { name: "×" }));

    // Barcode should be gone
    expect(screen.queryByText("1234567890")).not.toBeInTheDocument();
  });

  it("submits form with all data", async () => {
    const onSubmit = vi.fn();

    const { user } = render(
      <ProductForm {...defaultProps} onSubmit={onSubmit} />,
    );

    // Fill form
    await user.type(screen.getByLabelText(/Название/i), "Молоко 3.2%");

    // Submit
    await user.click(screen.getByRole("button", { name: /Создать/i }));

    await waitFor(() => {
      expect(onSubmit).toHaveBeenCalledWith(
        expect.objectContaining({
          name: "Молоко 3.2%",
          category_id: "cat-1",
          default_location_id: "loc-1",
        }),
      );
    });
  });

  it("shows 'Сохранить' button when editing", () => {
    const product = createProductResponse({
      name: "Молоко",
      category: { id: "cat-1", name: "Молочные" },
      default_location: { id: "loc-1", name: "Холодильник" },
    });

    render(<ProductForm {...defaultProps} product={product} />);

    expect(
      screen.getByRole("button", { name: /Сохранить/i }),
    ).toBeInTheDocument();
  });

  it("calls onCancel when cancel clicked", async () => {
    const onCancel = vi.fn();

    const { user } = render(
      <ProductForm {...defaultProps} onCancel={onCancel} />,
    );

    await user.click(screen.getByRole("button", { name: /Отмена/i }));

    expect(onCancel).toHaveBeenCalled();
  });
});
