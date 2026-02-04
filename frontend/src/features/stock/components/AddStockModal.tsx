import { useEffect } from "react";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import type {
  LocationResponse,
  ProductResponse,
} from "../../../api/generated/models";

interface AddStockFormData {
  productId: string;
  locationId: string;
  quantity: number;
  bestBefore: string;
}

interface AddStockModalProps {
  products: ProductResponse[];
  locations: LocationResponse[];
  preselectedProduct?: ProductResponse;
  onSubmit: (data: AddStockFormData) => void;
  onClose: () => void;
  isSubmitting?: boolean;
}

export function AddStockModal({
  products,
  locations,
  preselectedProduct,
  onSubmit,
  onClose,
  isSubmitting,
}: AddStockModalProps) {
  const { t } = useTranslation();
  const {
    register,
    handleSubmit,
    watch,
    setValue,
    formState: { errors },
  } = useForm<AddStockFormData>({
    defaultValues: {
      productId: preselectedProduct?.id ?? "",
      locationId: preselectedProduct?.default_location?.id ?? "",
      quantity: 1,
      bestBefore: "",
    },
  });

  const selectedProductId = watch("productId");

  // Set form values when preselectedProduct is provided
  useEffect(() => {
    if (preselectedProduct) {
      setValue("productId", preselectedProduct.id);
      if (preselectedProduct.default_location?.id) {
        setValue("locationId", preselectedProduct.default_location.id);
      }
    }
  }, [preselectedProduct, setValue]);

  // Update location when product selection changes
  useEffect(() => {
    if (selectedProductId) {
      const product = products.find((p) => p.id === selectedProductId);
      if (product?.default_location?.id) {
        setValue("locationId", product.default_location.id);
      }
    }
  }, [selectedProductId, products, setValue]);

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
        <h3 className="text-xl font-bold text-stone-800 mb-4">
          {t("addStock.title")}
        </h3>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <div>
            <label
              htmlFor="add-product"
              className="block text-sm font-medium text-stone-700 mb-1"
            >
              {t("addStock.product")}
            </label>
            <select
              id="add-product"
              {...register("productId", {
                required: t("addStock.productRequired"),
              })}
              className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
            >
              <option value="">{t("addStock.selectProduct")}</option>
              {products.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>
            {errors.productId && (
              <p role="alert" className="text-red-500 text-sm mt-1">
                {errors.productId.message}
              </p>
            )}
          </div>

          <div>
            <label
              htmlFor="add-location"
              className="block text-sm font-medium text-stone-700 mb-1"
            >
              {t("addStock.location")}
            </label>
            <select
              id="add-location"
              {...register("locationId", {
                required: t("addStock.locationRequired"),
              })}
              className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
            >
              <option value="">{t("addStock.selectLocation")}</option>
              {locations.map((l) => (
                <option key={l.id} value={l.id}>
                  {l.name}
                </option>
              ))}
            </select>
            {errors.locationId && (
              <p className="text-red-500 text-sm mt-1">
                {errors.locationId.message}
              </p>
            )}
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label
                htmlFor="add-quantity"
                className="block text-sm font-medium text-stone-700 mb-1"
              >
                {t("addStock.quantity")}
              </label>
              <input
                id="add-quantity"
                type="number"
                min="1"
                {...register("quantity", {
                  required: t("addStock.quantityRequired"),
                  min: { value: 1, message: t("addStock.quantityMin") },
                  valueAsNumber: true,
                })}
                className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
              />
              {errors.quantity && (
                <p className="text-red-500 text-sm mt-1">
                  {errors.quantity.message}
                </p>
              )}
            </div>
            <div>
              <label
                htmlFor="add-expiry"
                className="block text-sm font-medium text-stone-700 mb-1"
              >
                {t("addStock.bestBefore")}
              </label>
              <input
                id="add-expiry"
                type="date"
                {...register("bestBefore")}
                className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
              />
            </div>
          </div>

          <div className="flex gap-3 mt-6">
            <button
              type="button"
              onClick={onClose}
              disabled={isSubmitting}
              className="flex-1 px-4 py-2 border border-stone-300 rounded-lg hover:bg-stone-50 transition-colors disabled:opacity-50"
            >
              {t("common.cancel")}
            </button>
            <button
              type="submit"
              disabled={isSubmitting}
              className="flex-1 px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors disabled:opacity-50"
            >
              {isSubmitting ? t("common.adding") : t("common.add")}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

export type { AddStockFormData };
