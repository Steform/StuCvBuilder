# Features à coder (backlog)

> Implémenté le **2026-10-05** (voir historique git / diff). Conservé comme référence métier.

---

## 1. Gestion des langues du site pour `ROLE_CV_EDIT` — FAIT

- `#[IsGranted('ROLE_CV_EDIT')]` sur `app_dashboard_configuration_language`
- `access_control` : `/dashboard/configuration/language` → `ROLE_CV_EDIT`
- Bouton menu dans la branche `ROLE_CV_EDIT` de `_admin_dashboard_menu.html.twig`
- Carte dashboard « Langues du site »
- Test : `AdminDashboardMenuIntegrationTest::testLanguageConfigurationIsGrantedToRoleCvEdit`

---

## 2. Bouton « Ajouter une expérience » grisé sans catégorie niveau 1 — FAIT

- Clé payload `experienceCategories` (niveau 1 uniquement)
- UI admin : liste de catégories + bouton d’ajout expérience `disabled` s’il n’y en a aucune
- Champ `categoryId` sur les expériences (modal + édition)
- Persistance via `CvExperienceAdminUpdateService` + `CvProfilePersistenceScope`

---

## 3. Calendrier HTML5 dates d’expérience — FAIT

- Conservé `type="month"` (format `YYYY-MM`)
- Si poste en cours : calendrier de fin **masqué** (`hidden`), pas seulement `disabled`
- Sync modal + édition d’entrée (`cv-experience-admin.js`)
