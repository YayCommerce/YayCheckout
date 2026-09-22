import { NumberInputInput, NumberInputRoot, NumberInputUnit } from '@/components/ui/number-input';

/** The small "N px" stepper every radius and size token uses. */
export function PxInput({
  value,
  onChange,
  range,
  disabled,
}: {
  value: number;
  onChange: (value: number) => void;
  range: { min: number; max: number };
  disabled?: boolean;
}) {
  return (
    <NumberInputRoot
      value={value}
      onValueChange={onChange}
      min={range.min}
      max={range.max}
      size="small"
      disabled={disabled}
      className="w-28"
    >
      <NumberInputInput />
      <NumberInputUnit unit="px" />
    </NumberInputRoot>
  );
}
