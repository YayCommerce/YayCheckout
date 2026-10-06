/** Label/value strip summarising one record (an import file, a created draft). */
export function SummaryStrip({ items }: { items: [string, string][] }) {
  return (
    <dl className="bg-muted/30 flex flex-wrap gap-x-8 gap-y-2 rounded-md border px-4 py-2.5 text-sm">
      {items.map(([label, value]) => (
        <div key={label}>
          <dt className="text-muted-foreground mb-1 text-xs">{label}</dt>
          <dd className="font-medium">{value}</dd>
        </div>
      ))}
    </dl>
  );
}
