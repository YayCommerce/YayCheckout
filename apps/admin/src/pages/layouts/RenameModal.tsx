import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { toast } from 'sonner';
import { __ } from '@wordpress/i18n';

import { getErrorMsg } from '@/lib/helpers/response.helper';
import { useRenameLayoutMutation } from '@/lib/queries/layout.queries';
import { renameLayoutFormSchema, type LayoutSummary, type RenameLayoutForm } from '@/lib/schema/layout.schema';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Form } from '@/components/ui/form';
import { NameField } from '@/pages/layouts/new-layout/NameField';

/**
 * Renames a layout from the listed tab's ⋯ menu. The form lives inside DialogContent:
 * Radix unmounts it on close, so each open starts from the row's current title.
 */
export function RenameModal({
  layout,
  onOpenChange,
}: {
  layout: LayoutSummary | null;
  onOpenChange: (open: boolean) => void;
}) {
  return (
    <Dialog open={null !== layout} onOpenChange={onOpenChange}>
      <DialogContent>{layout && <RenameForm layout={layout} onClose={() => onOpenChange(false)} />}</DialogContent>
    </Dialog>
  );
}

function RenameForm({ layout, onClose }: { layout: LayoutSummary; onClose: () => void }) {
  const form = useForm<RenameLayoutForm>({
    resolver: zodResolver(renameLayoutFormSchema),
    defaultValues: { name: layout.title },
  });

  const mutation = useRenameLayoutMutation();
  const rootError = form.formState.errors.root?.message;

  async function onValid(values: RenameLayoutForm) {
    try {
      await mutation.mutateAsync({ id: layout.id, name: values.name });
      toast.success(__('Layout renamed.', 'yaycheckout'));
      onClose();
    } catch (error) {
      form.setError('root', { type: 'server', message: await getErrorMsg(error) });
    }
  }

  return (
    <>
      <DialogHeader>
        <DialogTitle>{__('Rename layout', 'yaycheckout')}</DialogTitle>
      </DialogHeader>

      <Form {...form}>
        <form onSubmit={form.handleSubmit(onValid)} className="space-y-4">
          <NameField />

          {rootError && <p className="text-destructive text-sm">{rootError}</p>}

          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose} disabled={mutation.isPending}>
              {__('Cancel', 'yaycheckout')}
            </Button>
            <Button type="submit" disabled={mutation.isPending}>
              {mutation.isPending ? __('Saving…', 'yaycheckout') : __('Save', 'yaycheckout')}
            </Button>
          </DialogFooter>
        </form>
      </Form>
    </>
  );
}
