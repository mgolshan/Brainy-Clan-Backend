@extends('legal.layout')

@section('title', 'Terms of Service')
@section('other_document', 'Privacy Policy')

@section('content')
    <p><span class="review">[DRAFT FOR LAWYER REVIEW — these terms describe how the app works and must be reviewed by a qualified lawyer (Canada and the United States, including Quebec consumer protection law) before the app is published.]</span></p>

    <p>These terms are an agreement between you and {{ $contact['company'] }} ("we", "us") for the use of the Brainy Clan app and service. By creating an account or using the app you accept them and our Privacy Policy.</p>

    <h2>1. Who can use Brainy Clan</h2>
    <ul>
        <li>Only adults (18 or older, or the age of majority where you live) can create an account and a family.</li>
        <li>Children and teens join a family only through an invitation from a family admin. For a child, the admin confirms that they are the parent or legal guardian (or act with their permission) and accepts these terms and the Privacy Policy on the child's behalf.</li>
        <li>Teens with their own login accept these terms themselves; a parent or guardian should review them with the teen.</li>
    </ul>

    <h2>2. Your family</h2>
    <p>The person who creates a family is its owner and an admin. Admins can invite and remove members, set goals and rewards, issue sign-in codes and manage devices. Admins are responsible for the invitations they create and for the information they enter about family members. Rewards set for goals (for example "pizza night") are promises between family members, not offers from us.</p>

    <h2>3. Accounts and security</h2>
    <p>Keep your password and devices secure. Sign-in codes are single-use and expire quickly; do not share them outside your family. Tell us if you think your account has been used without permission. You can sign out lost devices in the app.</p>

    <h2>4. Acceptable use</h2>
    <p>Do not misuse the service: no cheating tools or automated play, no attempts to access other families' data, no interfering with the service, and no offensive nicknames, family names or goal texts. We may suspend accounts that break these rules.</p>

    <h2>5. Content</h2>
    <p>Quiz questions, puzzles, wisdom cards and other content are provided for learning and entertainment. We review content carefully, but it may contain mistakes; please report them. Content and the app are protected by intellectual property laws and are licensed to you for personal, non-commercial use only.</p>

    <h2>6. Subscriptions and purchases</h2>
    <p>The free plan has limits (for example the number of members and categories). The Family plan is an auto-renewing subscription purchased by an adult through the Apple App Store or Google Play. Price, billing period and renewal are shown before purchase. Payment, renewal, cancellation and refunds are handled by the app store under its terms; you can cancel at any time in your store account settings, and the plan stays active until the end of the paid period. Children and teens cannot make purchases in the app. <span class="review">[Lawyer review: Quebec Consumer Protection Act — distance contracts, cancellation and price disclosure.]</span></p>

    <h2>7. Advertising</h2>
    <p>On the free plan, teens and adults may choose to watch an optional rewarded ad to get a second chance. Ads are non-personalized. Children never see ads.</p>

    <h2>8. Ending your use</h2>
    <p>You can delete your account at any time in the app. The family owner can delete the family, which also erases the accounts of members who have no login of their own (such as children). We may suspend or close accounts that seriously or repeatedly break these terms, or if required by law.</p>

    <h2>9. Disclaimers and liability</h2>
    <p>The service is provided "as is". To the extent permitted by law, we are not liable for indirect or consequential damages, and our total liability is limited to the amount you paid us in the twelve months before the claim. Nothing in these terms limits rights you have under consumer protection laws that cannot be waived. <span class="review">[Lawyer review: enforceability of limitations for consumers in Quebec and US states.]</span></p>

    <h2>10. Changes</h2>
    <p>We may update these terms. For meaningful changes we publish a new version with a summary, and the app asks you to accept it again before you continue.</p>

    <h2>11. Governing law</h2>
    <p><span class="review">[To be completed by counsel: governing law (e.g. the laws of the Province of Quebec / Ontario and the federal laws of Canada) and forum; consumer rights in the user's place of residence are preserved.]</span></p>

    <h2>12. Contact</h2>
    <p>Email: <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a><br>
        Mail: {{ $contact['company'] }}, {{ $contact['address'] }}</p>
@endsection
