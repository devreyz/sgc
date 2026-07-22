import "./bootstrap";
import "./pwa-notifications";
import { Passkeys } from '@laravel/passkeys';

window.SgcPasskeys = Passkeys;
window.dispatchEvent(new CustomEvent('sgc:passkeys-ready'));
