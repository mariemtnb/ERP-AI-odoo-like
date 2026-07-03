from rest_framework.routers import DefaultRouter

from .views import CustomerViewSet, SupplierViewSet

router = DefaultRouter()
router.register("customers", CustomerViewSet, basename="customers")
router.register("suppliers", SupplierViewSet, basename="suppliers")

urlpatterns = router.urls
