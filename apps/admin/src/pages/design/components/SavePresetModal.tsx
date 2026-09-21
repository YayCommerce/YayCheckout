import { zodResolver } from '@hookform/resolvers/zod';
import { useForm } from 'react-hook-form';
import { z } from 'zod';
import { __ } from '@wordpress/i18n';

import { getErrorMsg } from '@/lib/helpers/response.helper';
import { useSaveDesignPresetMutation } from '@/lib/queries/design.queries';
import type { DesignResponse, DesignTokens } from '@/lib/schema/design.schema';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';

// Mirrors Design::PRESET_NAME_MAX_CHARS.
const formSchema = z.object({
  name: z.string().trim().min(1, __('Name is required', 'yaycheckout')).max(60),
});

/** Names and stores the tokens currently in the editor as a merchant preset. */
export function SavePresetModal({
  open,
  tokens,
  onOpenChange,
  onSaved,
}: {
  open: boolean;
  tokens: DesignTokens;
  onOpenChange: (open: boolean) => void;
  onSaved: (saved: DesignResponse, presetId: string) => void;
}) {
  const form = useForm<z.infer<typeof formSchema>>({ resolver: zodResolver(formSchema), defaultValues: { name: '' } });
  const mutation = useSaveDesignPresetMutation();

  async function onSubmit({ name }: z.infer<typeof formSchema>) {
    try {
      const saved = await mutation.mutateAsync({ name, tokens });
      // The new preset is the last one; PHP appends.
      onSaved(saved, saved.presets[saved.presets.length - 1].id);
      form.reset();
      onOpenChange(false);
    } catch (error) {
      form.setError('root', { type: 'server', message: await getErrorMsg(error) });
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{__('Save current as preset', 'yaycheckout')}</DialogTitle>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4">
            <FormField
              control={form.control}
              name="name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{__('Name', 'yaycheckout')}</FormLabel>
                  <FormControl>
                    <Input {...field} autoFocus />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            {form.formState.errors.root && (
              <p className="text-destructive text-sm">{form.formState.errors.root.message}</p>
            )}

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                {__('Cancel', 'yaycheckout')}
              </Button>
              <Button type="submit" disabled={mutation.isPending}>
                {mutation.isPending ? __('Saving…', 'yaycheckout') : __('Save preset', 'yaycheckout')}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
