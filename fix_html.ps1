$filePath = "e:\Downloads\TaskForge\public\index.html"
$lines = [System.IO.File]::ReadAllLines($filePath, [System.Text.Encoding]::UTF8)

# Lines 1-27 are good (0-indexed: 0-26), lines 28-642 are leaked CSS (0-indexed: 27-641)
# Lines 643+ are the actual body content (0-indexed: 642+)
$topLines = $lines[0..26]
$bottomLines = $lines[642..($lines.Length - 1)]
$newLines = $topLines + $bottomLines

[System.IO.File]::WriteAllLines($filePath, $newLines, [System.Text.Encoding]::UTF8)
Write-Host "Done. Removed $($lines.Length - $newLines.Length) lines. New total: $($newLines.Length)"
