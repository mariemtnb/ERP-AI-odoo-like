# Diagrams (PlantUML)

| File | Diagram |
|---|---|
| [erd.puml](erd.puml) | Entity–Association diagram (full database schema, grouped by domain) |
| [class-diagram.puml](class-diagram.puml) | Class diagram (models + service layer + exceptions) |
| [use-case.puml](use-case.puml) | Use case diagram (roles, 21 use cases, AI agent as secondary actor) |
| [sequence-agent.puml](sequence-agent.puml) | Sequence — agent write action with human approval |
| [sequence-sale-posting.puml](sequence-sale-posting.puml) | Sequence — sale confirmation: stock + double-entry books in one transaction |

## Rendering

- **VS Code**: install the *PlantUML* extension, open a file, `Alt+D` to preview.
- **Online**: paste the file content into <https://www.plantuml.com/plantuml>.
- **CLI** (PNG into this folder):

```bash
docker run --rm -v ./diagrams:/data plantuml/plantuml -tpng "/data/*.puml"
```

## Coverage

The diagrams reflect the full implemented system: identity & RBAC, catalog,
multi-warehouse inventory with an append-only movement ledger, partners, the
CRM pipeline, purchase/sale document lifecycles with hierarchical approval,
invoicing, **double-entry accounting**, RAG documents, and the AI assistant
with its audit trail.
