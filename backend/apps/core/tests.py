"""RBAC matrix tests: one place asserting who may do what, per module.

Mirrors the permission matrix in docs/ARCHITECTURE.md §3.
"""
from rest_framework import status
from rest_framework.test import APITestCase

from apps.accounts.models import User


class RBACMatrixTests(APITestCase):
    @classmethod
    def setUpTestData(cls):
        cls.admin = User.objects.create_user(
            email="a@t.t", password="x", role="admin"
        )
        cls.manager = User.objects.create_user(
            email="m@t.t", password="x", role="manager"
        )
        cls.employee = User.objects.create_user(
            email="e@t.t", password="x", role="employee"
        )

    def _post(self, user, url, payload):
        self.client.force_authenticate(user)
        return self.client.post(url, payload, format="json")

    def _get(self, user, url):
        self.client.force_authenticate(user)
        return self.client.get(url)

    # --- users module: admin only ---
    def test_users_admin_only(self):
        self.assertEqual(self._get(self.admin, "/api/v1/users/").status_code, 200)
        self.assertEqual(self._get(self.manager, "/api/v1/users/").status_code, 403)
        self.assertEqual(self._get(self.employee, "/api/v1/users/").status_code, 403)

    # --- products: manager writes, employee reads ---
    def test_products_matrix(self):
        payload = {"sku": "T-1", "name": "T"}
        self.assertEqual(
            self._post(self.manager, "/api/v1/products/", payload).status_code,
            status.HTTP_201_CREATED,
        )
        self.assertEqual(
            self._post(self.employee, "/api/v1/products/", {"sku": "T-2", "name": "T"}).status_code,
            403,
        )
        self.assertEqual(self._get(self.employee, "/api/v1/products/").status_code, 200)

    # --- stock movements: manager writes, employee reads ---
    def test_stock_movements_matrix(self):
        self.assertEqual(
            self._get(self.employee, "/api/v1/stock/movements/").status_code, 200
        )
        self.assertEqual(
            self._post(self.employee, "/api/v1/stock/movements/", {}).status_code, 403
        )

    # --- customers: employees may create, not modify ---
    def test_customers_matrix(self):
        r = self._post(self.employee, "/api/v1/customers/", {"name": "Walk-in"})
        self.assertEqual(r.status_code, status.HTTP_201_CREATED)
        cid = r.data["id"]
        self.assertEqual(
            self.client.patch(f"/api/v1/customers/{cid}/", {"name": "X"}).status_code,
            403,
        )
        self.assertEqual(
            self._post(self.manager, "/api/v1/customers/", {"name": "Corp"}).status_code,
            201,
        )

    # --- suppliers: employees read-only ---
    def test_suppliers_matrix(self):
        self.assertEqual(
            self._post(self.employee, "/api/v1/suppliers/", {"name": "S"}).status_code,
            403,
        )
        self.assertEqual(
            self._post(self.manager, "/api/v1/suppliers/", {"name": "S"}).status_code,
            201,
        )
        self.assertEqual(self._get(self.employee, "/api/v1/suppliers/").status_code, 200)

    # --- anonymous: everything is locked ---
    def test_anonymous_denied(self):
        self.client.force_authenticate(None)
        for url in ["/api/v1/products/", "/api/v1/customers/", "/api/v1/users/"]:
            self.assertEqual(self.client.get(url).status_code, 401)
