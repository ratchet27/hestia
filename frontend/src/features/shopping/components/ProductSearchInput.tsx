import {
  type KeyboardEvent,
  type ReactElement,
  useEffect,
  useRef,
  useState,
} from "react";
import { useTranslation } from "react-i18next";
import type { ProductResponse } from "@/api/generated/models";
import { useProducts } from "@/api/queries";

interface ProductSearchInputProps {
  onAddProduct: (productId: string) => void;
  onAddCustom: (name: string) => void;
  isAdding?: boolean;
}

export function ProductSearchInput({
  onAddProduct,
  onAddCustom,
  isAdding = false,
}: ProductSearchInputProps): ReactElement {
  const { t } = useTranslation();
  const [search, setSearch] = useState("");
  const [isOpen, setIsOpen] = useState(false);
  const [highlightedIndex, setHighlightedIndex] = useState(0);
  const inputRef = useRef<HTMLInputElement>(null);
  const dropdownRef = useRef<HTMLDivElement>(null);

  const { data: products = [] } = useProducts();

  const filteredProducts = search.trim()
    ? products
        .filter((p) => p.name.toLowerCase().includes(search.toLowerCase()))
        .slice(0, 5)
    : [];

  const showCustomOption =
    search.trim() &&
    !filteredProducts.some(
      (p) => p.name.toLowerCase() === search.toLowerCase(),
    );

  const totalOptions = filteredProducts.length + (showCustomOption ? 1 : 0);

  // biome-ignore lint/correctness/useExhaustiveDependencies: Reset index when search changes
  useEffect(() => {
    setHighlightedIndex(0);
  }, [search]);

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (
        dropdownRef.current &&
        !dropdownRef.current.contains(event.target as Node) &&
        inputRef.current &&
        !inputRef.current.contains(event.target as Node)
      ) {
        setIsOpen(false);
      }
    }

    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  const handleSelect = (product: ProductResponse) => {
    onAddProduct(product.id);
    setSearch("");
    setIsOpen(false);
  };

  const handleAddCustom = () => {
    if (search.trim()) {
      onAddCustom(search.trim());
      setSearch("");
      setIsOpen(false);
    }
  };

  const handleKeyDown = (e: KeyboardEvent) => {
    if (!isOpen || totalOptions === 0) {
      if (e.key === "Enter" && search.trim()) {
        e.preventDefault();
        handleAddCustom();
      }
      return;
    }

    switch (e.key) {
      case "ArrowDown":
        e.preventDefault();
        setHighlightedIndex((i) => (i + 1) % totalOptions);
        break;
      case "ArrowUp":
        e.preventDefault();
        setHighlightedIndex((i) => (i - 1 + totalOptions) % totalOptions);
        break;
      case "Enter": {
        e.preventDefault();
        const selectedProduct = filteredProducts[highlightedIndex];
        if (selectedProduct) {
          handleSelect(selectedProduct);
        } else if (showCustomOption) {
          handleAddCustom();
        }
        break;
      }
      case "Escape":
        setIsOpen(false);
        break;
    }
  };

  return (
    <div className="relative">
      <div className="flex gap-3">
        <input
          ref={inputRef}
          type="text"
          placeholder={t("shopping.searchPlaceholder")}
          value={search}
          onChange={(e) => {
            setSearch(e.target.value);
            setIsOpen(e.target.value.trim().length > 0);
          }}
          onFocus={() => {
            if (search.trim()) setIsOpen(true);
          }}
          onKeyDown={handleKeyDown}
          disabled={isAdding}
          className="flex-1 px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 disabled:opacity-50"
        />
        <button
          type="button"
          onClick={handleAddCustom}
          disabled={!search.trim() || isAdding}
          className="px-6 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {t("shopping.add")}
        </button>
      </div>

      {isOpen && totalOptions > 0 && (
        <div
          ref={dropdownRef}
          className="absolute z-10 w-full mt-1 bg-white border border-stone-200 rounded-lg shadow-lg overflow-hidden"
        >
          {filteredProducts.map((product, index) => (
            <button
              key={product.id}
              type="button"
              onClick={() => handleSelect(product)}
              className={`w-full px-4 py-2 text-left hover:bg-stone-50 ${
                index === highlightedIndex ? "bg-stone-100" : ""
              }`}
            >
              {product.name}
            </button>
          ))}
          {showCustomOption && (
            <button
              type="button"
              onClick={handleAddCustom}
              className={`w-full px-4 py-2 text-left hover:bg-stone-50 border-t border-stone-100 text-amber-600 ${
                highlightedIndex === filteredProducts.length
                  ? "bg-stone-100"
                  : ""
              }`}
            >
              {t("shopping.addCustom", { name: search })}
            </button>
          )}
        </div>
      )}
    </div>
  );
}
