# Features / bugs à coder (backlog)

> Ne pas implémenter tant que non demandé explicitement.
> Dernière MAJ : **2026-10-05**

---

## FAIT (référence)

### 1. Langues du site pour `ROLE_CV_EDIT` — FAIT

### 2. Bouton « Ajouter une expérience » grisé sans catégorie niveau 1 — FAIT

### 3. Calendrier HTML5 dates d’expérience — FAIT

### 4. Réafficher la date de fin quand on décoche « Poste en cours » — FAIT

- Helper JS `syncExperienceEndDateVisibility` : toggle `hidden` + `d-none` + `disabled` + `required`
- Édition d’entrée + modal d’ajout
- Focus sur la date de fin après décoché
- Fichiers : `public/js/cv-experience-admin.js`, `_experience_entry_shared_fields.html.twig`, `_experience_add_modal.html.twig`
