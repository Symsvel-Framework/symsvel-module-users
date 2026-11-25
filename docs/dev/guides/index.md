# Users

Module natif installé uniquement lorsque le projet possède une App. Il gère les comptes des utilisateurs, séparément des administrateurs BO.

## Utiliser le module

Un utilisateur peut s'inscrire depuis l'App. Il reçoit un message de confirmation et ne peut ouvrir sa session avant la vérification de son adresse. Depuis le BO, un administrateur autorisé peut inviter un utilisateur, renvoyer l'invitation, modifier son profil ou désactiver son compte.

## Configuration

Configurez l'origine App au Setup et le transport Mails. La langue du compte est choisie parmi les langues disponibles. La politique 2FA de l'App relève de TwoFactor.

## API

| Route | Fonction |
| --- | --- |
| POST /apiopen/users/register | Inscription |
| GET /apibo/users | Liste BO |
| POST /apibo/users | Invitation BO |
| PATCH /apibo/users/{id} | Modifier un compte |
| POST /apibo/users/{id}/invitations/resend | Renvoyer une invitation |
| DELETE /apibo/users/{id} | Supprimer selon les contrôles serveur |

L'inscription retourne une réponse générique pour les entrées valides afin de ne pas révéler si une adresse existe. Si le mail n'est pas reçu, utilisez le parcours de renvoi plutôt que de créer un second compte. Les autorisations BO sont contrôlées sur le serveur. Les entités métier peuvent référencer le compte utilisateur ; ne modifiez pas ses champs d'authentification directement.
