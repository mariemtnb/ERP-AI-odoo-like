import os

import httpx
from rest_framework import mixins, status, viewsets
from rest_framework.permissions import IsAuthenticated
from rest_framework.response import Response
from rest_framework.throttling import ScopedRateThrottle
from rest_framework.views import APIView

from apps.core.models import AuditLog

from .models import Conversation, Message
from .serializers import ConversationSerializer

AI_SERVICE_URL = os.environ.get("AI_SERVICE_URL", "http://ai-service:8001")
# Local LLM on partially-offloaded weights can be slow; be generous.
AI_TIMEOUT = float(os.environ.get("AI_TIMEOUT", "600"))


class ConversationViewSet(
    mixins.ListModelMixin, mixins.RetrieveModelMixin,
    mixins.DestroyModelMixin, viewsets.GenericViewSet,
):
    serializer_class = ConversationSerializer
    permission_classes = [IsAuthenticated]

    def get_queryset(self):
        return Conversation.objects.filter(user=self.request.user).prefetch_related("messages")


class ChatView(APIView):
    """One agent turn. Body: {conversation_id?, message} or
    {conversation_id, approve: bool} to answer a pending confirmation."""

    permission_classes = [IsAuthenticated]
    throttle_classes = [ScopedRateThrottle]
    throttle_scope = "agent"

    def post(self, request):
        approve = request.data.get("approve")
        message = (request.data.get("message") or "").strip()
        conversation_id = request.data.get("conversation_id")

        if approve is None and not message:
            return Response({"detail": "message is required"}, status=400)

        if conversation_id:
            try:
                conversation = Conversation.objects.get(pk=conversation_id, user=request.user)
            except Conversation.DoesNotExist:
                return Response({"detail": "Conversation not found."}, status=404)
        else:
            conversation = Conversation.objects.create(
                user=request.user, title=message[:120]
            )

        token = request.META.get("HTTP_AUTHORIZATION", "").removeprefix("Bearer ")
        thread = f"conv-{conversation.pk}"

        if approve is None:
            Message.objects.create(
                conversation=conversation, role=Message.Role.USER, content=message
            )
            endpoint, payload = "/chat", {"thread_id": thread, "message": message}
        else:
            Message.objects.create(
                conversation=conversation, role=Message.Role.USER,
                content="✔ Approved" if approve else "✘ Rejected",
            )
            endpoint, payload = "/resume", {"thread_id": thread, "approved": bool(approve)}

        try:
            r = httpx.post(
                f"{AI_SERVICE_URL}{endpoint}", json=payload,
                headers={"Authorization": f"Bearer {token}"},
                timeout=AI_TIMEOUT,
            )
            r.raise_for_status()
            data = r.json()
        except httpx.HTTPError as exc:
            return Response(
                {"detail": f"AI service unavailable: {exc}"},
                status=status.HTTP_502_BAD_GATEWAY,
            )

        if data["type"] == "confirmation_required":
            reply = Message.objects.create(
                conversation=conversation, role=Message.Role.ASSISTANT,
                content="", pending_action=data["action"],
            )
        else:
            reply = Message.objects.create(
                conversation=conversation, role=Message.Role.ASSISTANT,
                content=data.get("reply", ""), tool_calls=data.get("tool_calls") or None,
            )
            for call in data.get("tool_calls") or []:
                AuditLog.objects.create(
                    user=request.user, actor=AuditLog.Actor.AGENT,
                    action=call["name"], payload=call["args"],
                )

        return Response({
            "conversation_id": conversation.pk,
            "type": data["type"],
            "message": {
                "id": reply.pk, "role": reply.role, "content": reply.content,
                "tool_calls": reply.tool_calls, "pending_action": reply.pending_action,
                "created_at": reply.created_at,
            },
        })
