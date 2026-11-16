export type BoUser = {
  id: number;
  email: string;
  enabled: boolean;
  email_verified: boolean;
  created_at: string;
};
export type BoUsersResponse = { data: BoUser[] };

export function validBoUserPassword(password: string): boolean {
  const bytes = new TextEncoder().encode(password).length;
  return bytes >= 12 && bytes <= 1024 && !password.includes('\0');
}

const messages = {
  fr: {
    title: 'Utilisateurs', dashboard: 'Tableau de bord', heading: 'Gestion des utilisateurs',
    intro: 'Une adresse nouvelle ou modifiée doit être confirmée avant toute connexion App.',
    email: 'Adresse e-mail', password: 'Mot de passe initial', confirmPassword: 'Confirmer le mot de passe',
    passwordRule: 'De 12 à 1024 octets UTF-8, sans caractère nul.',
    strength: 'Diversité du mot de passe',
    strengthHint: 'Une phrase longue et des caractères variés sont recommandés.',
    strengthLevels: ['Aucune saisie', 'Très faible', 'Faible', 'Moyenne', 'Bonne'],
    create: 'Créer un utilisateur', creating: 'Création…', list: 'Comptes utilisateurs',
    refresh: 'Actualiser', loading: 'Chargement…', none: 'Aucun utilisateur.',
    enabled: 'Actif', disabled: 'Désactivé', verified: 'Vérifié', unverified: 'Non vérifié',
    state: 'État', emailState: 'Adresse', actions: 'Actions', edit: 'Modifier',
    save: 'Enregistrer', saving: 'Enregistrement…', cancel: 'Annuler', delete: 'Supprimer',
    confirmDelete: 'Supprimer définitivement cet utilisateur ?',
    created: 'Utilisateur créé.', updated: 'Utilisateur modifié.', deleted: 'Utilisateur supprimé.',
    mailPending: 'E-mail de confirmation non délivré. Il pourra être renvoyé depuis l’App.',
    passwordMismatch: 'Les mots de passe sont différents.', invalidPassword: 'Mot de passe non conforme aux limites.',
    forbidden: 'Action non autorisée.', conflict: 'Adresse déjà utilisée.', invalid: 'Saisie invalide.',
    session: 'Session expirée. Reconnectez-vous.', failure: 'Opération impossible.',
    verifying: 'Vérification de la session…'
  },
  en: {
    title: 'Users', dashboard: 'Dashboard', heading: 'Manage users',
    intro: 'New or changed email addresses must be confirmed before App login.',
    email: 'Email address', password: 'Initial password', confirmPassword: 'Confirm password',
    passwordRule: '12 to 1024 UTF-8 bytes, without NUL.',
    strength: 'Password diversity',
    strengthHint: 'Use a long passphrase with varied characters when possible.',
    strengthLevels: ['Empty', 'Very low', 'Low', 'Moderate', 'Good'],
    create: 'Create user', creating: 'Creating…', list: 'User accounts',
    refresh: 'Refresh', loading: 'Loading…', none: 'No users.',
    enabled: 'Active', disabled: 'Disabled', verified: 'Verified', unverified: 'Unverified',
    state: 'Status', emailState: 'Email', actions: 'Actions', edit: 'Edit',
    save: 'Save', saving: 'Saving…', cancel: 'Cancel', delete: 'Delete',
    confirmDelete: 'Permanently delete this user?',
    created: 'User created.', updated: 'User updated.', deleted: 'User deleted.',
    mailPending: 'Confirmation email was not delivered. It can be resent from the App.',
    passwordMismatch: 'Passwords do not match.', invalidPassword: 'Password does not meet the limits.',
    forbidden: 'Permission denied.', conflict: 'Email already in use.', invalid: 'Invalid input.',
    session: 'Session expired. Please sign in.', failure: 'Operation failed.',
    verifying: 'Checking session…'
  }
};
export type BoUsersCopy = (typeof messages)['fr'];
export function boUsersCopy(language: string | null): BoUsersCopy {
  return language?.toLowerCase().split('-')[0] === 'fr' ? messages.fr : messages.en;
}
