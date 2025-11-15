# PowerShell Migration script to convert old XML blog posts to new YYYY-MM-DDTHHMM.ID.md format
# Usage: .\migrate-old-xml.ps1 <input_directory> [output_directory]
# 
# Filename format: UNIXDATE.ID.TAG.....xml
# Example: 1753591608.247.0.0.NULL.2025.07.27.04.46.48.xml

[string]$InputDir ="C:\blog\posts"
[string]$OutputDir = "C:\bitblog\blog-neu"

# Check if input directory exists
if (-not (Test-Path $InputDir)) {
    Write-Error "Error: Input directory not found: $InputDir"
    exit 1
}

# Create output directory if it doesn't exist
if (-not (Test-Path $OutputDir)) {
    New-Item -ItemType Directory -Path $OutputDir -Force | Out-Null
}

# Alternative function specifically for hex sequences like your example
function Fix-DoubleEncodedUtf8([string]$text) {
    try {
        # Convert the string to Latin-1 bytes, then interpret as UTF-8
        $latin1Bytes = [System.Text.Encoding]::GetEncoding("ISO-8859-1").GetBytes($text)
        return [System.Text.Encoding]::UTF8.GetString($latin1Bytes)
    } catch {
        return $text
    }
}

# Test function to demonstrate the fix with your example
function Test-MojibakeFix() {
    Write-Host "=== Testing Mojibake Fix ===" -ForegroundColor Cyan
    
    # Your example byte sequence as a string (how it appears in the XML)
    # C3 B0 C2 9F C2 95 C2 B5 C3 AF C2 B8 C2 8F C3 A2 C2 80 C2 8D C3 A2 C2 99 C2 82 C3 AF C2 B8 C2 8F
    $hexBytes = @(0xC3, 0xB0, 0xC2, 0x9F, 0xC2, 0x95, 0xC2, 0xB5, 0xC3, 0xAF, 0xC2, 0xB8, 0xC2, 0x8F, 0xC3, 0xA2, 0xC2, 0x80, 0xC2, 0x8D, 0xC3, 0xA2, 0xC2, 0x99, 0xC2, 0x82, 0xC3, 0xAF, 0xC2, 0xB8, 0xC2, 0x8F)
    
    # Convert hex bytes to the mojibake string (as it appears in XML)
    $mojibakeString = [System.Text.Encoding]::GetEncoding("ISO-8859-1").GetString($hexBytes)
    Write-Host "Mojibake in XML: '$mojibakeString'"
    
    # Fix it
    $fixed = Fix-DoubleEncodedUtf8 $mojibakeString
    Write-Host "Fixed text: '$fixed'" -ForegroundColor Green
    
    Write-Host "=================================" -ForegroundColor Cyan
    Write-Host ""
}

Write-Host "=== Migration: Old XML Posts -> YYYY-MM-DDTHHMM.ID.md ===" -ForegroundColor Green
Write-Host "Input: $InputDir"
Write-Host "Output: $OutputDir"
Write-Host ""

# Run the test to show the fix works
Test-MojibakeFix

$converted = 0
$skipped = 0
$errors = 0

# Fix UTF-8 encoding issues - handle double-encoded UTF-8 (Mojibake)
function Fix-Utf8Encoding([string]$text) {
        try {
            $bytes1252 = [System.Text.Encoding]::GetEncoding(1252).GetBytes($text)
            return [System.Text.Encoding]::UTF8.GetString($bytes1252)
        } catch {
            # If all else fails, return original text
            return $text
        }
}

# Decode HTML entities and normalize newlines
function Decode-HtmlEntities([string]$text) {
    # Basic HTML entity decoding
    $text = $text -replace '&lt;', '<'
    $text = $text -replace '&gt;', '>'
    $text = $text -replace '&amp;', '&'
    $text = $text -replace '&quot;', '"'
    $text = $text -replace '&#39;', "'"
    $text = $text -replace '&#13;', "`n"
    
    # Normalize newlines
    $text = $text -replace '\\r\\n', "`n"
    $text = $text -replace '\\n', "`n"
    $text = $text -replace '\\r', "`n"
    
    return $text
}

# Convert content to proper Markdown
function Convert-ToMarkdown([string]$content) {
    # Decode all HTML entities
    $content = Decode-HtmlEntities $content
    return $content
}

# Find all XML files
$xmlFiles = Get-ChildItem -Path $InputDir -Filter "*.xml"

