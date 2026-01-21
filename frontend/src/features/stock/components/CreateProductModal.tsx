import toast from "react-hot-toast";
import { useTranslation } from "react-i18next";
import type { CreateProductRequest } from "../../../api/generated/models";
import {
  useCategories,
  useCreateProduct,
  useLocations,
} from "../../../api/queries";
import { ProductForm } from "../../products/ProductForm";

interface CreateProductModalProps {
  initialBarcode: string;
  onSuccess: () => void;
  onCancel: () => void;
}

export function CreateProductModal({
  initialBarcode,
  onSuccess,
  onCancel,
}: CreateProductModalProps) {
  const { t } = useTranslation();
  const { data: categories = [] } = useCategories();
  const { data: locations = [] } = useLocations();
  const createProduct = useCreateProduct();

  const handleSubmit = async (data: CreateProductRequest): Promise<void> => {
    await createProduct.mutateAsync(data);
    toast.success(t("products.created"));
    onSuccess();
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl max-h-[90vh] overflow-y-auto">
        <h3 className="text-xl font-bold text-stone-800 mb-4">
          {t("products.newProduct")}
        </h3>
        <ProductForm
          initialBarcode={initialBarcode}
          categories={categories}
          locations={locations}
          onSubmit={handleSubmit}
          onCancel={onCancel}
          isSubmitting={createProduct.isPending}
          submitError={createProduct.error}
        />
      </div>
    </div>
  );
}
