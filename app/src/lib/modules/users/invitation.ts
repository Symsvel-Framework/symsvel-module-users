export function activationCopy(language: string | null) {
  const fr = {
    title: 'Activer mon compte', intro: 'Choisissez le mot de passe de votre compte.',
    password: 'Mot de passe', confirm: 'Confirmer le mot de passe',
    rule: 'De 12 à 1024 octets UTF-8, sans caractère nul.',
    strength: 'Robustesse du mot de passe',
    strengths: ['Aucune saisie', 'Très faible', 'Faible', 'Moyenne', 'Bonne'],
    mismatch: 'Les mots de passe ne correspondent pas.',
    invalid: 'Mot de passe invalide.',
    expired: 'Invitation invalide, expirée ou déjà utilisée.',
    failure: 'Activation impossible.', submit: 'Activer mon compte',
    busy: 'Activation…', success: 'Votre compte est activé. Vous pouvez vous connecter.',
    login: 'Se connecter'
  };
  const en: typeof fr = {
    title: 'Activate account', intro: 'Choose a password for your new account.',
    password: 'Password', confirm: 'Confirm password',
    rule: '12 to 1024 UTF-8 bytes without NUL.',
    strength: 'Password strength',
    strengths: ['Empty', 'Very weak', 'Weak', 'Moderate', 'Strong'],
    mismatch: 'Passwords do not match.',
    invalid: 'Password is invalid.',
    expired: 'Invitation invalid, expired or already used.',
    failure: 'Activation failed.', submit: 'Activate account',
    busy: 'Activating…', success: 'Your account is active. You can sign in.',
    login: 'Sign in'
  };
  return language?.toLowerCase().startsWith('fr') ? fr : en;
}

export function validActivationPassword(password: string): boolean {
  const bytes = new TextEncoder().encode(password).length;
  return bytes >= 12 && bytes <= 1024 && !password.includes('\0');
}
