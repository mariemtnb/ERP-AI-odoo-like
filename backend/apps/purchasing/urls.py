from rest_framework.routers import DefaultRouter

from .views import PurchaseOrderViewSet

router = DefaultRouter()
router.register("purchases", PurchaseOrderViewSet, basename="purchases")

urlpatterns = router.urls
