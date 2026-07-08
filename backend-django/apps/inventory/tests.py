from decimal import Decimal

from django.test import TestCase

from apps.accounts.models import User
from apps.catalog.models import Product

from .models import StockMovement
from .services import InsufficientStock, record_movement


class StockServiceTests(TestCase):
    def setUp(self):
        self.user = User.objects.create_user(email="t@t.t", password="x")
        self.product = Product.objects.create(sku="TEST-1", name="Test product")

    def _move(self, mtype, qty):
        return record_movement(
            product_id=self.product.pk,
            movement_type=mtype,
            quantity=Decimal(qty),
            user=self.user,
        )

    def refresh(self):
        self.product.refresh_from_db()
        return self.product.quantity_in_stock

    def test_stock_in_increases_quantity(self):
        self._move(StockMovement.Type.IN, "10")
        self.assertEqual(self.refresh(), Decimal("10"))

    def test_stock_out_decreases_quantity(self):
        self._move(StockMovement.Type.IN, "10")
        self._move(StockMovement.Type.OUT, "4")
        self.assertEqual(self.refresh(), Decimal("6"))

    def test_out_more_than_available_raises_and_rolls_back(self):
        self._move(StockMovement.Type.IN, "5")
        with self.assertRaises(InsufficientStock):
            self._move(StockMovement.Type.OUT, "6")
        self.assertEqual(self.refresh(), Decimal("5"))
        self.assertEqual(StockMovement.objects.count(), 1)

    def test_adjustment_accepts_signed_delta(self):
        self._move(StockMovement.Type.IN, "10")
        self._move(StockMovement.Type.ADJUSTMENT, "-3")
        self.assertEqual(self.refresh(), Decimal("7"))

    def test_negative_adjustment_below_zero_rejected(self):
        with self.assertRaises(InsufficientStock):
            self._move(StockMovement.Type.ADJUSTMENT, "-1")

    def test_in_out_require_positive_quantity(self):
        with self.assertRaises(ValueError):
            self._move(StockMovement.Type.IN, "-5")

    def test_ledger_sum_matches_cached_quantity(self):
        self._move(StockMovement.Type.IN, "10")
        self._move(StockMovement.Type.OUT, "2")
        self._move(StockMovement.Type.ADJUSTMENT, "1.5")
        total = Decimal("0")
        for m in StockMovement.objects.all():
            if m.movement_type == StockMovement.Type.IN:
                total += m.quantity
            elif m.movement_type == StockMovement.Type.OUT:
                total -= m.quantity
            else:
                total += m.quantity
        self.assertEqual(self.refresh(), total)
