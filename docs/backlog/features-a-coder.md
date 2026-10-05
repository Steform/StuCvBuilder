# Features / bugs à coder (backlog)

> Ne pas implémenter tant que non demandé explicitement.
> Dernière MAJ : **2026-10-05**

---

## FAIT (référence)

### 1. Langues du site pour `ROLE_CV_EDIT` — FAIT

### 2. Bouton « Ajouter une expérience » grisé sans catégorie niveau 1 — FAIT

### 3. Calendrier HTML5 dates d’expérience — FAIT

- Champs `type="date"` (calendrier natif) sur CV global + entreprise ; stockage `YYYY-MM`
- Bridge Twig (`YYYY-MM` → `YYYY-MM-01`), PHP (`YYYY-MM-DD` → `YYYY-MM`), JS `toDateInputValue` / `toStoredYearMonth`
- Fichiers : `_experience_entry_shared_fields.html.twig`, `_experience_add_modal.html.twig`, `ExperienceContract`, `cv-experience-admin.js`

### 4. Réafficher la date de fin quand on décoche « Poste en cours » — FAIT

- Visibilité pilotée uniquement par `data-hidden="1"` + CSS `display: none !important`
- Écouteurs directs sur chaque checkbox + délégation `document` (capture) + `root`
- Helper `resolveEndDateWrap` (closest) ; nettoyage des anciens `d-none` / `hidden` / `style.display`
- Fichiers : `public/js/cv-experience-admin.js`, `public/css/cv-experience-admin.css`, `_experience_entry_shared_fields.html.twig`
