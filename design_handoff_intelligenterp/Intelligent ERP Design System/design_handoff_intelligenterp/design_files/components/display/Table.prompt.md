**Table** — data table with sticky header, uppercase column labels, and soft row hover.

```jsx
<Table>
  <THead><Tr><Th>SKU</Th><Th align="right">Stock</Th></Tr></THead>
  <TBody>
    <Tr><Td mono>SKU-001</Td><Td align="right">42</Td></Tr>
  </TBody>
</Table>
```
Use `mono` on `Td` for SKUs/codes and `align="right"` for numeric columns.
