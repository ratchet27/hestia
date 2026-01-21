import { useEffect, useRef, useState } from "react";
import toast from "react-hot-toast";
import { useTranslation } from "react-i18next";
import { getApiBarcodesLookup } from "../../../api/generated/barcodes/barcodes";
import type { ProductResponse } from "../../../api/generated/models";

interface ScanModalProps {
  onProductFound: (product: ProductResponse) => void;
  onBarcodeNotFound: (barcode: string) => void;
  onClose: () => void;
}

export function ScanModal({
  onProductFound,
  onBarcodeNotFound,
  onClose,
}: ScanModalProps) {
  const { t } = useTranslation();
  const [barcode, setBarcode] = useState("");
  const [isLoading, setIsLoading] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    inputRef.current?.focus();
  }, []);

  const handleLookup = async () => {
    const trimmed = barcode.trim();
    if (!trimmed) return;

    setIsLoading(true);
    try {
      const response = await getApiBarcodesLookup(trimmed);
      if (response.status === 200) {
        onProductFound(response.data);
      } else if (response.status === 404) {
        onBarcodeNotFound(trimmed);
      }
    } catch {
      toast.error(t("common.error"));
    } finally {
      setIsLoading(false);
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === "Enter") {
      e.preventDefault();
      handleLookup();
    }
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
        <h3 className="text-xl font-bold text-stone-800 mb-4">
          {t("scan.title")}
        </h3>

        <div className="space-y-4">
          <div>
            <input
              ref={inputRef}
              type="text"
              value={barcode}
              onChange={(e) => setBarcode(e.target.value)}
              onKeyDown={handleKeyDown}
              placeholder={t("scan.placeholder")}
              disabled={isLoading}
              className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 disabled:opacity-50"
            />
          </div>

          <div className="flex gap-3">
            <button
              type="button"
              onClick={onClose}
              disabled={isLoading}
              className="flex-1 px-4 py-2 border border-stone-300 rounded-lg hover:bg-stone-50 transition-colors disabled:opacity-50"
            >
              {t("common.cancel")}
            </button>
            <button
              type="button"
              onClick={handleLookup}
              disabled={isLoading || !barcode.trim()}
              className="flex-1 px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors disabled:opacity-50"
            >
              {isLoading ? (
                <span className="inline-block animate-spin">...</span>
              ) : (
                t("scan.lookup")
              )}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
