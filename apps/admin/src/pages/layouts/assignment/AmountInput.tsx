import { useState } from 'react';
import { NumericFormat } from 'react-number-format';

import { InputGroupInput } from '@/components/ui/input-group';
import { NumberInputChevrons, NumberInputRoot, useNumberInputContext } from '@/components/ui/number-input';

type Currency = typeof window.yayCheckoutAdmin.store.currency;

/**
 * An amount in the store's currency, typed the way Woo prints prices: symbol, position, decimals, separators.
 * NumberInput's root and chevrons, but its own field: NumberInputInput snaps an emptied field back to "0", so
 * the next digits land beside that 0 ("100" becomes "1" or "1.000"). Here the field may stay empty while typing.
 */
export function AmountInput({ value, onChange }: { value: number; onChange: (value: number) => void }) {
  return (
    <NumberInputRoot className="min-w-0 flex-1" value={value} onValueChange={onChange} min={0}>
      <AmountField currency={window.yayCheckoutAdmin.store.currency} />
      <NumberInputChevrons />
    </NumberInputRoot>
  );
}

function AmountField({ currency }: { currency: Currency }) {
  const { value, setValue, handleIncrement, handleDecrement } = useNumberInputContext();
  const { symbol, position, decimals, decimal_separator, thousand_separator } = currency;
  const space = position.endsWith('_space') ? ' ' : '';
  // Emptied while typing; counts as 0. A chevron step moves value off 0, which shows it again.
  const [empty, setEmpty] = useState(false);

  return (
    <NumericFormat
      customInput={InputGroupInput}
      className="peer/number-input-input"
      value={empty && value === 0 ? '' : value}
      onValueChange={({ floatValue }) => {
        setEmpty(floatValue === undefined);
        setValue(floatValue ?? 0);
      }}
      onKeyDown={(event) => {
        if (event.key === 'ArrowUp') handleIncrement();
        if (event.key === 'ArrowDown') handleDecrement();
      }}
      // Left empty: show the 0 it counts as.
      onBlur={() => setEmpty(false)}
      allowNegative={false}
      decimalScale={decimals}
      fixedDecimalScale
      decimalSeparator={decimal_separator}
      thousandSeparator={thousand_separator}
      prefix={position.startsWith('left') ? symbol + space : undefined}
      suffix={position.startsWith('right') ? space + symbol : undefined}
    />
  );
}
