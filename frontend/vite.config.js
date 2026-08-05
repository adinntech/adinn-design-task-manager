import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig(({ mode }) => {
  // For production build, use subfolder
  // For development (local), use root
  const base = mode === 'production' ? '/Adinn_design_task/' : '/'
  
  return {
    plugins: [react()],
    base: base,
    server: {
      port: 5173,
    }
  }
})