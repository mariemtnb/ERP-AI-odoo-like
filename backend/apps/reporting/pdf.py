"""HTML → PDF rendering with WeasyPrint."""
from django.template.loader import render_to_string
from weasyprint import HTML


def render_pdf(template: str, context: dict) -> bytes:
    html = render_to_string(template, context)
    return HTML(string=html).write_pdf()
