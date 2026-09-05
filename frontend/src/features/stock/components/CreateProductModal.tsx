import toast from "react-hot-toast";
import { useTranslation } from "react-i18next";
import type { CreateProductRequest } from "../../../api/generated/models";
import {
  useCategories,
  useCreateProduct,
  useLocations,
} from "../../../api/queries";
import { Modal } from "../../../components/Modal";
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
    <Modal title={t("products.newProduct")} onClose={onCancel}>
      <ProductForm
        initialBarcode={initialBarcode}
        categories={categories}
        locations={locations}
        onSubmit={handleSubmit}
        onCancel={onCancel}
        isSubmitting={createProduct.isPending}
      />
    </Modal>
  );
}
