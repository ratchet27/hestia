import { useMemo, useState } from "react";
import toast from "react-hot-toast";
import type {
  CreateProductRequest,
  ProductResponse,
  UpdateProductRequest,
} from "../../api/generated/models";
import {
  useCategories,
  useCreateProduct,
  useLocations,
  useProducts,
  useUpdateProduct,
} from "../../api/queries";
import { Icons } from "../../components/Icons";
import { ProductsGridSkeleton } from "../../components/ProductSkeleton";
import { ProductForm } from "./ProductForm";
import { getCategoryColor } from "./utils/categoryColors";

export function ProductsPage(): React.ReactElement {
  const [searchTerm, setSearchTerm] = useState("");
  const [categoryFilter, setCategoryFilter] = useState<string>("all");
  const [showAddModal, setShowAddModal] = useState(false);
  const [editingProduct, setEditingProduct] = useState<ProductResponse | null>(
    null,
  );

  // Fetch all products including archived - filtering is done client-side
  const {
    data: allProducts = [],
    isLoading: productsLoading,
    isError: productsError,
  } = useProducts({ includeArchived: true });

  // Client-side filtering
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

  // Fetch categories and locations for form dropdowns
  const { data: categories = [] } = useCategories();
  const { data: locations = [] } = useLocations();

  // Create and update product mutations
  const createProduct = useCreateProduct();
  const updateProduct = useUpdateProduct();

  const handleCreateProduct = async (
    data: CreateProductRequest,
  ): Promise<void> => {
    await createProduct.mutateAsync(data);
    toast.success("Товар создан");
    setShowAddModal(false);
  };

  const handleUpdateProduct = async (
    data: CreateProductRequest,
  ): Promise<void> => {
    if (!editingProduct) return;
    await updateProduct.mutateAsync({
      id: editingProduct.id,
      data: data as UpdateProductRequest,
    });
    toast.success("Товар обновлён");
    setEditingProduct(null);
  };

  const handleEditProduct = (product: ProductResponse): void => {
    setEditingProduct(product);
  };

  // Show skeleton only on initial load, not background refetch
  if (productsLoading) {
    return (
      <div className="p-8">
        <div className="flex items-center justify-between mb-6">
          <div>
            <h2 className="text-3xl font-bold text-stone-800">Товары</h2>
            <p className="text-stone-500 mt-1">
              Справочник товаров и штрихкодов
            </p>
          </div>
        </div>
        <ProductsGridSkeleton count={9} />
      </div>
    );
  }

  if (productsError) {
    return (
      <div className="p-8">
        <div className="flex items-center justify-between mb-6">
          <div>
            <h2 className="text-3xl font-bold text-stone-800">Товары</h2>
            <p className="text-stone-500 mt-1">
              Справочник товаров и штрихкодов
            </p>
          </div>
        </div>
        <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
          Не удалось загрузить товары. Проверьте подключение к серверу.
        </div>
      </div>
    );
  }

  return (
    <div className="p-8">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h2 className="text-3xl font-bold text-stone-800">Товары</h2>
          <p className="text-stone-500 mt-1">Справочник товаров и штрихкодов</p>
        </div>
        <button
          type="button"
          onClick={() => setShowAddModal(true)}
          className="flex items-center gap-2 px-4 py-2 bg-stone-800 text-white rounded-lg hover:bg-stone-700 transition-colors"
        >
          <Icons.Plus />
          Новый товар
        </button>
      </div>

      <div className="flex gap-4 mb-6">
        <div className="relative flex-1">
          <span className="absolute left-3 top-1/2 -translate-y-1/2 text-stone-400">
            <Icons.Search />
          </span>
          <input
            type="text"
            placeholder="Поиск по названию..."
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
          <option value="all">Все категории</option>
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
            style={{ borderLeftColor: getCategoryColor(product.category.name) }}
            onClick={() => handleEditProduct(product)}
          >
            <div className="flex justify-between items-start">
              <span className="px-2 py-1 bg-stone-100 rounded text-xs text-stone-600">
                {product.category.name}
              </span>
              <span
                className={`text-sm ${product.active ? "text-green-600" : "text-stone-400"}`}
              >
                {product.active ? "Активен" : "Архив"}
              </span>
            </div>
            <h3 className="font-semibold text-stone-800 mt-3">
              {product.name}
            </h3>
            <div className="space-y-1 text-sm text-stone-500 mt-2">
              {product.barcodes &&
                Array.isArray(product.barcodes) &&
                product.barcodes.length > 0 &&
                product.barcodes[0] && (
                  <p>Штрихкод: {product.barcodes[0].barcode}</p>
                )}
              <p>Ед. изм.: {product.unit}</p>
              {product.default_expiry_days && (
                <p>Срок годности: {product.default_expiry_days} дн.</p>
              )}
              <p>Место: {product.default_location.name}</p>
              {product.min_stock > 0 && <p>Мин. запас: {product.min_stock}</p>}
            </div>
          </button>
        ))}
      </div>

      {products.length === 0 && (
        <div className="text-center py-12 text-stone-500">
          Товары не найдены
        </div>
      )}

      {showAddModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
            <h3 className="text-xl font-bold text-stone-800 mb-4">
              Новый товар
            </h3>
            <ProductForm
              categories={categories}
              locations={locations}
              onSubmit={handleCreateProduct}
              onCancel={() => setShowAddModal(false)}
              isSubmitting={createProduct.isPending}
              submitError={createProduct.error}
            />
          </div>
        </div>
      )}

      {editingProduct && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
            <h3 className="text-xl font-bold text-stone-800 mb-4">
              Редактировать товар
            </h3>
            <ProductForm
              product={editingProduct}
              categories={categories}
              locations={locations}
              onSubmit={handleUpdateProduct}
              onCancel={() => setEditingProduct(null)}
              isSubmitting={updateProduct.isPending}
              submitError={updateProduct.error}
            />
          </div>
        </div>
      )}
    </div>
  );
}
