<?php
// Function to obtain normal characters or URL friendly characters
function normal_chars($string) {
    $string = htmlentities($string, ENT_QUOTES, 'UTF-8');
    $string = preg_replace('~&([a-z]{1,2})(acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);~i', '$1', $string);
    $string = preg_replace(array('~[^0-9a-z]~i', '~-+~'), ' ', $string);
    return trim($string);
}
?>
