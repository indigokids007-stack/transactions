<?php

return [
    /**
     * The currency a new draft starts with. Not itself part of the manageable list in
     * `currencies` (that table is admin-editable through the panel); this is the one
     * system-level fallback, checked against that table like any other currency the
     * moment it is actually used for money.
     */
    'default' => 'UZS',
];
