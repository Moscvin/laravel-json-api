<?php 
echo "=== Laravel Test ===
";
echo "PHP Version: " . phpversion() . "
";
echo "SCRIPT_FILENAME: " . (["SCRIPT_FILENAME"] ?? "NOT SET") . "
";
echo "REQUEST_URI: " . (["REQUEST_URI"] ?? "NOT SET") . "
";
echo "DOCUMENT_ROOT: " . (["DOCUMENT_ROOT"] ?? "NOT SET") . "
";
?>
