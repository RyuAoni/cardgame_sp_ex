import { defineConfig } from "vite";
import react from "@vitejs/plugin-react-swc";

export default defineConfig({
  plugins: [react()],
  server: { port: 5173 },
  // サブディレクトリ配信にするなら base: '/dist/' とかに変更
  // base: '/',
});
