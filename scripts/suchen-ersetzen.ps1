# PowerShell Script zur Verarbeitung aller Markdown-Dateien in content/posts
# Führt Suchen/Ersetzen-Operationen in Markdown-Dateien durch
# Überspringt YAML Front Matter Header (zwischen --- Begrenzungen)

[string]$suchDir = "D:\blog\index2-downloads\posts"
[string]$ausgabeDir = "D:\blog\index2-downloads\out"

[string]$SuchMuster = "ISO(\d)"
[string]$ErsetzenMit = "ISO `$1"

# Ausgabeverzeichnis erstellen, falls nicht vorhanden
if (-not (Test-Path $ausgabeDir)) {
    New-Item -Path $ausgabeDir -ItemType Directory -Force | Out-Null
    Write-Host "Ausgabeverzeichnis erstellt: $ausgabeDir" -ForegroundColor Green
}

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
        # Alle Zeilen aus der Datei lesen
        $imHeader = $false
        $trefferInDatei = 0
        
        [string[]]$inhalt = [System.IO.File]::ReadLines($Datei.FullName) | ForEach-Object {
            # Prüfung auf Header-Anfang/Ende
            if ($_ -eq "---") {
                if (-not $imHeader) {
                    $imHeader = $true
                    return $_
                } else {
                    $imHeader = $false
                    return $_
                }
            }
            
            # Header-Zeilen überspringen
            if ($imHeader) {
                return $_
            }
            
            # Inhalt für Suchen/Ersetzen verarbeiten
            if ($_ -match $SuchMuster) {
                $trefferInDatei++
                $gesamtTreffer++
                Write-Host "  Treffer gefunden in Zeile: $_" -ForegroundColor Yellow
                $ersetzteZeile = $_ -replace $SuchMuster, $ErsetzenMit
                Write-Host "  Ersetzt mit: $ersetzteZeile" -ForegroundColor Green
                return $ersetzteZeile
            } else {
                return $_
            }
        }
        
        # Statistiken für diese Datei anzeigen und nur geänderte Dateien speichern
        if ($trefferInDatei -gt 0) {
            $dateienMitTreffern++
            Write-Host "  $trefferInDatei Treffer in dieser Datei gefunden" -ForegroundColor Cyan
            
            # Nur geänderte Dateien in Ausgabeverzeichnis schreiben
            $ausgabeDatei = Join-Path $ausgabeDir $Datei.Name
            [System.IO.File]::WriteAllLines($ausgabeDatei, $inhalt, [System.Text.UTF8Encoding]::new($false))
            Write-Host "  Datei gespeichert: $ausgabeDatei" -ForegroundColor Green
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
