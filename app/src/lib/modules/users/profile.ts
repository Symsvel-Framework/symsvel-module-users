import { generatedTranslation } from '../../i18n/generated-translations';

export type AppSelfProfile = { id: number; email: string; language: string | null; email_verified: boolean };
export type AppSelfProfileResponse = { data: AppSelfProfile };
export type ProfileLanguage = { iso_code: string; name: string; primary: boolean };
const copy = {
  "fr": {
    "title": "Mon profil",
    "intro": "Informations de mon compte et préférences.",
    "email": "Adresse e-mail",
    "verification": "Adresse e-mail",
    "verified": "Confirmée",
    "unverified": "À confirmer",
    "language": "Langue du compte",
    "save": "Enregistrer",
    "busy": "Enregistrement…",
    "saved": "Langue du compte enregistrée.",
    "loading": "Chargement du profil…",
    "failure": "Impossible de charger ou d’enregistrer le profil.",
    "session": "Session expirée. Reconnectez-vous.",
    "back": "Tableau de bord",
    "password": "Réinitialiser mon mot de passe",
    "readonly": "L’adresse e-mail ne peut pas être modifiée sans confirmation de la nouvelle boîte.",
    "passwordTitle": "Changer mon mot de passe",
    "currentPassword": "Mot de passe actuel",
    "newPassword": "Nouveau mot de passe",
    "confirmPassword": "Confirmer le nouveau mot de passe",
    "passwordSave": "Modifier mon mot de passe",
    "passwordInvalid": "Vérifiez le mot de passe actuel et saisissez un nouveau mot de passe différent.",
    "passwordMismatch": "Les deux nouveaux mots de passe ne correspondent pas.",
    "passwordLength": "12 caractères minimum.",
    "passwordSuccess": "Mot de passe modifié. Reconnexion nécessaire.",
    "singleLanguage": "Une seule langue est disponible pour ce projet."
  },
  "en": {
    "title": "My profile",
    "intro": "Account information and preferences.",
    "email": "Email address",
    "verification": "Email address",
    "verified": "Verified",
    "unverified": "Unverified",
    "language": "Account language",
    "save": "Save",
    "busy": "Saving…",
    "saved": "Account language saved.",
    "loading": "Loading profile…",
    "failure": "Unable to load or save the profile.",
    "session": "Session expired. Sign in again.",
    "back": "Dashboard",
    "password": "Reset my password",
    "readonly": "The email address cannot be changed without verifying the new mailbox.",
    "passwordTitle": "Change my password",
    "currentPassword": "Current password",
    "newPassword": "New password",
    "confirmPassword": "Confirm new password",
    "passwordSave": "Change my password",
    "passwordInvalid": "Check your current password and enter a different new password.",
    "passwordMismatch": "The two new passwords do not match.",
    "passwordLength": "At least 12 characters.",
    "passwordSuccess": "Password changed. Sign in again.",
    "singleLanguage": "Only one language is available for this project."
  }
};
export type AppProfileCopy = typeof copy.fr;
export function appProfileCopy(locale: string | null): AppProfileCopy {
  const source = locale?.toLowerCase().startsWith('en') ? copy.en : copy.fr;
  return Object.fromEntries(Object.entries(source).map(([key, value]) =>
    [key, generatedTranslation(locale, 'app.users.profile.' + key) ?? value])) as AppProfileCopy;
}
