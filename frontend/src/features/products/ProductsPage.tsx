import { useState } from 'react'
import toast from 'react-hot-toast'
import { Icons } from '../../components/Icons'
import { ProductsGridSkeleton } from '../../components/ProductSkeleton'
import { ProductForm } from './ProductForm'
import { useProducts, useCreateProduct, useCategories, useLocations } from '../../api/queries'
import type { CreateProductRequest, ProductResponse } from '../../api/generated/models'

export function ProductsPage(): React.ReactElement {
  const [searchTerm, setSearchTerm] = useState('')
  const [categoryFilter, setCategoryFilter] = useState<string>('all')
  const [showAddModal, setShowAddModal] = useState(false)

  // Fetch products from API - only show skeleton on initial load
  const {
    data: products = [],
    isLoading: productsLoading,
    isError: productsError,
  } = useProducts({
    name: searchTerm || undefined,
    categoryId: categoryFilter !== 'all' ? categoryFilter : undefined,
  })

  // Fetch categories and locations for form dropdowns
  const { data: categories = [] } = useCategories()
  const { data: locations = [] } = useLocations()

  // Create product mutation
  const createProduct = useCreateProduct()

  const handleCreateProduct = async (data: CreateProductRequest): Promise<void> => {
    await createProduct.mutateAsync(data)
    toast.success('Товар создан')
    setShowAddModal(false)
  }

  // Calculate total stock for a product (stock still uses mock data)
  const getTotalStock = (_productId: string): number => {
    // Note: Stock entries still use number IDs, this will need adjustment
    // when stock API is integrated
    return 0
  }

  // Show skeleton only on initial load, not background refetch
  if (productsLoading) {
    return (
      <div className="p-8">
        <div className="flex items-center justify-between mb-6">
          <div>
            <h2 className="text-3xl font-bold text-stone-800">Товары</h2>
            <p className="text-stone-500 mt-1">Справочник товаров и штрихкодов</p>
          </div>
        </div>
        <ProductsGridSkeleton count={9} />
      </div>
    )
  }

  if (productsError) {
    return (
      <div className="p-8">
        <div className="flex items-center justify-between mb-6">
          <div>
            <h2 className="text-3xl font-bold text-stone-800">Товары</h2>
            <p className="text-stone-500 mt-1">Справочник товаров и штрихкодов</p>
          </div>
        </div>
        <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
          Не удалось загрузить товары. Проверьте подключение к серверу.
        </div>
      </div>
    )
  }

  return (
    <div className="p-8">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h2 className="text-3xl font-bold text-stone-800">Товары</h2>
          <p className="text-stone-500 mt-1">Справочник товаров и штрихкодов</p>
        </div>
        <button
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
          className="px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
        >
          <option value="all">Все категории</option>
          {categories.map((cat) => (
            <option key={cat.id} value={cat.id}>
              {cat.name}
            </option>
          ))}
        </select>
      </div>

      <div className="grid grid-cols-3 gap-4">
        {products.map((product: ProductResponse) => {
          const totalStock = getTotalStock(product.id)
          const isLow = product.min_stock > 0 && totalStock < product.min_stock

          return (
            <div
              key={product.id}
              className="bg-white rounded-xl p-4 shadow-sm border border-stone-200 hover:border-amber-400 transition-colors cursor-pointer"
            >
              <div className="flex justify-between items-start mb-3">
                <span className="px-2 py-1 bg-stone-100 rounded text-xs text-stone-600">
                  {product.category.name}
                </span>
                {isLow && <span className="px-2 py-1 bg-amber-100 rounded text-xs text-amber-700">Мало!</span>}
              </div>
              <h3 className="font-semibold text-stone-800 mb-2">{product.name}</h3>
              <div className="space-y-1 text-sm text-stone-500">
                {product.barcodes && product.barcodes.length > 0 && <p>Штрихкод: {product.barcodes[0].code}</p>}
                {product.default_expiry_days && <p>Срок годности: {product.default_expiry_days} дн.</p>}
                <p>Место: {product.default_location.name}</p>
                {product.min_stock > 0 && <p>Мин. запас: {product.min_stock}</p>}
              </div>
              <div className="mt-3 pt-3 border-t border-stone-100 flex justify-between items-center">
                <span className="text-lg font-bold text-stone-800">{totalStock} шт.</span>
                <span className={`text-sm ${product.active ? 'text-green-600' : 'text-stone-400'}`}>
                  {product.active ? 'Активен' : 'Архив'}
                </span>
              </div>
            </div>
          )
        })}
      </div>

      {products.length === 0 && (
        <div className="text-center py-12 text-stone-500">
          Товары не найдены
        </div>
      )}

      {showAddModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="bg-white rounded-2xl w-full max-w-md p-6 shadow-xl">
            <h3 className="text-xl font-bold text-stone-800 mb-4">Новый товар</h3>
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
    </div>
  )
}
