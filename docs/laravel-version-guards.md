# Laravel version guards

The package supports Laravel 12 and 13 (`illuminate/contracts` in [`composer.json`](../composer.json)). A Laravel class that one of them lacks is referenced by string FQCN behind `class_exists()` and listed here, so each guard can go when support for that version ends.

No guards yet. Add a row per guard:

| Class | Missing in | Guard site |
| --- | --- | --- |
