from django.db import models


class AuditLog(models.Model):
    """Trace of actions executed through the AI agent (and other sensitive ops)."""

    class Actor(models.TextChoices):
        USER = "user", "User"
        AGENT = "agent", "AI agent"

    user = models.ForeignKey(
        "accounts.User", on_delete=models.PROTECT, related_name="audit_entries"
    )
    actor = models.CharField(max_length=10, choices=Actor.choices)
    action = models.CharField(max_length=60)
    payload = models.JSONField(null=True, blank=True)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        ordering = ["-created_at"]


class TimeStampedModel(models.Model):
    """Abstract base: created/updated timestamps for all business entities."""

    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        abstract = True
