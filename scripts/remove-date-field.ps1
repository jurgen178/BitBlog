# PowerShell Script to remove 'date' field from YAML Front Matter in all Markdown files
# Processes all Markdown files in content/posts and content/pages

$postsDir = "content\posts"
$pagesDir = "content\pages"

function Remove-DateField {
    param (
        [string]$Directory
    )
    
    if (-not (Test-Path $Directory)) {
        Write-Host "Directory not found: $Directory" -ForegroundColor Yellow
        return
    }
    
    $MarkdownFiles = Get-ChildItem -Path $Directory -Filter "*.md" -File
    Write-Host "$($MarkdownFiles.Count) Markdown files found in $Directory"
    
    $filesModified = 0
    
    foreach ($File in $MarkdownFiles) {
        Write-Host "Processing: $($File.Name)"
        
        try {
            $content = Get-Content -Path $File.FullName -Raw -Encoding UTF8
            
            # Check if file has YAML front matter
            if ($content -match '^---\r?\n') {
                # Remove the date field from YAML front matter
                $newContent = $content -replace '(?m)^date:\s*[^\r\n]+\r?\n', ''
                
                if ($newContent -ne $content) {
                    # Write back to file
                    Set-Content -Path $File.FullName -Value $newContent -Encoding UTF8 -NoNewline
                    Write-Host "  Removed 'date' field from $($File.Name)" -ForegroundColor Green
                    $filesModified++
                } else {
                    Write-Host "  No 'date' field found in $($File.Name)" -ForegroundColor Gray
                }
            } else {
                Write-Host "  No YAML front matter found in $($File.Name)" -ForegroundColor Gray
            }
            
        } catch {
            Write-Error "Error processing file $($File.Name): $($_.Exception.Message)"
        }
    }
    
    return $filesModified
}

Write-Host "=== Removing 'date' field from YAML Front Matter ===" -ForegroundColor Magenta
Write-Host ""

# Process posts directory
Write-Host "Processing posts directory..." -ForegroundColor Cyan
$postsModified = Remove-DateField -Directory $postsDir

Write-Host ""

# Process pages directory
Write-Host "Processing pages directory..." -ForegroundColor Cyan
$pagesModified = Remove-DateField -Directory $pagesDir

Write-Host ""
Write-Host "=== COMPLETE ===" -ForegroundColor Magenta
Write-Host "Files modified in posts: $postsModified" -ForegroundColor Green
Write-Host "Files modified in pages: $pagesModified" -ForegroundColor Green
Write-Host "Total files modified: $($postsModified + $pagesModified)" -ForegroundColor Green
