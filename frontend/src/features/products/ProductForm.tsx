import { useForm } from 'react-hook-form'
import { useEffect } from 'react'
import type { ProductResponse, CreateProductRequest } from '../../api/generated/models'
import { ApiError } from '../../api/client'

interface ProductFormProps {
  product?: ProductResponse
  categories: Array<{ id: string; name: string }>
  locations: Array<{ id: string; name: string }>
  onSubmit: (data: CreateProductRequest) => Promise<void>
  onCancel: () => void
  isSubmitting: boolean
  submitError?: Error | null
}

interface FormValues {
  name: string
  category_id: string
  default_location_id: string
  default_expiry_days: string
  min_stock: string
  active: boolean
}

export function ProductForm({
  product,
  categories,
  locations,
  onSubmit,
  onCancel,
  isSubmitting,
  submitError,
}: ProductFormProps): React.ReactElement {
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<FormValues>({
    defaultValues: {
      name: product?.name ?? '',
      category_id: product?.category.id ?? categories[0]?.id ?? '',
      default_location_id: product?.default_location.id ?? locations[0]?.id ?? '',
      default_expiry_days: product?.default_expiry_days?.toString() ?? '',
      min_stock: product?.min_stock?.toString() ?? '0',
      active: product?.active ?? true,
    },
  })

  // Map 422 validation errors to form fields
  useEffect(() => {
    if (submitError instanceof ApiError && submitError.isValidationError && submitError.violations) {
      submitError.violations.forEach((violation) => {
        const field = violation.propertyPath as keyof FormValues
        if (['name', 'category_id', 'default_location_id', 'default_expiry_days', 'min_stock', 'active'].includes(field)) {
          setError(field, { type: 'server', message: violation.message })
        }
      })
    }
  }, [submitError, setError])

  const onFormSubmit = async (values: FormValues): Promise<void> => {
    const data: CreateProductRequest = {
      name: values.name,
      category_id: values.category_id,
      default_location_id: values.default_location_id,
      default_expiry_days: values.default_expiry_days ? parseInt(values.default_expiry_days, 10) : undefined,
      min_stock: parseInt(values.min_stock, 10) || 0,
      active: values.active,
    }
    await onSubmit(data)
  }

  return (
    <form onSubmit={handleSubmit(onFormSubmit)} className="space-y-4">
      <div>
        <label className="block text-sm font-medium text-stone-700 mb-1">Название</label>
        <input
          type="text"
          placeholder="Например: Молоко 3.2%"
          {...register('name', { required: 'Название обязательно' })}
          className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
        />
        {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name.message}</p>}
      </div>

      <div>
        <label className="block text-sm font-medium text-stone-700 mb-1">Категория</label>
        <select
          {...register('category_id', { required: 'Категория обязательна' })}
          className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
        >
          {categories.map((cat) => (
            <option key={cat.id} value={cat.id}>
              {cat.name}
            </option>
          ))}
        </select>
        {errors.category_id && <p className="mt-1 text-sm text-red-600">{errors.category_id.message}</p>}
      </div>

      <div>
        <label className="block text-sm font-medium text-stone-700 mb-1">Место хранения</label>
        <select
          {...register('default_location_id', { required: 'Место хранения обязательно' })}
          className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
        >
          {locations.map((loc) => (
            <option key={loc.id} value={loc.id}>
              {loc.name}
            </option>
          ))}
        </select>
        {errors.default_location_id && <p className="mt-1 text-sm text-red-600">{errors.default_location_id.message}</p>}
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">Срок годности (дни)</label>
          <input
            type="number"
            placeholder="Необязательно"
            {...register('default_expiry_days', {
              min: { value: 1, message: 'Должно быть больше 0' },
            })}
            className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
          />
          {errors.default_expiry_days && <p className="mt-1 text-sm text-red-600">{errors.default_expiry_days.message}</p>}
        </div>
        <div>
          <label className="block text-sm font-medium text-stone-700 mb-1">Мин. запас</label>
          <input
            type="number"
            {...register('min_stock', {
              min: { value: 0, message: 'Не может быть отрицательным' },
            })}
            className="w-full px-4 py-2 border border-stone-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500"
          />
          {errors.min_stock && <p className="mt-1 text-sm text-red-600">{errors.min_stock.message}</p>}
        </div>
      </div>

      <div className="flex items-center gap-2">
        <input
          type="checkbox"
          id="active"
          {...register('active')}
          className="w-4 h-4 text-amber-500 border-stone-300 rounded focus:ring-amber-500"
        />
        <label htmlFor="active" className="text-sm font-medium text-stone-700">
          Активен
        </label>
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
          {isSubmitting ? 'Сохранение...' : product ? 'Сохранить' : 'Создать'}
        </button>
      </div>
    </form>
  )
}
