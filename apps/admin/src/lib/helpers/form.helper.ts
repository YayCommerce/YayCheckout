/** react-hook-form's dirtyFields tree → number of leaves flagged true. */
export function countDirtyLeaves(value: unknown): number {
  if (value === true) return 1;
  if (value && typeof value === 'object') {
    return Object.values(value).reduce<number>((total, child) => total + countDirtyLeaves(child), 0);
  }
  return 0;
}
