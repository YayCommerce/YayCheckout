import { NumberInput } from '@/components/ui/number-input';

/** An amount in the store's currency, typed the way Woo prints prices: symbol, position, decimals, separators. */
export function AmountInput({ value, onChange }: { value: number; onChange: (value: number) => void }) {
  const { symbol, position, decimals, decimal_separator, thousand_separator } = window.yayCheckoutAdmin.store.currency;
  const space = position.endsWith('_space') ? ' ' : '';

  return (
    <NumberInput
      className="min-w-0 flex-1"
      value={value}
      onValueChange={onChange}
      min={0}
      decimalScale={decimals}
      fixedDecimalScale
      decimalSeparator={decimal_separator}
      thousandSeparator={thousand_separator}
      prefix={position.startsWith('left') ? symbol + space : undefined}
      suffix={position.startsWith('right') ? space + symbol : undefined}
    />
  );
}
