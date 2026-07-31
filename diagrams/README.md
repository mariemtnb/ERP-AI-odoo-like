# Diagrams (PlantUML)

Start with **overview** — it explains the whole system on one page. The others
add detail when you need it.

| File | What it shows | Read it when |
|---|---|---|
| [overview.puml](overview.puml) | The five pieces and how they talk to each other | You want the system in 30 seconds |
| [use-case.puml](use-case.puml) | Who can do what, grouped by role | Explaining permissions |
| [erd.puml](erd.puml) | The database, grouped by domain | Working on the schema |
| [class-diagram.puml](class-diagram.puml) | Where the business rules live | Working on the backend |
| [sequence-sale-posting.puml](sequence-sale-posting.puml) | What happens when a sale is confirmed | Explaining stock + accounting |
| [sequence-agent.puml](sequence-agent.puml) | How the AI asks before changing data | Explaining the assistant |

## A note on what these deliberately leave out

These diagrams are meant to be read, including from the back of a room, so
they show **structure rather than every detail**:

- The ERD lists only the columns that explain the model. Every table also has
  `id`, `created_at` and `updated_at`, and join tables are omitted.
- The class diagram shows services, not models. Models are plain data; every
  rule that matters lives in a service.
- Links that are logical rather than foreign keys (a sale causing a stock
  movement, via `reference_type` + `reference_id`) are written as a note
  instead of drawn — drawing them turned the ERD into spaghetti.

For the exact schema, read the migrations in `backend/database/migrations`.

## Rendering

- **VS Code**: install the *PlantUML* extension, open a file, `Alt+D` to preview.
- **Online**: paste the file content into <https://www.plantuml.com/plantuml>.
- **CLI** (regenerates every PNG in this folder):

```bash
java -jar plantuml.jar -charset UTF-8 -tpng diagrams/*.puml
```

`-charset UTF-8` matters — without it accented characters and dashes come out
as mojibake.

## Coverage

The diagrams cover the whole implemented system: company structure and
permissions, catalog and multi-warehouse stock, partners and CRM, purchase and
sale lifecycles, double-entry accounting, the Tunisian treasury layer
(cheques, traites/kembyelet, instalments), banking and reconciliation, the
audit trail, and the AI assistant.
