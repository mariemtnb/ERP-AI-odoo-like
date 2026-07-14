**KpiCard** — the signature dashboard metric tile: eyebrow label, large tabular value, delta chip, optional sparkline.

```jsx
<KpiCard label="Revenue" value="48,250" unit="TND" delta={12.4} spark={[3,5,4,7,6,9]} icon={<TrendIcon/>} />
```
Negative `delta` flips the chip and sparkline to rose.
