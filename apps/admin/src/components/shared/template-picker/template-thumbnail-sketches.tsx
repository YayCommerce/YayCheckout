import type { ReactNode } from 'react';

import { Box, Dot, Header, Row } from './TemplateThumbnailPrimitives';

/** One 120×80 layout sketch per catalogue slug, drawn from the v1.1 template designs.
 * Brand colours (terracotta, PayPal yellow) are deliberately flattened to the accent token:
 * these sketch the layout, not the theme. */
export const SKETCHES: Record<string, ReactNode> = {
  'classic-two-column': (
    <>
      <Header />
      <Row x={8} y={16} w={24} h={3} tone="ink" />
      <Row x={8} y={22} w={66} h={6} tone="surface" />
      <Row x={8} y={31} w={32} h={6} tone="surface" />
      <Row x={42} y={31} w={32} h={6} tone="surface" />
      <Row x={8} y={40} w={66} h={6} tone="surface" />
      <Row x={8} y={49} w={66} h={6} tone="surface" />
      <Row x={8} y={58} w={32} h={6} tone="surface" />
      <Row x={42} y={58} w={32} h={6} tone="surface" />
      <Row x={8} y={67} w={66} h={6} tone="surface" />
      <Box x={80} y={16} w={32} h={40} />
      <Row x={84} y={21} w={14} h={2} tone="ink" />
      <Row x={84} y={27} w={24} h={2} />
      <Row x={84} y={32} w={24} h={2} />
      <Row x={84} y={40} w={24} h={0.75} />
      <Row x={84} y={46} w={10} h={3} tone="ink" />
      <Row x={100} y={46} w={8} h={3} tone="ink" />
    </>
  ),

  'split-express': (
    <>
      <Row x={0} y={0} w={48} h={80} tone="dark" rx={0} />
      <Row x={6} y={6} w={4} h={4} tone="light" />
      <Row x={12} y={7} w={12} h={2} tone="light" />
      <Row x={6} y={18} w={10} h={10} tone="light" />
      <Row x={19} y={20} w={16} h={2} tone="light" />
      <Row x={36} y={20} w={6} h={2} tone="light" />
      <Row x={6} y={32} w={10} h={10} tone="light" />
      <Row x={19} y={34} w={16} h={2} tone="light" />
      <Row x={36} y={34} w={6} h={2} tone="light" />
      <Row x={6} y={50} w={36} h={0.75} tone="light" />
      <Row x={6} y={56} w={12} h={2} tone="light" />
      <Row x={32} y={56} w={10} h={2} tone="light" />
      <Row x={6} y={68} w={10} h={3} tone="light" />
      <Row x={26} y={68} w={16} h={3} tone="light" />
      <Row x={54} y={8} w={18} h={7} tone="dark" />
      <Box x={74} y={8} w={18} h={7} />
      <Row x={94} y={8} w={18} h={7} tone="accent" />
      <Row x={54} y={19} w={58} h={0.75} />
      <Row x={54} y={24} w={58} h={6} tone="surface" />
      <Row x={54} y={33} w={28} h={6} tone="surface" />
      <Row x={84} y={33} w={28} h={6} tone="surface" />
      <Row x={54} y={42} w={58} h={6} tone="surface" />
      <Row x={54} y={51} w={58} h={6} tone="surface" />
      <Row x={54} y={60} w={18} h={6} tone="surface" />
      <Row x={74} y={60} w={18} h={6} tone="surface" />
      <Row x={94} y={60} w={18} h={6} tone="surface" />
      <Row x={54} y={69} w={58} h={6} tone="surface" />
    </>
  ),

  'editorial-fashion': (
    <>
      <Row x={0} y={0} w={54} h={80} tone="accent" rx={0} />
      <Row x={6} y={6} w={16} h={3} tone="light" />
      <Row x={6} y={16} w={30} h={7} tone="light" />
      <Row x={6} y={25} w={22} h={7} tone="light" />
      <Row x={6} y={40} w={9} h={9} tone="light" />
      <Row x={18} y={42} w={18} h={2} tone="light" />
      <Row x={40} y={42} w={8} h={2} tone="light" />
      <Row x={6} y={52} w={9} h={9} tone="light" />
      <Row x={18} y={54} w={18} h={2} tone="light" />
      <Row x={40} y={54} w={8} h={2} tone="light" />
      <Row x={6} y={64} w={9} h={9} tone="light" />
      <Row x={18} y={66} w={18} h={2} tone="light" />
      <Row x={40} y={66} w={8} h={2} tone="light" />
      <Row x={60} y={10} w={4} h={4} tone="accent" />
      <Row x={66} y={10} w={22} h={4} tone="ink" />
      <Row x={60} y={19} w={52} h={1} tone="ink" />
      <Row x={60} y={28} w={4} h={4} tone="accent" />
      <Row x={66} y={28} w={22} h={4} tone="ink" />
      <Row x={60} y={37} w={24} h={1} tone="ink" />
      <Row x={88} y={37} w={24} h={1} tone="ink" />
      <Row x={60} y={45} w={52} h={1} tone="ink" />
      <Row x={60} y={53} w={24} h={1} tone="ink" />
      <Row x={88} y={53} w={24} h={1} tone="ink" />
      <Row x={60} y={60} w={4} h={4} tone="accent" />
      <Row x={66} y={60} w={22} h={4} tone="ink" />
      <Dot cx={62} cy={70} on />
      <Row x={67} y={69} w={30} h={2} />
      <Dot cx={62} cy={77} />
      <Row x={67} y={76} w={30} h={2} />
    </>
  ),

  'multi-step-wizard': (
    <>
      <Header />
      <Box x={30} y={14} w={60} h={7} />
      <Row x={33} y={17} w={18} h={2} tone="accent" />
      <Row x={80} y={17} w={7} h={2} tone="ink" />
      <Row x={30} y={25} w={19} h={2} tone="accent" />
      <Row x={51} y={25} w={18} h={2} />
      <Row x={71} y={25} w={19} h={2} />
      <Box x={30} y={31} w={60} h={44} />
      <Row x={34} y={35} w={20} h={3} tone="ink" />
      <Row x={34} y={41} w={52} h={6} />
      <Row x={34} y={50} w={25} h={6} />
      <Row x={61} y={50} w={25} h={6} />
      <Row x={34} y={59} w={52} h={6} />
      <Row x={70} y={68} w={16} h={5} tone="accent" />
    </>
  ),

  'accordion-one-page': (
    <>
      <Header />
      <Row x={28} y={15} w={16} h={3} tone="ink" />
      <Box x={28} y={21} w={64} h={32} accent />
      <Dot cx={33} cy={26} on />
      <Row x={38} y={25} w={18} h={3} tone="ink" />
      <Row x={33} y={31} w={54} h={6} />
      <Row x={33} y={40} w={26} h={6} />
      <Row x={72} y={44} w={15} h={5} tone="accent" />
      <Box x={28} y={57} w={64} h={6} />
      <Dot cx={33} cy={60} r={2} />
      <Row x={38} y={59} w={20} h={2} />
      <Box x={28} y={65} w={64} h={6} />
      <Dot cx={33} cy={68} r={2} />
      <Row x={38} y={67} w={20} h={2} />
      <Box x={28} y={73} w={64} h={6} />
      <Dot cx={33} cy={76} r={2} />
      <Row x={38} y={75} w={20} h={2} />
    </>
  ),

  'upsell-cross-sell': (
    <>
      <Header />
      <Box x={8} y={14} w={62} h={13} />
      <Row x={12} y={17} w={16} h={2} tone="ink" />
      <Row x={12} y={21} w={54} h={4} />
      <Box x={8} y={30} w={62} h={13} />
      <Row x={12} y={33} w={16} h={2} tone="ink" />
      <Row x={12} y={37} w={26} h={4} />
      <Row x={40} y={37} w={26} h={4} />
      <Box x={8} y={46} w={62} h={13} />
      <Row x={12} y={49} w={16} h={2} tone="ink" />
      <Row x={12} y={53} w={54} h={4} />
      <Box x={8} y={62} w={62} h={13} dashed />
      <Row x={12} y={66} w={4} h={4} tone="surface" />
      <Row x={19} y={66} w={26} h={2} tone="ink" />
      <Row x={19} y={70} w={10} h={2} tone="accent" />
      <Box x={76} y={14} w={36} h={8} />
      <Row x={79} y={16} w={16} h={1.5} tone="ink" />
      <Row x={79} y={19} w={30} h={1.5} />
      <Row x={79} y={19} w={18} h={1.5} tone="accent" />
      <Box x={76} y={25} w={36} h={50} />
      <Row x={79} y={28} w={14} h={2} tone="ink" />
      <Row x={79} y={33} w={6} h={6} />
      <Row x={87} y={35} w={14} h={2} />
      <Row x={103} y={35} w={6} h={2} tone="ink" />
      <Row x={79} y={41} w={6} h={6} />
      <Row x={87} y={43} w={14} h={2} />
      <Row x={103} y={43} w={6} h={2} tone="ink" />
      <Row x={79} y={50} w={4} h={4} />
      <Row x={85} y={51} w={12} h={2} />
      <Row x={101} y={50} w={8} h={4} tone="accent" />
      <Row x={79} y={57} w={4} h={4} />
      <Row x={85} y={58} w={12} h={2} />
      <Row x={101} y={57} w={8} h={4} tone="accent" />
      <Row x={79} y={65} w={30} h={0.75} />
      <Row x={79} y={69} w={10} h={2} tone="ink" />
      <Row x={100} y={68} w={9} h={3} tone="ink" />
    </>
  ),

  'minimal-steps': (
    <>
      <Row x={0} y={0} w={120} h={80} tone="surface" rx={0} />
      <Row x={0} y={0} w={120} h={2} rx={0} />
      <Row x={0} y={0} w={60} h={2} tone="accent" rx={0} />
      <Row x={36} y={8} w={4} h={4} tone="accent" />
      <Row x={42} y={9} w={10} h={2} tone="ink" />
      <Row x={76} y={9} w={8} h={2} />
      <Row x={36} y={16} w={10} h={2} />
      <Row x={72} y={15} w={12} h={3} tone="ink" />
      <Row x={36} y={23} w={28} h={4} tone="ink" />
      <Box x={36} y={30} w={48} h={6} />
      <Box x={36} y={39} w={23} h={6} />
      <Box x={61} y={39} w={23} h={6} />
      <Box x={36} y={48} w={48} h={6} />
      <Box x={36} y={57} w={23} h={6} />
      <Box x={61} y={57} w={23} h={6} />
      <Row x={36} y={70} w={48} h={7} tone="accent" />
    </>
  ),

  'live-receipt': (
    <>
      <Header />
      <Row x={8} y={14} w={40} h={4} tone="ink" />
      <Row x={8} y={20} w={30} h={2} />
      <Dot cx={10} cy={27} on r={2} />
      <Row x={15} y={26} w={18} h={3} tone="ink" />
      <Row x={8} y={32} w={62} h={5} tone="surface" />
      <Dot cx={10} cy={41} on r={2} />
      <Row x={15} y={40} w={18} h={3} tone="ink" />
      <Row x={8} y={46} w={30} h={5} tone="surface" />
      <Row x={40} y={46} w={30} h={5} tone="surface" />
      <Row x={8} y={53} w={62} h={5} tone="surface" />
      <Dot cx={10} cy={62} on r={2} />
      <Row x={15} y={61} w={18} h={3} tone="ink" />
      <Box x={8} y={66} w={19} h={8} accent />
      <Box x={29} y={66} w={19} h={8} />
      <Box x={51} y={66} w={19} h={8} />
      <path
        d={`M76 14 H112 V63 ${'l-2 3 l-2 -3 '.repeat(9)}Z`}
        strokeWidth={0.75}
        className="fill-background stroke-border"
      />
      <Row x={88} y={18} w={12} h={2} tone="ink" />
      <Row x={80} y={24} w={20} h={1.5} />
      <Row x={80} y={27} w={14} h={1.5} />
      <Row x={80} y={32} w={20} h={1.5} />
      <Row x={80} y={35} w={24} h={1.5} />
      <Row x={80} y={38} w={16} h={1.5} />
      <Row x={80} y={42} w={28} h={0.75} />
      <Row x={80} y={45} w={28} h={1.5} />
      <Row x={80} y={48} w={28} h={1.5} />
      <Row x={80} y={53} w={10} h={2} tone="ink" />
      <Row x={100} y={53} w={8} h={2} tone="ink" />
      <Row x={86} y={57} w={1} h={4} tone="ink" rx={0} />
      <Row x={88} y={57} w={2} h={4} tone="ink" rx={0} />
      <Row x={91} y={57} w={1} h={4} tone="ink" rx={0} />
      <Row x={93} y={57} w={1} h={4} tone="ink" rx={0} />
      <Row x={95} y={57} w={2} h={4} tone="ink" rx={0} />
      <Row x={98} y={57} w={1} h={4} tone="ink" rx={0} />
      <Row x={100} y={57} w={1} h={4} tone="ink" rx={0} />
      <Row x={76} y={70} w={36} h={7} tone="accent" />
    </>
  ),

  'soft-steps': (
    <>
      <Row x={0} y={0} w={120} h={80} tone="surface" rx={0} />
      <Row x={10} y={4} w={14} h={3} tone="ink" />
      <Row x={52} y={4} w={3} h={3} tone="accent" rx={1.5} />
      <Row x={57} y={5} w={5} h={1} />
      <Row x={64} y={4} w={3} h={3} rx={1.5} />
      <Row x={69} y={5} w={5} h={1} />
      <Row x={76} y={4} w={3} h={3} rx={1.5} />
      <Row x={100} y={4} w={12} h={3} tone="ink" />
      <Row x={0} y={11} w={120} h={0.75} rx={0} />
      <Row x={34} y={17} w={36} h={5} tone="ink" />
      <Row x={34} y={24} w={26} h={2} />
      <Row x={34} y={29} w={52} h={6} rx={3} />
      <Row x={34} y={38} w={25} h={6} rx={3} />
      <Row x={61} y={38} w={25} h={6} rx={3} />
      <Row x={34} y={47} w={52} h={6} rx={3} />
      <Row x={34} y={56} w={15} h={6} rx={3} />
      <Row x={51} y={56} w={15} h={6} rx={3} />
      <Row x={68} y={56} w={18} h={6} rx={3} />
      <Row x={34} y={69} w={52} h={7} tone="accent" rx={3.5} />
    </>
  ),

  'material-stepper': (
    <>
      <Header />
      <Box x={6} y={14} w={70} h={60} />
      <Row x={10.6} y={21} w={0.75} h={47} rx={0} />
      <Dot cx={11} cy={21} on />
      <Row x={17} y={19} w={24} h={3} tone="ink" />
      <Row x={17} y={25} w={50} h={5} />
      <Row x={17} y={33} w={50} h={5} />
      <Row x={52} y={41} w={15} h={5} tone="accent" rx={2.5} />
      <Dot cx={11} cy={50} />
      <Row x={17} y={49} w={20} h={2} />
      <Dot cx={11} cy={59} />
      <Row x={17} y={58} w={20} h={2} />
      <Dot cx={11} cy={68} />
      <Row x={17} y={67} w={20} h={2} />
      <Box x={80} y={14} w={34} h={38} />
      <Row x={84} y={17} w={14} h={2} tone="ink" />
      <Row x={84} y={22} w={6} h={6} />
      <Row x={92} y={23} w={12} h={2} />
      <Row x={106} y={23} w={5} h={2} tone="ink" />
      <Row x={84} y={30} w={6} h={6} />
      <Row x={92} y={31} w={12} h={2} />
      <Row x={106} y={31} w={5} h={2} tone="ink" />
      <Row x={84} y={39} w={26} h={0.75} />
      <Row x={84} y={42} w={12} h={1.5} />
      <Row x={84} y={45} w={12} h={1.5} />
      <Row x={84} y={48} w={8} h={2} tone="ink" />
      <Row x={102} y={48} w={8} h={2} tone="ink" />
    </>
  ),

  'digital-product': (
    <>
      <Row x={0} y={0} w={120} h={80} tone="dark" rx={0} />
      <rect x={16} y={8} width={44} height={64} rx={2} className="fill-muted" />
      <Row x={58} y={8} w={46} h={64} tone="surface" rx={2} />
      <Row x={20} y={12} w={14} h={1.5} tone="ink" />
      <Row x={20} y={16} w={36} h={18} tone="accent" />
      <Row x={36} y={22} w={4} h={6} tone="light" />
      <Row x={20} y={37} w={24} h={3} tone="ink" />
      <Row x={20} y={42} w={30} h={1.5} />
      <Row x={20} y={45} w={22} h={1.5} />
      <Box x={20} y={49} w={36} h={6} />
      <Dot cx={23} cy={52} r={1.5} />
      <Row x={27} y={51} w={12} h={2} />
      <Row x={48} y={51} w={6} h={2} tone="ink" />
      <Box x={20} y={56} w={36} h={6} accent />
      <Dot cx={23} cy={59} r={1.5} on />
      <Row x={27} y={58} w={12} h={2} />
      <Row x={48} y={58} w={6} h={2} tone="ink" />
      <Box x={20} y={63} w={36} h={6} />
      <Dot cx={23} cy={66} r={1.5} />
      <Row x={27} y={65} w={12} h={2} />
      <Row x={48} y={65} w={6} h={2} tone="ink" />
      <Row x={62} y={12} w={14} h={3} tone="ink" />
      <Row x={62} y={17} w={18} h={6} tone="dark" />
      <Row x={82} y={17} w={18} h={6} tone="accent" />
      <Row x={62} y={26} w={38} h={5} />
      <Row x={62} y={34} w={38} h={5} />
      <Row x={62} y={41} w={19} h={5} />
      <Row x={83} y={41} w={17} h={5} />
      <Row x={62} y={48} w={38} h={5} />
      <Row x={62} y={55} w={38} h={5} />
      <Row x={62} y={63} w={38} h={6} tone="accent" />
    </>
  ),
};

/** Generic form for a slug the SPA does not know yet. */
export const FALLBACK: ReactNode = (
  <>
    <Header />
    <Row x={8} y={16} w={104} h={6} tone="surface" />
    <Row x={8} y={25} w={50} h={6} tone="surface" />
    <Row x={62} y={25} w={50} h={6} tone="surface" />
    <Row x={8} y={34} w={104} h={6} tone="surface" />
    <Row x={8} y={43} w={104} h={6} tone="surface" />
    <Row x={72} y={56} w={40} h={7} tone="accent" />
  </>
);
