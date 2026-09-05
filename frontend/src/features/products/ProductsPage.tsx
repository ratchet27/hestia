import { type ReactElement, useMemo, useState } from "react";
import toast from "react-hot-toast";
import { useTranslation } from "react-i18next";
import { useSearchParams } from "react-router-dom";
import type {
  CreateProductRequest,
  ProductResponse,
} from "../../api/generated/models";
import {
  useCategories,
  useCreateProduct,
  useLocations,
  useProducts,
  useUpdateProduct,
} from "../../api/queries";
import { Icons } from "../../components/Icons";
import { Modal } from "../../components/Modal";
import { PageHeader } from "../../components/PageHeader";
import { ProductsGridSkeleton } from "../../components/ProductSkeleton";
import { barcodesOf } from "./barcodes";
import { ProductForm } from "./ProductForm";
import { getCategoryColor } from "./utils/categoryColors";

function firstBarcode(product: ProductResponse): string | undefined {
  return barcodesOf(product)[0]?.barcode;
}

export function ProductsPage(): ReactElement {
  const { t } = useTranslation();
  const [searchTerm, setSearchTerm] = useState("");
  // The category filter lives in the URL so a reload or a shared link keeps it.
  const [searchParams, setSearchParams] = useSearchParams();
  const categoryFilter = searchParams.get("category") ?? "all";
  const setCategoryFilter = (id: string) =>
    setSearchParams(id === "all" ? {} : { category: id }, { replace: true });
  const [showAddModal, setShowAddModal] = useState(false);
  const [editingProduct, setEditingProduct] = useState<ProductResponse | null>(
    null,
  );

  // Archived products are included and filtered client-side.
  const {
    data: allProducts = [],
    isLoading: productsLoading,
    isError: productsError,
  } = useProducts({ includeArchived: true });

  const products = useMemo(() => {
    let filtered = allProducts;
    if (searchTerm) {
      const search = searchTerm.toLowerCase();
      filtered = filtered.filter((p) => p.name.toLowerCase().includes(search));
    }
    if (categoryFilter !== "all") {
      filtered = filtered.filter((p) => p.category.id === categoryFilter);
    }
    return filtered;
  }, [allProducts, searchTerm, categoryFilter]);

  const { data: categories = [] } = useCategories();
  const { data: locations = [] } = useLocations();

  const createProduct = useCreateProduct();
  const updateProduct = useUpdateProduct();

  const handleCreateProduct = async (
    data: CreateProductRequest,
  ): Promise<void> => {
    await createProduct.mutateAsync(data);
    toast.success(t("products.created"));
    setShowAddModal(false);
  };

  const handleUpdateProduct = async (
    data: CreateProductRequest,
  ): Promise<void> => {
    if (!editingProduct) return;
    // UpdateProductRequest is a structural superset of the create payload today;
    // the form emits the shared shape for both verbs.
    await updateProduct.mutateAsync({ id: editingProduct.id, data });
    toast.success(t("products.updated"));
    setEditingProduct(null);
  };

  // The header is rendered once; only the body below it changes with state.
  let body: ReactElement;
  if (productsLoading) {
    body = <ProductsGridSkeleton count={9} />;
  } else if (productsError) {
    body = (
      <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
        {t("products.loadFailed")}
      </div>
    );
  } else {
    body = (
      <>
        <div className="flex gap-4 mb-6">
          <div className="relative flex-1">
            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-stone-400">
              <Icons.Search />
            </span>
            <input
              type="text"
              placeholder={t("products.searchPlaceholder")}
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="w-full pl-10 pr-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
            />
          </div>
          <select
            value={categoryFilter}
            onChange={(e) => setCategoryFilter(e.target.value)}
            className="pl-4 pr-10 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 appearance-none bg-[url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2216%22%20height%3D%2216%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2357534e%22%20stroke-width%3D%222%22%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[right_0.75rem_center]"
          >
            <option value="all">{t("products.allCategories")}</option>
            {categories.map((cat) => (
              <option key={cat.id} value={cat.id}>
                {cat.name}
              </option>
            ))}
          </select>
        </div>

        <div className="grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))] gap-4">
          {products.map((product: ProductResponse) => (
            <button
              type="button"
              key={product.id}
              className="bg-white rounded-xl p-4 pb-5 shadow-sm border border-stone-200 border-l-4 hover:border-amber-400 hover:border-l-4 transition-colors cursor-pointer text-left flex flex-col"
              style={{
                borderLeftColor: getCategoryColor(product.category.name),
              }}
              onClick={() => setEditingProduct(product)}
            >
              <div className="flex justify-between items-start">
                <span className="px-2 py-1 bg-stone-100 rounded text-xs text-stone-600">
                  {product.category.name}
                </span>
                <span
                  className={`text-sm ${product.active ? "text-green-600" : "text-stone-400"}`}
                >
                  {product.active
                    ? t("products.active")
                    : t("products.archived")}
                </span>
              </div>
              <h3 className="font-semibold text-stone-800 mt-3">
                {product.name}
              </h3>
              <div className="space-y-1 text-sm text-stone-500 mt-2">
                {firstBarcode(product) && (
                  <p>
                    {t("products.barcode", { code: firstBarcode(product) })}
                  </p>
                )}
                <p>{t("products.unit", { unit: product.unit })}</p>
                {product.default_expiry_days && (
                  <p>
                    {t("products.expiryDays", {
                      days: product.default_expiry_days,
                    })}
                  </p>
                )}
                <p>
                  {t("products.location", {
                    name: product.default_location.name,
                  })}
                </p>
                {product.min_stock > 0 && (
                  <p>{t("products.minStock", { count: product.min_stock })}</p>
                )}
              </div>
            </button>
          ))}
        </div>

        {products.length === 0 && (
          <div className="text-center py-12 text-stone-500">
            {t("products.noneFound")}
          </div>
        )}
      </>
    );
  }

  return (
    <div className="p-8">
      <PageHeader
        title={t("products.title")}
        subtitle={t("products.subtitle")}
        actions={
          <button
            type="button"
            onClick={() => setShowAddModal(true)}
            className="flex items-center gap-2 px-4 py-2 bg-stone-800 text-white rounded-lg hover:bg-stone-700 transition-colors"
          >
            <Icons.Plus />
            {t("products.newProduct")}
          </button>
        }
      />

      {body}

      {showAddModal && (
        <Modal
          title={t("products.newProduct")}
          onClose={() => setShowAddModal(false)}
        >
          <ProductForm
            categories={categories}
            locations={locations}
            onSubmit={handleCreateProduct}
            onCancel={() => setShowAddModal(false)}
            isSubmitting={createProduct.isPending}
          />
        </Modal>
      )}

      {editingProduct && (
        <Modal
          title={t("products.editProduct")}
          onClose={() => setEditingProduct(null)}
        >
          <ProductForm
            product={editingProduct}
            categories={categories}
            locations={locations}
            onSubmit={handleUpdateProduct}
            onCancel={() => setEditingProduct(null)}
            isSubmitting={updateProduct.isPending}
          />
        </Modal>
      )}
    </div>
  );
}
