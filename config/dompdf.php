<?php

return [
    /*
    | Use a font that supports Arabic (and other scripts) so PDF labels
    | never render as ???? when locale is Arabic. DomPDF's "dejavu sans"
    | is bundled and has full Unicode/Arabic support.
    */
    'options' => [
        'default_font' => 'dejavu sans',
    ],
];
