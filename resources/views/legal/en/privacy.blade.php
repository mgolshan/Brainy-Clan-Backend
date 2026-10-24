@extends('legal.layout')

@section('title', 'Privacy Policy')
@section('other_document', 'Terms of Service')

@section('content')
    <p><span class="review">[DRAFT FOR LAWYER REVIEW — this text was prepared to describe how the app actually works. It must be reviewed by a qualified lawyer (Canada: PIPEDA and Quebec Law 25; United States: COPPA and state privacy laws) before the app is published.]</span></p>

    <p>Brainy Clan is a family learning and quiz app. A parent or guardian creates a family, invites family members (including children) and plays short quizzes and puzzles together. This policy explains what personal information we collect, why, who we share it with, how long we keep it and what rights you have. It is operated by {{ $contact['company'] }} ("we", "us").</p>

    <h2>1. Summary</h2>
    <ul>
        <li>For children we collect only a <strong>nickname, an avatar and a birth year and month</strong> — no email address, no full name, no photo, no exact birth date, no precise location. If a parent leaves the family chat on for them, we also store the chat messages they write, which only their own family can read (see "Family chat").</li>
        <li>Children see <strong>no advertising and no tracking</strong>. Teens and adults on the free plan may see optional rewarded ads that are <strong>non-personalized</strong>.</li>
        <li>A parent or guardian gives consent for each child before the child can join.</li>
        <li>We do not sell personal information, and we do not use it for profiling or targeted advertising.</li>
        <li>You can download a copy of your data in the app and delete your account at any time.</li>
    </ul>

    <h2>2. Information we collect</h2>
    <h3>Adults (18+) and teens with their own login</h3>
    <ul>
        <li>Display name, avatar, birth year and month (to apply the right age rules), language.</li>
        <li>Email address and password (stored only as a secure hash), or the identifier of the Google, Apple or Facebook account you choose to sign in with. With Sign in with Apple you may use Apple's private relay email.</li>
    </ul>
    <h3>Children (under 13)</h3>
    <ul>
        <li>Nickname, avatar and birth year and month, entered by the parent or guardian in the invitation. A child never enters an email address or password and cannot sign in with Google, Apple or Facebook.</li>
    </ul>
    <h3>Everyone</h3>
    <ul>
        <li><strong>Family and activity data:</strong> family name, role (admin or member), answers given in quizzes and puzzles, points, streaks, goals and their rewards, accessibility and difficulty settings.</li>
        <li><strong>Device data:</strong> a device name and platform (iOS, Android, web), the app version, the time the app was last used and, if notifications are allowed, a push notification token.</li>
        <li><strong>Consent records:</strong> which version of these documents was accepted, when, and a one-way, keyed hash of the network (IP) address — not the address itself.</li>
        <li><strong>Purchases:</strong> if an adult subscribes, the app store handles the payment. We receive only the subscription status (for example active or expired), never card details.</li>
        <li><strong>Error reports:</strong> technical crash and error details (for example the app version and the failing code) with names, email addresses, birth data and authentication details removed before they are sent.</li>
    </ul>
    <h3>Family chat</h3>
    <p><span class="review">[DRAFT FOR LAWYER REVIEW]</span> Each family has one private group chat for short text messages and emoji (no photos, files, links previews or voice). We store each message's text, its author (the family member), the time it was sent and whether it was deleted, plus each member's "last read" position. <strong>Only the active members of that family can see the chat</strong> — not other families, and not our staff: messages are not shown in our administration tools and are kept out of logs, error reports and our activity records. Messages are <strong>encrypted at rest</strong> on our servers with the application's key. Notifications about new messages say only who wrote ("Sam sent a message"), never the message text. A family admin can turn the chat off for any member and delete any message; every member can delete their own messages. Messages are deleted automatically after <strong>7 days on the free plan and 1 year on the Family plan</strong> (after a switch to the free plan, the shorter period applies). When an account is deleted, all of that person's messages are deleted; when a member is removed from the family, their messages stay visible to the family as "Former member" until they expire; when the family is deleted, its whole chat is deleted. Your own messages are included in your data download. The chat is not end-to-end encrypted.</p>

    <p>We do not knowingly collect any other personal information from children. If you believe a child has given us information we did not ask for, contact us and we will delete it.</p>

    <h2>3. How we use information</h2>
    <ul>
        <li>To run the service: accounts, sign-in, families, quizzes, scores, goals and reminders.</li>
        <li>To apply age-appropriate rules: the birth year and month decide the age band, which sets content, difficulty, whether ads may be shown and whether an email login is allowed.</li>
        <li>To send the notifications each person turned on (for example goal results or a daily reminder), respecting quiet hours.</li>
        <li>To manage subscriptions and, for teens and adults on the free plan, to verify that a rewarded ad was watched.</li>
        <li>To keep the service secure, prevent abuse, fix errors and meet legal obligations.</li>
    </ul>
    <p>We do not use personal information for behavioural advertising, we do not build marketing profiles, and we do not use children's information to train artificial intelligence models.</p>

    <h2>4. Advertising</h2>
    <p>Children never see ads. Teens and adults on the free plan can choose to watch a rewarded ad (Google AdMob) to get a second chance in a game. These ads are requested as <strong>non-personalized</strong> and limited to general-audience content; for teens they are also flagged as intended for users under the age of consent. The app does not use the advertising identifier for tracking across other companies' apps or websites.</p>

    <h2>5. Service providers</h2>
    <p>We share personal information only with service providers that process it on our behalf, under contract, for the purposes above:</p>
    <table>
        <thead><tr><th>Provider</th><th>Purpose</th><th>Data</th></tr></thead>
        <tbody>
            <tr><td>Hetzner Online GmbH (hosting)</td><td>Servers and backups</td><td>All data stored by the service (encrypted backups)</td></tr>
            <tr><td>Apple, Google, Meta (Facebook)</td><td>Optional sign-in for adults</td><td>Account identifier and email shared by the provider</td></tr>
            <tr><td>Apple App Store, Google Play, RevenueCat</td><td>Subscriptions</td><td>Family identifier and subscription status</td></tr>
            <tr><td>Google AdMob</td><td>Rewarded ads (teens and adults on the free plan only)</td><td>Ad request data from the device; a random reward reference</td></tr>
            <tr><td>Expo (push notifications), Apple Push Notification service, Firebase Cloud Messaging</td><td>Notifications</td><td>Push token and the notification text (never the text of chat messages)</td></tr>
            <tr><td>Sentry</td><td>Error monitoring</td><td>Technical error details with personal data removed</td></tr>
        </tbody>
    </table>
    <p>We may also disclose information when required by law, or to protect the safety of users. If the business is transferred, information would be transferred under this policy and you would be notified.</p>

    <h2>6. Where information is stored</h2>
    <p>Our servers are operated by Hetzner <span class="review">[confirm data-centre location: Germany/Finland (EU) or the United States]</span>. Some service providers above process information in the United States or other countries. Information processed outside your province or country may be accessible to authorities there. <span class="review">[Lawyer review: Quebec Law 25 privacy impact assessment for transfers outside Quebec.]</span></p>

    <h2>7. Children's privacy and parental consent</h2>
    <p>Before a child can join, the family admin must confirm that they are the child's parent or legal guardian (or act with their permission) and agree to the processing described here. We record that consent. A parent or guardian can at any time review the child's information in the app, change the nickname or avatar, stop further collection by removing the child from the family (which erases the child's account), or contact us. <span class="review">[Lawyer review: COPPA "verifiable parental consent" method; Quebec: consent of the person having parental authority for minors under 14.]</span></p>

    <h2>8. Retention</h2>
    <ul>
        <li>Account and activity data: as long as the account exists.</li>
        <li>When an account is deleted (or a child is removed from the family), we immediately erase the name, email, password, birth data, avatar, devices, push tokens and linked sign-in accounts. Anonymous game records (points without any name) are kept so that other family members' scores stay correct.</li>
        <li>Family chat messages: 7 days (free plan) or 1 year (Family plan) after they were sent, then deleted automatically; immediately when deleted by their author or a family admin (only a "Message deleted" marker without the text remains until the same deadline), when the author's account is deleted or when the family is deleted.</li>
        <li>Consent records are kept as proof of consent for as long as required by law <span class="review">[confirm period]</span>.</li>
        <li>Encrypted backups are kept for 14 days, then deleted (so deleted data, including chat messages, can remain in a backup for up to 14 days).</li>
        <li>Error reports are kept by Sentry for up to 90 days.</li>
    </ul>

    <h2>9. Your rights</h2>
    <p>Depending on where you live you can: access your information and get a copy (in the app: Settings → Download my data), correct it, delete your account (Settings → Delete account), withdraw consent, and ask questions about how we use it. Under Quebec law you can also ask for your information in a structured, commonly used technological format, and complain to the Commission d'accès à l'information du Québec; elsewhere in Canada, to the Office of the Privacy Commissioner of Canada. Parents and guardians exercise these rights for their children.</p>

    <h2>10. Security</h2>
    <p>Data is encrypted in transit (HTTPS). Passwords are hashed, access tokens are stored as hashes and each device has its own token that can be revoked. Family chat messages are encrypted at rest. Backups are encrypted. Access to the administration tools is limited to authorized staff. No method is perfectly secure; if a breach creates a risk of serious harm we will notify you and the authorities as the law requires.</p>

    <h2>11. Changes</h2>
    <p>When we change this policy in a meaningful way we publish a new version with a summary of the changes, and the app asks adults and teens to accept it again.</p>

    <h2>12. Contact</h2>
    <p>Person in charge of the protection of personal information: {{ $contact['privacy_officer'] }}<br>
        Email: <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a><br>
        Mail: {{ $contact['company'] }}, {{ $contact['address'] }}</p>
@endsection
