<?php
$data = file_get_contents("EldoGas_Complete_Product_Concept.pdf");

// Find all compressed streams and decompress them
preg_match_all('/<<[^>]*\/Filter\/FlateDecode[^>]*>>[\r\n]+stream[\r\n](.*?)[\r\n]endstream/s', $data, $matches, PREG_OFFSET_CAPTURE);

$allText = "";
foreach ($matches[1] as $idx => $match) {
    $compressed = $match[0];
    $decompressed = @gzuncompress($compressed);
    if ($decompressed === false) {
        $decompressed = @gzinflate($compressed);
    }
    if ($decompressed !== false) {
        // Extract text from PDF operators
        // Look for text between ( ) in BT/ET blocks
        preg_match_all('/BT(.*?)ET/s', $decompressed, $btMatches);
        foreach ($btMatches[1] as $block) {
            // Extract string literals
            preg_match_all('/\(([^)\\\\]*(?:\\\\.[^)\\\\]*)*)\)\s*Tj/s', $block, $stringMatches);
            foreach ($stringMatches[1] as $str) {
                $str = stripcslashes($str);
                if (preg_match('/[a-zA-Z]{2,}/', $str)) {
                    $allText .= $str . " ";
                }
            }
            // Also catch TJ arrays
            preg_match_all('/\[([^\]]+)\]\s*TJ/s', $block, $tjMatches);
            foreach ($tjMatches[1] as $tj) {
                preg_match_all('/\(([^)\\\\]*(?:\\\\.[^)\\\\]*)*)\)/', $tj, $tjStrings);
                foreach ($tjStrings[1] as $s) {
                    $s = stripcslashes($s);
                    if (preg_match('/[a-zA-Z]{2,}/', $s)) {
                        $allText .= $s;
                    }
                }
                $allText .= " ";
            }
        }
        $allText .= "\n";
    }
}
echo $allText;
