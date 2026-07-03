"""Reusable RBAC permission classes.

Every module viewset declares its access rule with these classes so the
role matrix from the architecture document lives in exactly one place.
"""
from rest_framework.permissions import SAFE_METHODS, BasePermission


class IsAdmin(BasePermission):
    def has_permission(self, request, view):
        return bool(
            request.user.is_authenticated and request.user.role == "admin"
        )


class IsManagerOrAdmin(BasePermission):
    def has_permission(self, request, view):
        return bool(
            request.user.is_authenticated
            and request.user.role in ("admin", "manager")
        )


class ManagerWritesEmployeeReads(BasePermission):
    """Managers/admins get full access; employees are read-only.

    Default rule for products, stock, suppliers, purchases.
    """

    def has_permission(self, request, view):
        if not request.user.is_authenticated:
            return False
        if request.method in SAFE_METHODS:
            return True
        return request.user.role in ("admin", "manager")


class EmployeeCanCreate(BasePermission):
    """Like ManagerWritesEmployeeReads, but employees may also POST.

    Rule for customers and sales (employees record sales at the counter).
    """

    def has_permission(self, request, view):
        if not request.user.is_authenticated:
            return False
        if request.method in SAFE_METHODS or request.method == "POST":
            return True
        return request.user.role in ("admin", "manager")
