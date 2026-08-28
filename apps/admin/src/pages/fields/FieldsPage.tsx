import { __ } from '@wordpress/i18n';

import PagePlaceholder from '@/pages/PagePlaceholder';

export default function FieldsPage() {
  return <PagePlaceholder title={__('Fields', 'yaycheckout')} description={__('Manage checkout fields: add, edit, reorder and conditionally show.', 'yaycheckout')} />;
}
