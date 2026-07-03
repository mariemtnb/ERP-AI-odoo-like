from django.urls import include, path
from rest_framework.routers import DefaultRouter

from .views import ChatView, ConversationViewSet

router = DefaultRouter()
router.register("agent/conversations", ConversationViewSet, basename="conversations")

urlpatterns = [
    path("agent/chat/", ChatView.as_view(), name="agent-chat"),
    path("", include(router.urls)),
]
