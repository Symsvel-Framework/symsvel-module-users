import { passwordStrength } from '../../password/strength';
import { generatedTranslation } from '../../i18n/generated-translations';

export function validRegistrationPassword(password: string): boolean {
  return passwordStrength(password).valid;
}

export type RegistrationResponse = { status: 'accepted' };

const labels = {
  fr: {
    title: 'Créer un compte', intro: 'Votre compte sera accessible après confirmation de votre adresse e-mail.',
    email: 'Adresse e-mail', accountLanguage: 'Langue du compte', password: 'Mot de passe', confirmation: 'Confirmer le mot de passe',
    passwordHint: '12 caractères minimum, majuscule, minuscule, chiffre et caractère spécial.',
    strength: 'Force du mot de passe (indicative)',
    strengthHint: 'Une phrase longue est recommandée, en respectant les critères.',
    strengthLevels: ['Aucune saisie', 'Très faible', 'Faible', 'Moyenne', 'Bonne'],
    mismatch: 'Les mots de passe ne correspondent pas.',
    invalidPassword: 'Respectez les critères du nouveau mot de passe.',
    submit: 'Créer mon compte', pending: 'Enregistrement…',
    success: 'Si la demande a été prise en compte, un e-mail de confirmation va vous être envoyé.',
    failure: 'Impossible de traiter la demande pour le moment.',
    signIn: 'Revenir à la connexion', resend: 'Renvoyer le lien de confirmation'
  },
  en: {
    title: 'Create account', intro: 'Your account will be available after confirming your email address.',
    email: 'Email address', accountLanguage: 'Account language', password: 'Password', confirmation: 'Confirm password',
    passwordHint: 'At least 12 characters, uppercase, lowercase, digit and special character.',
    strength: 'Password strength (estimate)',
    strengthHint: 'A long passphrase meeting the requirements is recommended.',
    strengthLevels: ['Empty', 'Very low', 'Low', 'Moderate', 'Good'],
    mismatch: 'Passwords do not match.',
    invalidPassword: 'Meet the new password requirements.',
    submit: 'Create my account', pending: 'Registering…',
    success: 'If your request was accepted, a confirmation email will be sent.',
    failure: 'Unable to process your request at this time.',
    signIn: 'Back to sign in', resend: 'Resend confirmation link'
  }
};
export type RegistrationCopy = (typeof labels)['fr'];
export function registrationCopy(language: string | null): RegistrationCopy {
  const base = language?.toLowerCase().split('-')[0] === 'en' ? labels.en : labels.fr;
  return Object.fromEntries(Object.entries(base).map(([key, value]) => [
    key, Array.isArray(value)
      ? value.map((entry, index) => generatedTranslation(language, 'app.users.registration.' + key + '.' + index) ?? entry)
      : generatedTranslation(language, 'app.users.registration.' + key) ?? value
  ])) as RegistrationCopy;
}
