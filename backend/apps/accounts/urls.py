from django.urls import path
from rest_framework_simplejwt.views import (
    TokenBlacklistView,
    TokenObtainPairView,
    TokenRefreshView,
)

from .views import MeView

urlpatterns = [
    path("login/", TokenObtainPairView.as_view(), name="login"),
    path("refresh/", TokenRefreshView.as_view(), name="refresh"),
    path("logout/", TokenBlacklistView.as_view(), name="logout"),
    path("me/", MeView.as_view(), name="me"),
]
