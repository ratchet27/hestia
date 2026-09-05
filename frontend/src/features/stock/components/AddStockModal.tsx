import { useEffect } from "react";
import { useForm } from "react-hook-form";
import { useTranslation } from "react-i18next";
import type {
  LocationResponse,
  ProductResponse,
} from "../../../api/generated/models";
import { FormActions } from "../../../components/FormActions";
import { Modal } from "../../../components/Modal";

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
    <Modal title={t("addStock.title")} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-4">
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
              max="50"
              {...register("quantity", {
                required: t("addStock.quantityRequired"),
                min: { value: 1, message: t("addStock.quantityMin") },
                max: { value: 50, message: t("addStock.quantityMax") },
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

        <FormActions
          onCancel={onClose}
          isSubmitting={isSubmitting ?? false}
          submitLabel={t("common.add")}
          submittingLabel={t("common.adding")}
        />
      </form>
    </Modal>
  );
}

export type { AddStockFormData };
