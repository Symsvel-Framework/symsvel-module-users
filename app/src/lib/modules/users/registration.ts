export function validRegistrationPassword(password: string): boolean {
  const bytes = new TextEncoder().encode(password).length;
  return bytes >= 12 && bytes <= 1024 && !password.includes('\0');
}

export type RegistrationResponse = { status: 'accepted' };

const labels = {
  fr: {
    title: 'Créer un compte', intro: 'Votre compte sera accessible après confirmation de votre adresse e-mail.',
    email: 'Adresse e-mail', accountLanguage: 'Langue du compte', password: 'Mot de passe', confirmation: 'Confirmer le mot de passe',
    passwordHint: 'De 12 à 1024 octets UTF-8, sans caractère nul.',
    strength: 'Diversité du mot de passe',
    strengthHint: 'Une phrase longue et un mélange de caractères sont conseillés, sans être imposés.',
    strengthLevels: ['Aucune saisie', 'Très faible', 'Faible', 'Moyenne', 'Bonne'],
    mismatch: 'Les mots de passe ne correspondent pas.',
    invalidPassword: 'Le mot de passe doit contenir entre 12 et 1024 octets.',
    submit: 'Créer mon compte', pending: 'Enregistrement…',
    success: 'Si la demande a été prise en compte, un e-mail de confirmation va vous être envoyé.',
    failure: 'Impossible de traiter la demande pour le moment.',
    signIn: 'Revenir à la connexion', resend: 'Renvoyer le lien de confirmation'
  },
  en: {
    title: 'Create account', intro: 'Your account will be available after confirming your email address.',
    email: 'Email address', accountLanguage: 'Account language', password: 'Password', confirmation: 'Confirm password',
    passwordHint: '12 to 1024 UTF-8 bytes, no NUL character.',
    strength: 'Password diversity',
    strengthHint: 'A long passphrase with varied characters is recommended but not required.',
    strengthLevels: ['Empty', 'Very low', 'Low', 'Moderate', 'Good'],
    mismatch: 'Passwords do not match.',
    invalidPassword: 'Password must contain between 12 and 1024 bytes.',
    submit: 'Create my account', pending: 'Registering…',
    success: 'If your request was accepted, a confirmation email will be sent.',
    failure: 'Unable to process your request at this time.',
    signIn: 'Back to sign in', resend: 'Resend confirmation link'
  }
};
export type RegistrationCopy = (typeof labels)['fr'];
export function registrationCopy(language: string | null): RegistrationCopy {
  return language?.toLowerCase().split('-')[0] === 'fr' ? labels.fr : labels.en;
}
