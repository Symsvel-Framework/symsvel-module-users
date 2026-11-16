export type BoUser = {
  id: number;
  email: string;
  enabled: boolean;
  email_verified: boolean;
  created_at: string;
};
export type BoUsersResponse = { data: BoUser[] };

const messages = {
  fr: {
    title: 'Utilisateurs', heading: 'Gestion des utilisateurs',
    intro: 'Les comptes créés depuis le BO reçoivent un lien pour choisir leur mot de passe.',
    email: 'Adresse e-mail', inviteIntro: 'Le destinataire choisira son mot de passe et activera son compte depuis le lien reçu par e-mail.',
    create: 'Inviter un utilisateur', creating: 'Invitation…', list: 'Comptes utilisateurs',
    refresh: 'Actualiser', loading: 'Chargement…', none: 'Aucun utilisateur.',
    enabled: 'Actif', disabled: 'Désactivé', verified: 'Vérifié', unverified: 'Non vérifié',
    state: 'État', emailState: 'Adresse', createdAt: 'Créé le', actions: 'Actions',
    search: 'Rechercher', searchHint: 'Adresse e-mail…', resend: 'Renvoyer l’invitation',
    resent: 'Nouvelle invitation envoyée.', edit: 'Modifier',
    save: 'Enregistrer', saving: 'Enregistrement…', cancel: 'Annuler', delete: 'Supprimer',
    confirmDelete: 'Supprimer définitivement cet utilisateur ?',
    created: 'Utilisateur invité.', updated: 'Utilisateur modifié.', deleted: 'Utilisateur supprimé.',
    mailPending: 'E-mail non délivré. Une nouvelle invitation peut être envoyée depuis la liste.',
    forbidden: 'Action non autorisée.', conflict: 'Adresse déjà utilisée.', invalid: 'Saisie invalide.',
    session: 'Session expirée. Reconnectez-vous.', failure: 'Opération impossible.'
  },
  en: {
    title: 'Users', heading: 'Manage users',
    intro: 'Accounts created in BO receive a link to choose their own password.',
    email: 'Email address', inviteIntro: 'The recipient will choose their password and activate their account using the emailed link.',
    create: 'Invite user', creating: 'Inviting…', list: 'User accounts',
    refresh: 'Refresh', loading: 'Loading…', none: 'No users.',
    enabled: 'Active', disabled: 'Disabled', verified: 'Verified', unverified: 'Unverified',
    state: 'Status', emailState: 'Email', createdAt: 'Created', actions: 'Actions',
    search: 'Search', searchHint: 'Email address…', resend: 'Resend invitation',
    resent: 'New invitation sent.', edit: 'Edit',
    save: 'Save', saving: 'Saving…', cancel: 'Cancel', delete: 'Delete',
    confirmDelete: 'Permanently delete this user?',
    created: 'User invited.', updated: 'User updated.', deleted: 'User deleted.',
    mailPending: 'Email not delivered. You can resend an invitation from the list.',
    forbidden: 'Permission denied.', conflict: 'Email already in use.', invalid: 'Invalid input.',
    session: 'Session expired. Please sign in.', failure: 'Operation failed.'
  }
};
export type BoUsersCopy = (typeof messages)['fr'];
export function boUsersCopy(language: string | null): BoUsersCopy {
  return language?.toLowerCase().split('-')[0] === 'fr' ? messages.fr : messages.en;
}
