# Diagrams (PlantUML)

| File | Diagram |
|---|---|
| [erd.puml](erd.puml) | Entity–Association diagram (database schema) |
| [class-diagram.puml](class-diagram.puml) | Class diagram (models + service layer) |
| [use-case.puml](use-case.puml) | Use case diagram (roles + AI agent) |
| [sequence-agent.puml](sequence-agent.puml) | Sequence diagram — agent write action with confirmation |

## Rendering

- **VS Code**: install the *PlantUML* extension, open a file, `Alt+D` to preview.
- **Online**: paste the file content into <https://www.plantuml.com/plantuml>.
- **CLI** (PNG/SVG into this folder):

```bash
docker run --rm -v ./diagrams:/data plantuml/plantuml -tpng "/data/*.puml"
```
