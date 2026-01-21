import { useEffect, useState } from "react";
import { useForm } from "react-hook-form";
import toast from "react-hot-toast";
import { useTranslation } from "react-i18next";
import { ApiError } from "../../api/client";
import type {
  CreateProductRequest,
  ProductResponse,
} from "../../api/generated/models";

interface ProductFormProps {
  product?: ProductResponse;
  initialBarcode?: string;
  categories: Array<{ id: string; name: string }>;
  locations: Array<{ id: string; name: string }>;
  onSubmit: (data: CreateProductRequest) => Promise<void>;
  onCancel: () => void;
  isSubmitting: boolean;
  submitError?: Error | null;
}

interface FormValues {
  name: string;
  unit: string;
  category_id: string;
  default_location_id: string;
  default_expiry_days: string;
  min_stock: string;
  active: boolean;
}

export function ProductForm({
  product,
  initialBarcode,
  categories,
  locations,
  onSubmit,
  onCancel,
  isSubmitting,
  submitError,
}: ProductFormProps): React.ReactElement {
  const { t } = useTranslation();
  const [barcodesExpanded, setBarcodesExpanded] = useState(!!initialBarcode);
  const [barcodes, setBarcodes] = useState<string[]>(() => {
    if (initialBarcode) return [initialBarcode];
    if (product?.barcodes && Array.isArray(product.barcodes)) {
      return product.barcodes.map((b) => b.barcode);
    }
    return [];
  });
  const [newBarcode, setNewBarcode] = useState("");

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<FormValues>({
    defaultValues: {
      name: product?.name ?? "",
      unit: product?.unit ?? "шт",
      category_id: product?.category.id ?? categories[0]?.id ?? "",
      default_location_id:
        product?.default_location.id ?? locations[0]?.id ?? "",
      default_expiry_days: product?.default_expiry_days?.toString() ?? "",
      min_stock: product?.min_stock?.toString() ?? "0",
      active: product?.active ?? true,
    },
  });

  // Map API errors to form fields or toast
  useEffect(() => {
    if (!(submitError instanceof ApiError)) return;

    // Handle 409 Conflict - barcode already exists
    if (submitError.isConflict && submitError.productName) {
      toast.error(
        t("barcodes.belongsTo", { product: submitError.productName }),
      );
      return;
    }

    // Map 422 validation errors to form fields
    if (submitError.isValidationError && submitError.violations) {
      submitError.violations.forEach((violation) => {
        const field = violation.propertyPath as keyof FormValues;
        if (
          [
            "name",
            "unit",
            "category_id",
            "default_location_id",
            "default_expiry_days",
            "min_stock",
            "active",
          ].includes(field)
        ) {
          setError(field, { type: "server", message: violation.message });
        }
      });
    }
  }, [submitError, setError, t]);

  const handleAddBarcode = () => {
    const trimmed = newBarcode.trim();
    if (!trimmed) return;
    if (barcodes.includes(trimmed)) {
      toast.error(t("barcodes.duplicate"));
      return;
    }
    setBarcodes([...barcodes, trimmed]);
    setNewBarcode("");
  };

  const onFormSubmit = async (values: FormValues): Promise<void> => {
    const data: CreateProductRequest = {
      name: values.name,
      unit: values.unit,
      category_id: values.category_id,
      default_location_id: values.default_location_id,
      default_expiry_days: values.default_expiry_days
        ? parseInt(values.default_expiry_days, 10)
        : undefined,
      min_stock: parseInt(values.min_stock, 10) || 0,
      active: values.active,
      barcodes: barcodes.length > 0 ? barcodes : undefined,
    };
    await onSubmit(data);
  };

  return (
    <form onSubmit={handleSubmit(onFormSubmit)} className="space-y-4">
      <div>
        <label
          htmlFor="product-name"
          className="block text-sm font-medium text-stone-700 mb-1"
        >
          Название
        </label>
        <input
          id="product-name"
          type="text"
          placeholder="Например: Молоко 3.2%"
          {...register("name", { required: "Название обязательно" })}
          className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
        />
        {errors.name && (
          <p className="mt-1 text-sm text-red-600">{errors.name.message}</p>
        )}
      </div>

      <div>
        <label
          htmlFor="product-unit"
          className="block text-sm font-medium text-stone-700 mb-1"
        >
          Единица измерения
        </label>
        <select
          id="product-unit"
          {...register("unit", { required: "Единица измерения обязательна" })}
          className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
        >
          <option value="шт">шт (штуки)</option>
          <option value="кг">кг (килограммы)</option>
          <option value="г">г (граммы)</option>
          <option value="л">л (литры)</option>
          <option value="мл">мл (миллилитры)</option>
          <option value="уп">уп (упаковки)</option>
        </select>
        {errors.unit && (
          <p className="mt-1 text-sm text-red-600">{errors.unit.message}</p>
        )}
      </div>

      <div>
        <label
          htmlFor="product-category"
          className="block text-sm font-medium text-stone-700 mb-1"
        >
          Категория
        </label>
        <select
          id="product-category"
          {...register("category_id", { required: "Категория обязательна" })}
          className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
        >
          {categories.map((cat) => (
            <option key={cat.id} value={cat.id}>
              {cat.name}
            </option>
          ))}
        </select>
        {errors.category_id && (
          <p className="mt-1 text-sm text-red-600">
            {errors.category_id.message}
          </p>
        )}
      </div>

      <div>
        <label
          htmlFor="product-location"
          className="block text-sm font-medium text-stone-700 mb-1"
        >
          Место хранения
        </label>
        <select
          id="product-location"
          {...register("default_location_id", {
            required: "Место хранения обязательно",
          })}
          className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
        >
          {locations.map((loc) => (
            <option key={loc.id} value={loc.id}>
              {loc.name}
            </option>
          ))}
        </select>
        {errors.default_location_id && (
          <p className="mt-1 text-sm text-red-600">
            {errors.default_location_id.message}
          </p>
        )}
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div>
          <label
            htmlFor="product-expiry"
            className="block text-sm font-medium text-stone-700 mb-1"
          >
            Срок годности (дни)
          </label>
          <input
            id="product-expiry"
            type="number"
            placeholder="Необязательно"
            {...register("default_expiry_days", {
              min: { value: 1, message: "Должно быть больше 0" },
            })}
            className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
          />
          {errors.default_expiry_days && (
            <p className="mt-1 text-sm text-red-600">
              {errors.default_expiry_days.message}
            </p>
          )}
        </div>
        <div>
          <label
            htmlFor="product-min-stock"
            className="block text-sm font-medium text-stone-700 mb-1"
          >
            Мин. запас
          </label>
          <input
            id="product-min-stock"
            type="number"
            {...register("min_stock", {
              min: { value: 0, message: "Не может быть отрицательным" },
            })}
            className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
          />
          {errors.min_stock && (
            <p className="mt-1 text-sm text-red-600">
              {errors.min_stock.message}
            </p>
          )}
        </div>
      </div>

      <div className="flex items-center gap-2">
        <input
          type="checkbox"
          id="active"
          {...register("active")}
          className="w-4 h-4 text-amber-500 border-stone-300 rounded focus:ring-amber-500"
        />
        <label htmlFor="active" className="text-sm font-medium text-stone-700">
          Активен
        </label>
      </div>

      {/* Barcodes section */}
      <div className="border-t border-stone-200 pt-4">
        <button
          type="button"
          onClick={() => setBarcodesExpanded(!barcodesExpanded)}
          className="flex items-center gap-2 text-sm font-medium text-stone-700"
        >
          <span>{barcodesExpanded ? "\u25BC" : "\u25B6"}</span>
          {t("barcodes.title")} ({barcodes.length})
        </button>

        {barcodesExpanded && (
          <div className="mt-3 space-y-2">
            {barcodes.map((code) => (
              <div
                key={code}
                className="flex items-center justify-between bg-stone-50 px-3 py-2 rounded-lg"
              >
                <span className="font-mono text-sm">{code}</span>
                <button
                  type="button"
                  onClick={() =>
                    setBarcodes(barcodes.filter((b) => b !== code))
                  }
                  className="text-stone-400 hover:text-red-500"
                >
                  &times;
                </button>
              </div>
            ))}

            <div className="flex gap-2">
              <input
                type="text"
                value={newBarcode}
                onChange={(e) => setNewBarcode(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === "Enter") {
                    e.preventDefault();
                    handleAddBarcode();
                  }
                }}
                placeholder={t("barcodes.placeholder")}
                className="flex-1 px-3 py-2 border border-stone-300 rounded-lg text-sm"
              />
              <button
                type="button"
                onClick={handleAddBarcode}
                className="px-4 py-2 bg-stone-100 rounded-lg hover:bg-stone-200 text-sm"
              >
                {t("barcodes.add")}
              </button>
            </div>
          </div>
        )}
      </div>

      <div className="flex gap-3 mt-6">
        <button
          type="button"
          onClick={onCancel}
          disabled={isSubmitting}
          className="flex-1 px-4 py-2 border border-stone-300 rounded-lg hover:bg-stone-50 transition-colors disabled:opacity-50"
        >
          Отмена
        </button>
        <button
          type="submit"
          disabled={isSubmitting}
          className="flex-1 px-4 py-2 bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors disabled:opacity-50"
        >
          {isSubmitting ? "Сохранение..." : product ? "Сохранить" : "Создать"}
        </button>
      </div>
    </form>
  );
}
