# Sprachschlüssel-Überprüfung für BitBlog
# Überprüft, ob alle Sprachschlüssel in den Sprachdateien auch verwendet werden

$ErrorActionPreference = 'Stop'

# Lade Sprachdateien
$deJson = Get-Content -Path 'src\lang\de.json' -Raw | ConvertFrom-Json
$enJson = Get-Content -Path 'src\lang\en.json' -Raw | ConvertFrom-Json

# Sammle alle verwendeten Schlüssel aus PHP-Dateien
$usedKeys = @{}

# Suche nach Language::getText('key')
Get-ChildItem -Recurse -Filter '*.php' -File | ForEach-Object {
    $content = Get-Content $_.FullName -Raw
    
    # 1. Finde alle Sprachschlüssel in Language::getText() und Language::getTextf() Aufrufen
    # Sucht nach allen String-Literalen innerhalb dieser Funktionsaufrufe, 
    # auch in ternary operators, Variablen, etc.
    
    # Pattern erklärt:
    # - Language::getText(?:f)?\s* - findet getText oder getTextf
    # - \( - öffnende Klammer
    # - [^)]* - beliebiger Inhalt (nicht-gierig bis zur schließenden Klammer)
    # - Dann suchen wir in diesem Inhalt nach allen String-Literalen
    
    [regex]::Matches($content, "Language::getText(?:f)?\s*\([^)]+\)") | ForEach-Object {
        $fullMatch = $_.Value
        # Finde alle String-Literale innerhalb dieses Aufrufs
        [regex]::Matches($fullMatch, "[`"']([a-z_]+)[`"']") | ForEach-Object {
            $key = $_.Groups[1].Value
            $usedKeys[$key] = $true
        }
    }
    
    # 2. Finde alle 'label' => 'key' Einträge in Arrays (z. B. CONFIGURABLE_SETTINGS)
    # Pattern: 'label' => 'sprachschlüssel'
    [regex]::Matches($content, "['`"]label['`"]\s*=>\s*['`"]([a-z_]+)['`"]") | ForEach-Object {
        $key = $_.Groups[1].Value
        $usedKeys[$key] = $true
    }
    
    # 3. Finde auch group-Namen, die als Sprachschlüssel mit '_settings' Suffix verwendet werden
    # Pattern: 'group' => 'name' wird zu 'name_settings'
    [regex]::Matches($content, "['`"]group['`"]\s*=>\s*['`"]([a-z_]+)['`"]") | ForEach-Object {
        $groupName = $_.Groups[1].Value
        $usedKeys[$groupName + '_settings'] = $true
    }
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "SPRACHSCHLÜSSEL-ANALYSE" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

# Finde nicht verwendete Schlüssel
Write-Host "NICHT VERWENDETE SCHLÜSSEL:" -ForegroundColor Yellow
Write-Host "(in Sprachdateien definiert, aber nirgendwo im Code verwendet)`n" -ForegroundColor DarkGray

$unusedKeys = @()
$deJson.PSObject.Properties | Where-Object { 
    $_.Name -notlike '_*' -and -not $usedKeys.ContainsKey($_.Name) 
} | ForEach-Object {
    $unusedKeys += $_.Name
    Write-Host "  ❌ $($_.Name)" -ForegroundColor Red
}

if ($unusedKeys.Count -eq 0) {
    Write-Host "  ✅ Alle Schlüssel werden verwendet!" -ForegroundColor Green
}

# Finde fehlende Schlüssel
Write-Host "`n`nFEHLENDE SCHLÜSSEL:" -ForegroundColor Yellow
Write-Host "(im Code verwendet, aber nicht in den Sprachdateien definiert)`n" -ForegroundColor DarkGray

# Whitelist für bekannte false-positives (Regex-Artefakte)
$ignoreKeys = @('_settings', 'label', 'group', 'type', 'options', 'min', 'max')

$missingKeys = @()
$usedKeys.Keys | Where-Object { 
    -not ($deJson.PSObject.Properties.Name -contains $_) -and
    -not ($ignoreKeys -contains $_)
} | Sort-Object | ForEach-Object {
    $missingKeys += $_
    Write-Host "  ⚠️  $_" -ForegroundColor Magenta
}

if ($missingKeys.Count -eq 0) {
    Write-Host "  ✅ Alle verwendeten Schlüssel sind definiert!" -ForegroundColor Green
}

# Überprüfe Konsistenz zwischen DE und EN
Write-Host "`n`nKONSISTENZ-PRÜFUNG (DE vs EN):" -ForegroundColor Yellow
Write-Host "(Schlüssel, die nur in einer Sprache existieren)`n" -ForegroundColor DarkGray

$onlyInDe = @()
$onlyInEn = @()

$deJson.PSObject.Properties | Where-Object { $_.Name -notlike '_*' } | ForEach-Object {
    if (-not ($enJson.PSObject.Properties.Name -contains $_.Name)) {
        $onlyInDe += $_.Name
    }
}

$enJson.PSObject.Properties | Where-Object { $_.Name -notlike '_*' } | ForEach-Object {
    if (-not ($deJson.PSObject.Properties.Name -contains $_.Name)) {
        $onlyInEn += $_.Name
    }
}

if ($onlyInDe.Count -gt 0) {
    Write-Host "Nur in DE (nicht in EN):" -ForegroundColor Cyan
    $onlyInDe | Sort-Object | ForEach-Object {
        Write-Host "  ⚠️  $_" -ForegroundColor Yellow
    }
}

if ($onlyInEn.Count -gt 0) {
    Write-Host "`nNur in EN (nicht in DE):" -ForegroundColor Cyan
    $onlyInEn | Sort-Object | ForEach-Object {
        Write-Host "  ⚠️  $_" -ForegroundColor Yellow
    }
}

if ($onlyInDe.Count -eq 0 -and $onlyInEn.Count -eq 0) {
    Write-Host "  ✅ DE und EN sind konsistent!" -ForegroundColor Green
}

# Zusammenfassung
Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "ZUSAMMENFASSUNG" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

$deAllKeys = @($deJson.PSObject.Properties)
$enAllKeys = @($enJson.PSObject.Properties)
$deMetaKeys = @($deAllKeys | Where-Object { $_.Name -like '_*' })
$enMetaKeys = @($enAllKeys | Where-Object { $_.Name -like '_*' })

$deCount = $deAllKeys.Count - $deMetaKeys.Count
$enCount = $enAllKeys.Count - $enMetaKeys.Count

Write-Host "Definierte Schlüssel (DE): $deCount" -ForegroundColor White
Write-Host "Definierte Schlüssel (EN): $enCount" -ForegroundColor White
Write-Host "Verwendete Schlüssel:      $($usedKeys.Count)" -ForegroundColor White
Write-Host "Nicht verwendet:           $($unusedKeys.Count)" -ForegroundColor $(if ($unusedKeys.Count -gt 0) { 'Red' } else { 'Green' })
Write-Host "Fehlend:                   $($missingKeys.Count)" -ForegroundColor $(if ($missingKeys.Count -gt 0) { 'Magenta' } else { 'Green' })
Write-Host "Inkonsistent (DE/EN):      $($onlyInDe.Count + $onlyInEn.Count)" -ForegroundColor $(if (($onlyInDe.Count + $onlyInEn.Count) -gt 0) { 'Yellow' } else { 'Green' })

Write-Host ""
