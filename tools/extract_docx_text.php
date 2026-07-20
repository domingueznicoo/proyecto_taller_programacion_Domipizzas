<?php
$path = $argv[1] ?? '';
if (!$path || !is_file($path)) {
    fwrite(STDERR, "Usage: php extract_docx_text.php <file.docx>\n");
    exit(1);
}
$zip = new ZipArchive();
if ($zip->open($path) !== true) {
    fwrite(STDERR, "Cannot open docx\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();
if ($xml === false) {
    fwrite(STDERR, "No document.xml\n");
    exit(1);
}
$x = @simplexml_load_string($xml);
if (!$x) {
    fwrite(STDERR, "Invalid XML\n");
    exit(1);
}
$x->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
$text = '';
foreach ($x->xpath('//w:t') as $node) {
    $text .= (string)$node . ' ';
}
echo trim(preg_replace('/\s+/', ' ', $text));
