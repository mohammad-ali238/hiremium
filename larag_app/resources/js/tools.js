import './bootstrap';
import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';

/*
| Public Free Tools bundle
| --------------------------------------------------------------------------
| Marketing pages load app.js, which starts its OWN Alpine and never loads
| Livewire. That is fine for plain Blade pages, but a Livewire component on
| that layout ends up with two Alpine instances — and the one app.js started
| has no Livewire magics, so every `$wire` inside an x-data (the tags input,
| for one) throws "$wire is not defined".
|
| Tool pages load THIS bundle instead: Livewire brings its own Alpine and we
| start it ourselves, exactly like the dashboard does. Both tool layouts use
| @livewireScriptConfig so Livewire does not also auto-inject a second copy.
*/
Livewire.start();
