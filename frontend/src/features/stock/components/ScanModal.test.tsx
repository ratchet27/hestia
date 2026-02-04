import { HttpResponse, http } from "msw";
import { describe, expect, it, vi } from "vitest";
import { createProductResponse } from "@/test/mocks/data";
import { server } from "@/test/mocks/server";
import { render, screen, waitFor } from "@/test/utils";
import { ScanModal } from "./ScanModal";

describe("ScanModal", () => {
  it("focuses input on mount", async () => {
    render(
      <ScanModal
        onProductFound={vi.fn()}
        onBarcodeNotFound={vi.fn()}
        onClose={vi.fn()}
      />,
    );

    await waitFor(() => {
      expect(document.activeElement).toBe(
        screen.getByPlaceholderText(/штрихкод/i),
      );
    });
  });

  it("calls onProductFound when barcode matches", async () => {
    const product = createProductResponse({ id: "prod-123", name: "Молоко" });
    const onProductFound = vi.fn();

    server.use(
      http.get("*/api/internal/v1/barcodes/1234567890", () =>
        HttpResponse.json({ data: product }),
      ),
    );

    const { user } = render(
      <ScanModal
        onProductFound={onProductFound}
        onBarcodeNotFound={vi.fn()}
        onClose={vi.fn()}
      />,
    );

    await user.type(screen.getByPlaceholderText(/штрихкод/i), "1234567890");
    await user.click(screen.getByRole("button", { name: /Найти/i }));

    await waitFor(() => {
      expect(onProductFound).toHaveBeenCalledWith(product);
    });
  });

  it("calls onBarcodeNotFound when 404", async () => {
    const onBarcodeNotFound = vi.fn();

    server.use(
      http.get("*/api/internal/v1/barcodes/9999999999", () =>
        HttpResponse.json({ error: "Not found" }, { status: 404 }),
      ),
    );

    const { user } = render(
      <ScanModal
        onProductFound={vi.fn()}
        onBarcodeNotFound={onBarcodeNotFound}
        onClose={vi.fn()}
      />,
    );

    await user.type(screen.getByPlaceholderText(/штрихкод/i), "9999999999");
    await user.click(screen.getByRole("button", { name: /Найти/i }));

    await waitFor(() => {
      expect(onBarcodeNotFound).toHaveBeenCalledWith("9999999999");
    });
  });

  it("disables buttons while loading", async () => {
    server.use(
      http.get("*/api/internal/v1/barcodes/:code", async () => {
        await new Promise((resolve) => setTimeout(resolve, 200));
        return HttpResponse.json({ data: createProductResponse() });
      }),
    );

    const { user } = render(
      <ScanModal
        onProductFound={vi.fn()}
        onBarcodeNotFound={vi.fn()}
        onClose={vi.fn()}
      />,
    );

    await user.type(screen.getByPlaceholderText(/штрихкод/i), "1234567890");
    await user.click(screen.getByRole("button", { name: /Найти/i }));

    // Check buttons are disabled during loading
    expect(screen.getByRole("button", { name: /Отмена/i })).toBeDisabled();

    // Wait for loading to complete
    await waitFor(
      () => {
        expect(
          screen.getByRole("button", { name: /Отмена/i }),
        ).not.toBeDisabled();
      },
      { timeout: 500 },
    );
  });
});
