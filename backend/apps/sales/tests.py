from datetime import date
from decimal import Decimal

from django.test import TestCase

from apps.accounts.models import User
from apps.catalog.models import Product
from apps.inventory.models import StockMovement
from apps.inventory.services import InsufficientStock, record_movement
from apps.partners.models import Customer, Supplier
from apps.purchasing import services as po_services
from apps.purchasing.models import PurchaseOrder, PurchaseOrderLine
from apps.sales import services as sale_services
from apps.sales.models import Sale, SaleLine


class DocumentFlowTests(TestCase):
    def setUp(self):
        self.user = User.objects.create_user(email="t@t.t", password="x", role="manager")
        self.product = Product.objects.create(sku="P-1", name="P", sale_price=10, cost_price=6)
        self.customer = Customer.objects.create(name="C")
        self.supplier = Supplier.objects.create(name="S")

    def _stock(self):
        self.product.refresh_from_db()
        return self.product.quantity_in_stock

    def _make_po(self, qty="10"):
        po = PurchaseOrder.objects.create(
            number=po_services.next_number(), supplier=self.supplier,
            order_date=date.today(), created_by=self.user,
        )
        PurchaseOrderLine.objects.create(
            purchase_order=po, product=self.product,
            quantity=Decimal(qty), unit_price=Decimal("6"),
        )
        po.recompute_total()
        return po

    def _make_sale(self, qty="4"):
        sale = Sale.objects.create(
            number=sale_services.next_number(), customer=self.customer,
            sale_date=date.today(), created_by=self.user,
        )
        SaleLine.objects.create(
            sale=sale, product=self.product,
            quantity=Decimal(qty), unit_price=Decimal("10"),
        )
        sale.recompute_total()
        return sale

    def test_receive_purchase_creates_stock_in(self):
        po = self._make_po("10")
        po_services.confirm(po)
        po_services.receive(po, self.user)
        self.assertEqual(self._stock(), Decimal("10"))
        movement = StockMovement.objects.get(reference_type="purchase", reference_id=po.pk)
        self.assertEqual(movement.movement_type, StockMovement.Type.IN)
        self.assertEqual(po.total_amount, Decimal("60"))

    def test_receive_requires_confirmed(self):
        po = self._make_po()
        with self.assertRaises(po_services.InvalidTransition):
            po_services.receive(po, self.user)

    def test_confirm_sale_decrements_stock(self):
        record_movement(product_id=self.product.pk, movement_type="in",
                        quantity=Decimal("10"), user=self.user)
        sale = self._make_sale("4")
        sale_services.confirm(sale, self.user)
        self.assertEqual(self._stock(), Decimal("6"))
        self.assertEqual(sale.total_amount, Decimal("40"))

    def test_confirm_sale_insufficient_stock_rolls_back(self):
        record_movement(product_id=self.product.pk, movement_type="in",
                        quantity=Decimal("2"), user=self.user)
        sale = self._make_sale("4")
        with self.assertRaises(InsufficientStock):
            sale_services.confirm(sale, self.user)
        sale.refresh_from_db()
        self.assertEqual(sale.status, Sale.Status.DRAFT)
        self.assertEqual(self._stock(), Decimal("2"))
        self.assertFalse(
            StockMovement.objects.filter(reference_type="sale", reference_id=sale.pk).exists()
        )

    def test_cancel_confirmed_sale_restores_stock(self):
        record_movement(product_id=self.product.pk, movement_type="in",
                        quantity=Decimal("10"), user=self.user)
        sale = self._make_sale("4")
        sale_services.confirm(sale, self.user)
        sale_services.cancel(sale, user=self.user)
        self.assertEqual(self._stock(), Decimal("10"))
        self.assertEqual(sale.status, Sale.Status.CANCELLED)

    def test_document_numbers_sequence(self):
        po1, po2 = self._make_po(), self._make_po()
        self.assertNotEqual(po1.number, po2.number)
        self.assertTrue(po1.number.startswith("PO-"))
        sale = self._make_sale()
        self.assertTrue(sale.number.startswith("SO-"))
