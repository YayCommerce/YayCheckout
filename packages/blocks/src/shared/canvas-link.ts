/** A preview link in the editor canvas takes the link styling but goes nowhere. */
export const preventCanvasNavigation = ( event: { preventDefault: () => void } ) => event.preventDefault();