foreach ($xmlFile in $xmlFiles) {
    $filename = [System.IO.Path]::GetFileNameWithoutExtension($xmlFile.Name)
    Write-Host "Processing: $filename"
    
    # Parse filename: UNIXDATE.ID.TAG.....xml
    $parts = $filename -split '\.'
    if ($parts.Count -lt 3) {
        Write-Host "  -> Skipped: Invalid filename format" -ForegroundColor Yellow
        $skipped++
        continue
    }
    
    $unixTimestamp = [int]$parts[0]
    $id = [int]$parts[1]
    $tag = $parts[2]
    
    if ($unixTimestamp -le 0 -or $id -le 0) {
        Write-Host "  -> Skipped: Invalid timestamp or ID" -ForegroundColor Yellow
        $skipped++
        continue
    }
    
    try {
        # Load XML as Windows-1252, then convert the mojibake UTF-8 bytes back
        $xmlContent = Get-Content $xmlFile.FullName -Raw -Encoding ([System.Text.Encoding]::GetEncoding(1252))
        
        # Convert the Windows-1252 text back to proper UTF-8
        $bytes1252 = [System.Text.Encoding]::GetEncoding(1252).GetBytes($xmlContent)
        $correctedContent = [System.Text.Encoding]::UTF8.GetString($bytes1252)
        
        [xml]$xml = $correctedContent
        if (-not $xml) {
            Write-Host "  -> Error: Failed to parse XML" -ForegroundColor Red
            $errors++
            continue
        }
        
        # Extract data - access XML elements correctly
        $title = if ($xml.DocumentElement.SelectSingleNode("//title")) { 
            $xml.DocumentElement.SelectSingleNode("//title").InnerText 
        } else { "Untitled" }
        
        $content = if ($xml.DocumentElement.SelectSingleNode("//content")) { 
            $xml.DocumentElement.SelectSingleNode("//content").InnerText 
        } else { "" }
        
        # Debug output
        Write-Host "     Raw Title: '$title'"
        Write-Host "     Raw Content Length: $($content.Length)"
        
        # Fix UTF-8 encoding issues on extracted content
        # Try both approaches to fix the encoding
        $titleFixed1 = Fix-Utf8Encoding $title
        $titleFixed2 = Fix-DoubleEncodedUtf8 $title
        
        $contentFixed1 = Fix-Utf8Encoding $content  
        $contentFixed2 = Fix-DoubleEncodedUtf8 $content
        
        # Use the version that seems to have worked better (has readable characters)
        $title = if ($titleFixed2 -match '[^\x00-\x7F]' -and $titleFixed2.Length -lt $titleFixed1.Length) { 
            $titleFixed2 
        } elseif ($titleFixed1 -ne $title) { 
            $titleFixed1 
        } else { 
            $titleFixed2 
        }
        
        $content = if ($contentFixed2 -match '[^\x00-\x7F]' -and $contentFixed2.Length -lt $contentFixed1.Length) { 
            $contentFixed2 
        } elseif ($contentFixed1 -ne $content) { 
            $contentFixed1 
        } else { 
            $contentFixed2 
        }
        
        Write-Host "     Fixed Title: '$title'"
        Write-Host "     Fixed Content Length: $($content.Length)"
        
        # Decode HTML entities and convert to markdown
        $content = Convert-ToMarkdown $content
        
        # Create date from Unix timestamp
        $dateTime = [DateTimeOffset]::FromUnixTimeSeconds($unixTimestamp).DateTime
        $date = $dateTime.ToString("yyyy-MM-ddTHH:mm:ssK")
        $datePrefix = $dateTime.ToString("yyyy-MM-dd")
        $timeStr = $dateTime.ToString("HHmm")
        
        # Function to convert numeric tag to category name
        function ConvertTagToCategory($tagNumber) {
            switch ($tagNumber) {
                '0' { return 'Uncategorized' }
                '1' { return 'Panorama' }
                '2' { 
                    Write-Error "Tag number 2 is not defined! File: $($xmlFile.Name), Tag: $tagNumber"
                    return $null 
                }
                '3' { return 'Motorrad' }
                '4' { return 'Software' }
                '5' { return 'Fotografie' }
                '6' { return 'Paintings' }
                '7' { return 'Auto' }
                default { return $null }
            }
        }
        
        # Prepare tags
        $tags = @()
        if ($tag -ne '0' -and $tag -ne 'NULL' -and $tag -ne '') {
            $categoryName = ConvertTagToCategory $tag
            if ($categoryName) {
                $tags += $categoryName
            }
        }
        
        # Create YAML frontmatter
        $yaml = @"
---
title: $title
date: $date
status: published
tags: [$($tags -join ', ')]
---

"@
        
        # Create full content
        $markdown = $yaml + $content
        
        # Create new filename: YYYY-MM-DDTHHMM.ID.md
        $newFilename = "$datePrefix" + "T$timeStr.$id.md"
        $outputPath = Join-Path $OutputDir $newFilename
        
        # Write file (always overwrite)
        Set-Content -Path $outputPath -Value $markdown -Encoding UTF8
        Write-Host "  -> Success: $newFilename" -ForegroundColor Green
        Write-Host "     Title: $title"
        Write-Host "     Date: $($dateTime.ToString('yyyy-MM-dd HH:mm:ss'))"
        Write-Host "     Tags: $($tags -join ', ')"
        
        $converted++
        
    } catch {
        Write-Host "  -> Error: $($_.Exception.Message)" -ForegroundColor Red
        $errors++
    }
    
    Write-Host ""
}

Write-Host "=== Migration Complete ===" -ForegroundColor Green
Write-Host "Converted: $converted files"
Write-Host "Skipped: $skipped files"
Write-Host "Errors: $errors files"