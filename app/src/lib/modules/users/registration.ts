export function validRegistrationPassword(password: string): boolean {
  const bytes = new TextEncoder().encode(password).length;
  return bytes >= 12 && bytes <= 1024 && !password.includes('\0');
}

export type RegistrationResponse = { status: 'accepted' };

const labels = {
  fr: {
    title: 'Créer un compte', intro: 'Votre compte sera accessible après confirmation de votre adresse e-mail.',
    email: 'Adresse e-mail', password: 'Mot de passe', confirmation: 'Confirmer le mot de passe',
    passwordHint: '12 caractères minimum.',
    mismatch: 'Les mots de passe ne correspondent pas.',
    invalidPassword: 'Le mot de passe doit contenir entre 12 et 1024 octets.',
    submit: 'Créer mon compte', pending: 'Enregistrement…',
    success: 'Si la demande a été prise en compte, un e-mail de confirmation va vous être envoyé.',
    failure: 'Impossible de traiter la demande pour le moment.',
    signIn: 'Revenir à la connexion', resend: 'Renvoyer le lien de confirmation'
  },
  en: {
    title: 'Create account', intro: 'Your account will be available after confirming your email address.',
    email: 'Email address', password: 'Password', confirmation: 'Confirm password',
    passwordHint: 'At least 12 characters.',
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
