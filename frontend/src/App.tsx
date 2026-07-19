import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Route, Routes } from "react-router-dom";
import Layout from "@/components/Layout";
import { ThemeProvider } from "@/lib/theme";
import { AuthProvider } from "@/features/auth/AuthContext";
import LoginPage from "@/features/auth/LoginPage";
import SignupPage from "@/features/auth/SignupPage";
import { ProtectedRoute } from "@/features/auth/ProtectedRoute";
import AccountingPage from "@/features/accounting/AccountingPage";
import InventoryPage from "@/features/inventory/InventoryPage";
import AssistantPage from "@/features/assistant/AssistantPage";
import CrmPage from "@/features/crm/CrmPage";
import DashboardPage from "@/features/dashboard/DashboardPage";
import LandingPage from "@/features/landing/LandingPage";
import DocumentsPage from "@/features/documents/DocumentsPage";
import ReportsPage from "@/features/reports/ReportsPage";
import PartnersPage from "@/features/partners/PartnersPage";
import ProductsPage from "@/features/products/ProductsPage";
import UsersPage from "@/features/users/UsersPage";

const queryClient = new QueryClient();

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider>
      <AuthProvider>
        <BrowserRouter>
          <Routes>
            <Route path="/welcome" element={<LandingPage />} />
            <Route path="/login" element={<LoginPage />} />
            <Route path="/signup" element={<SignupPage />} />
            <Route element={<ProtectedRoute />}>
              <Route element={<Layout />}>
                <Route index element={<DashboardPage />} />
                <Route element={<ProtectedRoute roles={["admin", "manager"]} />}>
                  <Route path="reports" element={<ReportsPage />} />
                  <Route path="accounting" element={<AccountingPage />} />
                </Route>
                <Route path="products" element={<ProductsPage />} />
                <Route path="inventory" element={<InventoryPage />} />
                <Route
                  path="customers"
                  element={<PartnersPage kind="customers" title="Customers" />}
                />
                <Route
                  path="suppliers"
                  element={<PartnersPage kind="suppliers" title="Suppliers" />}
                />
                <Route
                  path="purchases"
                  element={<DocumentsPage kind="purchases" title="Purchases" />}
                />
                <Route
                  path="sales"
                  element={<DocumentsPage kind="sales" title="Sales" />}
                />
                <Route path="crm" element={<CrmPage />} />
                <Route path="assistant" element={<AssistantPage />} />
                <Route element={<ProtectedRoute roles={["admin"]} />}>
                  <Route path="users" element={<UsersPage />} />
                </Route>
              </Route>
            </Route>
          </Routes>
        </BrowserRouter>
      </AuthProvider>
      </ThemeProvider>
    </QueryClientProvider>
  );
}
