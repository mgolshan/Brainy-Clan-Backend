@if ($locale === 'fr')
Bonjour,

Quelqu'un a essayé plusieurs fois de se connecter à votre compte Brainy Clan avec un mauvais mot de passe. Pour protéger votre compte, la connexion par mot de passe est bloquée pendant {{ $minutes }} minutes.

Si c'était vous et que vous avez oublié votre mot de passe, touchez « Mot de passe oublié ? » dans l'écran de connexion de l'application : nous vous enverrons un lien pour en choisir un nouveau.

Si ce n'était pas vous, votre mot de passe n'a pas été trouvé et vous n'avez rien à faire. Pour plus de sécurité, vous pouvez quand même le changer avec le lien « Mot de passe oublié ? ».

Une question ? Écrivez-nous à {{ $supportEmail }}.

— Brainy Clan
@else
Hello,

Someone tried several times to sign in to your Brainy Clan account with a wrong password. To protect your account, password sign-in is blocked for {{ $minutes }} minutes.

If this was you and you forgot your password, tap "Forgot password?" on the app's sign-in screen: we will email you a link to choose a new one.

If this wasn't you, your password was not found and you don't need to do anything. For extra safety you can still change it with the "Forgot password?" link.

Questions? Email us at {{ $supportEmail }}.

— Brainy Clan
@endif
