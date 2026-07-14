**Dialog** — centered modal with a blurred backdrop and spring entrance. Controlled.

```jsx
<Dialog open={open} onClose={close} title="New product" description="Add an item to the catalog">
  <ProductForm/>
</Dialog>
```
Backdrop click closes; content click is stopped.
