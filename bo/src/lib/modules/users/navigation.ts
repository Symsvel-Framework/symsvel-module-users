/** BO navigation belongs to the module; authorization remains in its PHP controller. */
export const boNavigation = {
  path: '/users',
  label: { fr: 'Utilisateurs', en: 'Users' },
  resource: 'users',
  action: 'list',
  probe: '/apibo/users'
} as const;
