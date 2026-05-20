import './bootstrap';
import './push';

import Alpine from 'alpinejs';
import sort from '@alpinejs/sort';

Alpine.plugin(sort);
window.Alpine = Alpine;

// Defer so that other entry-point modules (e.g. the workflow designer)
// can register their globals before Alpine evaluates x-data attributes.
window.setTimeout(() => Alpine.start(), 0);
