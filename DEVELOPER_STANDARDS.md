Evolution ohne Breaking Changes! 🎉
Manchmal ist weniger wirklich mehr

# Developer Standards - BitBlog Project

This is a high-quality, well-architected PHP project that demonstrates excellent adherence to modern development practices. The code is clean, follows your stated developer standards, and shows good security awareness.

## Code Quality Philosophy
**Zero tolerance for dead code, unused functions, and code bloat.**

## Core Principles

### 1. Dead Code Detection & Elimination
- **Always hunt for unused code** - "ich finde doch immer wieder was!"
- Remove unused functions, variables, constants, and entire classes
- Use systematic grep searches to identify orphaned code
- Delete entire files if they contain only unused code (e.g., PostStatus.php)

### 2. Complete Solutions Only
- When asked "komplett?" - provide thorough, complete implementations
- No half-measures or partial fixes
- Address all related issues in the same scope

### 3. Code Quality Standards
- Clean namespace management with proper `use` statements
- No redundant validation layers
- Remove duplicate functionality
- Eliminate unnecessary variable declarations
- Clean up commented-out code

### 4. Internationalization Standards
- All UI text must use `Language::getText()` 
- Default language: English ('en')
- German ('de') translations required
- JavaScript translations via TRANSLATIONS object

### 5. Modern PHP Practices
- Use modern alternatives for deprecated functions (e.g., `htmlentities()` instead of `mb_convert_encoding()`)
- Clean class structures without bloat
- Proper error handling

## Detection Methods
1. **Grep searches** for function/class usage patterns
2. **Systematic file analysis** for orphaned code
3. **Variable usage tracking** 
4. **Cross-reference validation** between files

## Quality Mantras
- "wird nicht verwendet!" - eliminate it immediately
- "scharfes Auge für Code-Qualität" - maintain detective mindset
- Pragmatic decisions: "good enough for now" when appropriate
- Document decisions for future reference

## Technical Environment
- **Platform:** Windows with PowerShell
- **Editor:** Visual Studio Code Editor integration
- **Languages:** PHP 8.2+, JavaScript ES6+, HTML5/CSS3
- **Framework:** Custom BitBlog system

---
*This document serves as context for AI agents working on this project. These standards reflect the maintainer's commitment to clean, efficient, and maintainable code.*