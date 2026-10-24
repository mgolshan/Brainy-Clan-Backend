@if ($locale === 'fr')
Bonjour,

@if ($checkoutUrl !== null)
Voici votre lien pour vous abonner au forfait Famille de Brainy Clan sur le Web (valide {{ $minutes }} minutes, pour votre famille seulement) :
{{ $checkoutUrl }}

Le paiement est traité par Paddle.com, notre revendeur officiel (« merchant of record ») : Paddle encaisse le paiement, les taxes et vous envoie le reçu. Le forfait s’active dans l’application dès que le paiement est confirmé.
@elseif ($reason === 'store_plan_active')
Votre famille a déjà un abonnement Brainy Clan acheté dans {{ $storeLabel }}. Gérez-le ou annulez-le à cet endroit ; il n’est pas nécessaire de vous abonner de nouveau sur le Web.
@elseif ($reason === 'web_plan_active')
Votre famille a déjà un abonnement Brainy Clan acheté sur le Web. Pour le gérer (carte, factures, annulation), ouvrez l’écran Abonnement de l’application, ou utilisez le lien dans votre reçu Paddle.
@elseif ($reason === 'not_admin' || $reason === 'purchases_not_allowed')
Seul un administrateur adulte de votre famille peut acheter le forfait Famille. Demandez-lui de s’abonner avec l’adresse courriel de son compte.
@elseif ($reason === 'no_family')
Votre compte n’appartient encore à aucune famille. Créez votre famille dans l’application Brainy Clan, puis revenez vous abonner.
@else
Nous n’avons trouvé aucun compte Brainy Clan avec cette adresse courriel. Téléchargez l’application, créez votre famille avec cette adresse, puis revenez vous abonner.
@endif

Si vous n’avez pas fait cette demande, ignorez ce courriel. Des questions ? {{ $supportEmail }}

— Brainy Clan
@else
Hello,

@if ($checkoutUrl !== null)
Here is your link to subscribe to the Brainy Clan Family plan on the web (valid for {{ $minutes }} minutes, for your family only):
{{ $checkoutUrl }}

Payment is handled by Paddle.com, our authorized reseller (merchant of record): Paddle takes the payment and the sales tax and sends you the receipt. The plan turns on in the app as soon as the payment is confirmed.
@elseif ($reason === 'store_plan_active')
Your family already has a Brainy Clan subscription bought in {{ $storeLabel }}. Manage or cancel it there; there is no need to subscribe again on the web.
@elseif ($reason === 'web_plan_active')
Your family already has a Brainy Clan subscription bought on the web. To manage it (card, invoices, cancellation), open the Subscription screen in the app, or use the link in your Paddle receipt.
@elseif ($reason === 'not_admin' || $reason === 'purchases_not_allowed')
Only an adult admin of your family can buy the Family plan. Ask them to subscribe with the email address of their account.
@elseif ($reason === 'no_family')
Your account does not belong to a family yet. Create your family in the Brainy Clan app, then come back to subscribe.
@else
We could not find a Brainy Clan account with this email address. Download the app, create your family with this address, then come back to subscribe.
@endif

If you did not ask for this, ignore this email. Questions? {{ $supportEmail }}

— Brainy Clan
@endif
