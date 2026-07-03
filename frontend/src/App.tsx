import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Route, Routes } from "react-router-dom";
import Layout from "@/components/Layout";
import { AuthProvider } from "@/features/auth/AuthContext";
import LoginPage from "@/features/auth/LoginPage";
import { ProtectedRoute } from "@/features/auth/ProtectedRoute";
import UsersPage from "@/features/users/UsersPage";
import Placeholder from "@/pages/Placeholder";

const queryClient = new QueryClient();

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <BrowserRouter>
          <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route element={<ProtectedRoute />}>
              <Route element={<Layout />}>
                <Route index element={<Placeholder title="Dashboard" />} />
                <Route path="products" element={<Placeholder title="Products" />} />
                <Route path="inventory" element={<Placeholder title="Inventory" />} />
                <Route path="customers" element={<Placeholder title="Customers" />} />
                <Route path="suppliers" element={<Placeholder title="Suppliers" />} />
                <Route path="purchases" element={<Placeholder title="Purchases" />} />
                <Route path="sales" element={<Placeholder title="Sales" />} />
                <Route path="assistant" element={<Placeholder title="AI Assistant" />} />
                <Route element={<ProtectedRoute roles={["admin"]} />}>
                  <Route path="users" element={<UsersPage />} />
                </Route>
              </Route>
            </Route>
          </Routes>
        </BrowserRouter>
      </AuthProvider>
    </QueryClientProvider>
  );
}
