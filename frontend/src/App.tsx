import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Route, Routes } from "react-router-dom";
import Layout from "@/components/Layout";
import { ThemeProvider } from "@/lib/theme";
import { SessionProvider } from "@/lib/session";
import { AuthProvider } from "@/features/auth/AuthContext";
import LoginPage from "@/features/auth/LoginPage";
import SignupPage from "@/features/auth/SignupPage";
import { ProtectedRoute } from "@/features/auth/ProtectedRoute";
import AccountingPage from "@/features/accounting/AccountingPage";
import AdministrationPage from "@/features/admin/AdministrationPage";
import BankingPage from "@/features/treasury/BankingPage";
import InstallmentsPage from "@/features/treasury/InstallmentsPage";
import InstrumentsPage from "@/features/treasury/InstrumentsPage";
import LocalizationPage from "@/features/settings/LocalizationPage";
import ReconciliationPage from "@/features/treasury/ReconciliationPage";
import OwnerProfitPage from "@/features/owner/OwnerProfitPage";
import PayrollPage from "@/features/payroll/PayrollPage";
import InventoryPage from "@/features/inventory/InventoryPage";
import AssistantPage from "@/features/assistant/AssistantPage";
import CrmPage from "@/features/crm/CrmPage";
import PosPage from "@/features/pos/PosPage";
import ReturnsPage from "@/features/returns/ReturnsPage";
import LotsPage from "@/features/lots/LotsPage";
import ReorderPage from "@/features/reorder/ReorderPage";
import CurrencyPage from "@/features/currency/CurrencyPage";
import HrPage from "@/features/hr/HrPage";
import AssetsPage from "@/features/assets/AssetsPage";
import ManufacturingPage from "@/features/manufacturing/ManufacturingPage";
import ProjectsPage from "@/features/projects/ProjectsPage";
import RfqPage from "@/features/procurement/RfqPage";
import HelpdeskPage from "@/features/helpdesk/HelpdeskPage";
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
        {/* Effective permissions + enabled modules, so the shell can hide
            what this user cannot reach. The backend still enforces both. */}
        <SessionProvider>
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
                  <Route path="instruments" element={<InstrumentsPage />} />
                  <Route path="installments" element={<InstallmentsPage />} />
                  <Route path="banking" element={<BankingPage />} />
                  <Route path="reconciliation" element={<ReconciliationPage />} />
                  <Route path="owner" element={<OwnerProfitPage />} />
                  <Route path="payroll" element={<PayrollPage />} />
                  <Route path="hr" element={<HrPage />} />
                  <Route path="assets" element={<AssetsPage />} />
                  <Route path="returns" element={<ReturnsPage />} />
                  <Route path="lots" element={<LotsPage />} />
                  <Route path="reordering" element={<ReorderPage />} />
                  <Route path="rfqs" element={<RfqPage />} />
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
                <Route path="pos" element={<PosPage />} />
                <Route element={<ProtectedRoute roles={["admin", "manager"]} />}>
                  <Route path="manufacturing" element={<ManufacturingPage />} />
                </Route>
                <Route path="crm" element={<CrmPage />} />
                <Route path="projects" element={<ProjectsPage />} />
                <Route path="helpdesk" element={<HelpdeskPage />} />
                <Route path="assistant" element={<AssistantPage />} />
                <Route element={<ProtectedRoute roles={["admin"]} />}>
                  <Route path="users" element={<UsersPage />} />
                  <Route path="settings/localization" element={<LocalizationPage />} />
                  <Route path="settings/currencies" element={<CurrencyPage />} />
                  <Route path="settings/administration" element={<AdministrationPage />} />
                </Route>
              </Route>
            </Route>
          </Routes>
        </BrowserRouter>
        </SessionProvider>
      </AuthProvider>
      </ThemeProvider>
    </QueryClientProvider>
  );
}
