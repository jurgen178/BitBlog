# PowerShell Script zur Verarbeitung aller Markdown-Dateien in content/posts
# Führt mehrzeilige Suchen/Ersetzen-Operationen in Markdown-Dateien durch
# Überspringt YAML Front Matter Header (zwischen --- Begrenzungen)

[string]$suchDir = "c:\Repos\blog-test\content\posts"

# Mehrzeiliges Suchmuster (Regex mit .* für beliebige Zeichen dazwischen)
[string]$SuchMuster = '<video width="100%" controls>\s*<source src="([^"]+)" type="video/MP4" />\s*<div style="padding: 8px;">\s*Your browser does not support the video tag\.\s*</div>\s*</video>'

# Ersetzungsmuster ($1 referenziert die erste Capture Group - die URL)
[string]$ErsetzenMit = '<video width="100%" controls>
  <source src="$1" type="video/MP4" />
  Your browser does not support the video tag.
</video>'

# Alle Markdown-Dateien laden
$MarkdownDateien = Get-ChildItem -Path $suchDir -Filter "*.md" -File

Write-Host "$($MarkdownDateien.Count) Markdown-Dateien in $suchDir gefunden"

# Zähler für Statistiken
$gesamtTreffer = 0
$dateienMitTreffern = 0

# Jede Datei verarbeiten
foreach ($Datei in $MarkdownDateien) {
    Write-Host "Verarbeite: $($Datei.Name)"
    
    try {
        # Gesamte Datei als String einlesen
        [string]$inhalt = [System.IO.File]::ReadAllText($Datei.FullName, [System.Text.UTF8Encoding]::new($false))
        
        # Front Matter von Content trennen
        $headerEnd = -1
        if ($inhalt.StartsWith("---`n") -or $inhalt.StartsWith("---`r`n")) {
            $headerEnd = $inhalt.IndexOf("`n---", 4)
            if ($headerEnd -eq -1) {
                $headerEnd = $inhalt.IndexOf("`r`n---", 4)
            }
        }
        
        $header = ""
        $body = $inhalt
        
        if ($headerEnd -gt 0) {
            # Finde das Ende der zweiten --- Zeile
            $bodyStart = $inhalt.IndexOf("`n", $headerEnd + 4)
            if ($bodyStart -eq -1) {
                $bodyStart = $headerEnd + 4
            } else {
                $bodyStart++
            }
            
            $header = $inhalt.Substring(0, $bodyStart)
            $body = $inhalt.Substring($bodyStart)
        }
        
        # Suchen und Ersetzen im Body
        # Singleline-Modus: Der Punkt (.) in Regex matched auch Newlines - ermöglicht mehrzeilige Suche
        $trefferInDatei = 0
        $neuerBody = $body
        
        $regexMatches = [regex]::Matches($body, $SuchMuster, [System.Text.RegularExpressions.RegexOptions]::Singleline)
        
        if ($regexMatches.Count -gt 0) {
            $trefferInDatei = $regexMatches.Count
            $gesamtTreffer += $matches.Count
            $dateienMitTreffern++
            
            Write-Host "  $trefferInDatei Treffer gefunden" -ForegroundColor Yellow
            
            # Ersetzen durchführen
            $neuerBody = [regex]::Replace($body, $SuchMuster, $ErsetzenMit, [System.Text.RegularExpressions.RegexOptions]::Singleline)
            
            Write-Host "  Ersetzungen durchgeführt" -ForegroundColor Green
        }
        
        # Zurück in Datei schreiben, wenn Änderungen vorgenommen wurden
        if ($trefferInDatei -gt 0) {
            $neuerInhalt = $header + $neuerBody
            [System.IO.File]::WriteAllText($Datei.FullName, $neuerInhalt, [System.Text.UTF8Encoding]::new($false))
            Write-Host "  Datei erfolgreich aktualisiert" -ForegroundColor Green
        }
        
    } catch {
        Write-Error "Fehler bei der Verarbeitung der Datei $($Datei.Name): $($_.Exception.Message)"
    }
}

Write-Host ""
Write-Host "=== STATISTIKEN ===" -ForegroundColor Magenta
Write-Host "Verarbeitung abgeschlossen!" -ForegroundColor Green
Write-Host "Dateien verarbeitet: $($MarkdownDateien.Count)" -ForegroundColor White
Write-Host "Dateien mit Treffern: $dateienMitTreffern" -ForegroundColor Yellow
Write-Host "Gesamtanzahl Treffer: $gesamtTreffer" -ForegroundColor Yellow
