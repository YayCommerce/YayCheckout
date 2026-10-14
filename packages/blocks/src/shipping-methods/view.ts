/**
 * Storefront script for yaycheckout/shipping-methods. The shared helper owns the whole-item click.
 */

import { onDomReady } from '../shared/dom-ready';
import { bindMethodItemClick } from '../shared/method-item-click';

onDomReady( bindMethodItemClick );
