import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import { QueryClientProvider } from '@tanstack/react-query'
import { Toaster } from 'react-hot-toast'
import { queryClient } from './api/queryClient'
import { DataProvider, AuthProvider } from './data/context'
import './index.css'
import App from './App'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <AuthProvider>
          <DataProvider>
            <App />
            <Toaster
              position="top-right"
              toastOptions={{
                duration: 4000,
                error: { duration: 4000 },
                success: { duration: 2000 },
              }}
            />
          </DataProvider>
        </AuthProvider>
      </BrowserRouter>
    </QueryClientProvider>
  </StrictMode>
)
