# Translation Guide for BitBlog

## Adding a New Language

To add a new language to BitBlog, create a new JSON file in the `src/lang/` directory.

### File Naming
- Use the 2-letter ISO 639-1 language code
- Example: `fr.json` for French, `es.json` for Spanish, `it.json` for Italian

### File Structure
Your translation file should contain all translation keys. The `_locale` field is optional:

```json
{
    "newer_posts": "Your Translation", 
    "older_posts": "Your Translation",
    ...
}
```

Or with custom locale (only if needed):
```json
{
    "newer_posts": "Your Translation",
    "older_posts": "Your Translation", 
    ...
}
```

### Advanced Configuration (Optional)

The system automatically detects the best locale, but you can override this directly in your JSON file:

#### Force Specific Locale for Your Language:
```json
{
    "_locale": "pt_BR",
    "newer_posts": "Artigos mais recentes"
}
```

#### Map Browser Languages to Your File:
```json
{
    "_language_overrides": {
        "pt-br": "pt",
        "zh-hans": "zh"
    },
    "newer_posts": "Your translation"
}
```

#### Force Locale for Any Language:
```json
{
    "_locale_overrides": {
        "en": "en_GB",
        "pt": "pt_BR"
    },
    "newer_posts": "Your translation"
}
```

**Most translations don't need any of these fields** - the automatic detection works perfectly for standard cases!

### Complete Example: French Translation

Create `src/lang/fr.json`:
```json
{
    "newer_posts": "Articles plus récents",
    "older_posts": "Articles plus anciens",
    "page_of": "Page %d sur %d",
    "no_posts": "Aucun article disponible",
    
    "categories_overview": "Aperçu des catégories",
    "chronological_overview": "Aperçu chronologique",
    "back_to_blog": "Retour au blog",
    "posts_in_categories": "%d articles dans %d catégories",
    "total_posts_chronological": "%d articles au total",
    "uncategorized": "Non catégorisé",
    
    "published_on": "Publié le",
    "in_category": "dans",
    "tags": "Catégories",
    
    "home": "Accueil",
    "about": "À propos",
    "blog_overview": "Aperçu du blog",
    "search": "Rechercher",
    "archive": "Archives",
    
    "not_found": "Non trouvé",
    "post_not_found": "Article non trouvé",
    "page_not_found": "Page non trouvée",
    
    "admin_panel": "Panneau d'administration",
    "login": "Connexion",
    "logout": "Déconnexion",
    "edit_post": "Modifier l'article",
    "new_post": "Nouvel article",
    "save": "Enregistrer",
    "delete": "Supprimer",
    "rebuild_index": "Reconstruire l'index",
    "invalid_credentials": "Identifiants invalides",
    "username": "Nom d'utilisateur",
    "password": "Mot de passe",
    "rebuild_success": "🔄✅ L'index a été reconstruit avec succès",
    "overview_created": "📚✅ L'aperçu des catégories a été créé",
    "admin_overview_created": "⚙️✅ L'aperçu administrateur a été créé",
    "chronological_created": "📅✅ L'aperçu chronologique a été créé",
    "chronological_admin_created": "⚙️📅✅ L'aperçu chronologique administrateur a été créé",
    "view": "Voir",
    "posts_found": "%d articles trouvés",
    "posts": "Articles",
    "title": "Titre",
    "date": "Date",
    "status": "Statut",
    "actions": "Actions",
    "edit": "Modifier",
    "error": "Erreur",
    "post_deleted": "L'article a été supprimé avec succès",
    "invalid_id": "ID d'article invalide",
    "delete_failed": "Échec de la suppression",
    "file_not_found": "Fichier non trouvé",
    "unknown_error": "Erreur inconnue",
    "status_published": "Publié",
    "status_draft": "Brouillon",
    
    "post_id": "ID de l'article",
    "content": "Contenu",
    "select_existing_category": "-- Sélectionner une catégorie existante --",
    "enter_new_category": "Entrer une nouvelle catégorie...",
    "add": "Ajouter",
    "editor": "Éditeur",
    "preview": "Aperçu",
    "preview_appears_here": "L'aperçu apparaît ici",
    "theme_dark": "🌙 Sombre",
    "theme_light": "☀️ Clair",
    "fullscreen": "⛶ Plein écran",
    "normal_mode": "📄 Normal",
    "show_preview": "Afficher l'aperçu",
    "hide_preview": "Masquer l'aperçu",
    "bold": "Gras",
    "italic": "Italique",
    "code": "Code",
    "link": "Lien",
    "table": "Tableau",
    "enter_code_here": "Entrer le code ici",
    
    "built_with": "Créé avec BitBlog",
    
    "date_format_long": "long",
    "date_format_short": "short"
}
```

### Testing Your Translation

1. Save your translation file in `src/lang/`
2. Visit your blog with `?lang=xx` parameter (e.g., `?lang=fr`)
3. Check that all texts are translated correctly
4. Verify that dates are formatted correctly for your locale

### Need Help?

**The system automatically handles everything!** It will:
1. Use PHP's built-in ICU data to find available locales for your language
2. Select the first working locale (e.g., `fr_FR`, `fr_CA`, etc. for French)  
3. Fall back to a standard pattern (`xx_XX`) if nothing else works
4. Use any overrides you've configured in your JSON file

To test your translation:
1. Save your translation file in `src/lang/`
2. Visit your blog with `?lang=xx` parameter (e.g., `?lang=fr`)
3. Check that all texts are translated correctly and dates format properly

The system will automatically detect your new language file and make it available!