<?php
$path = $argv[1] ?? '';
if (!$path || !is_file($path)) {
    fwrite(STDERR, "Usage: php extract_word_xml_text.php <document.xml>\n");
    exit(1);
}
$xml = file_get_contents($path);
if ($xml === false) {
    exit(1);
}
// Strip tags keeping paragraph breaks
$xml = preg_replace('/<\/w:p>/', "\n", $xml);
$text = strip_tags($xml);
$text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
$text = preg_replace("/[ \t]+/", ' ', $text);
$text = preg_replace("/\n{3,}/", "\n\n", $text);
echo trim($text);
