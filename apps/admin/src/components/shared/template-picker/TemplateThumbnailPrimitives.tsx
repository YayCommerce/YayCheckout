import { cn } from '@/lib/utils';

type Tone = 'field' | 'ink' | 'accent' | 'surface' | 'dark' | 'light';

const TONE: Record<Tone, string> = {
  field: 'fill-muted-foreground-400',
  ink: 'fill-muted-foreground',
  accent: 'fill-primary',
  surface: 'fill-background',
  dark: 'fill-foreground/70',
  light: 'fill-background/60',
};

/** Rounded rect; every bar, field and panel in a sketch. */
export function Row({
  x,
  y,
  w,
  h = 4,
  tone = 'field',
  rx = 1,
}: {
  x: number;
  y: number;
  w: number;
  h?: number;
  tone?: Tone;
  rx?: number;
}) {
  return <rect x={x} y={y} width={w} height={h} rx={rx} className={TONE[tone]} />;
}

/** Outlined card; `accent` for the active step, `dashed` for an offer. */
export function Box({
  x,
  y,
  w,
  h,
  accent,
  dashed,
}: {
  x: number;
  y: number;
  w: number;
  h: number;
  accent?: boolean;
  dashed?: boolean;
}) {
  return (
    <rect
      x={x}
      y={y}
      width={w}
      height={h}
      rx={1.5}
      strokeWidth={accent ? 1 : 0.75}
      strokeDasharray={dashed ? '2 1.5' : undefined}
      className={cn('fill-background', accent ? 'stroke-primary' : 'stroke-border')}
    />
  );
}

/** Step marker or radio: filled accent when `on`, hollow otherwise. */
export function Dot({ cx, cy, on, r = 2.5 }: { cx: number; cy: number; on?: boolean; r?: number }) {
  return (
    <circle
      cx={cx}
      cy={cy}
      r={r}
      strokeWidth={0.75}
      className={on ? 'fill-primary stroke-primary' : 'fill-background stroke-muted-foreground-400'}
    />
  );
}

/** Storefront header: white bar, logo square, wordmark, hairline. */
export function Header() {
  return (
    <>
      <Row x={0} y={0} w={120} h={10} tone="surface" rx={0} />
      <Row x={8} y={3} w={4} h={4} tone="accent" />
      <Row x={14} y={4} w={12} h={2} tone="ink" />
      <Row x={0} y={10} w={120} h={0.75} tone="field" rx={0} />
    </>
  );
}
