<x-site.legal-page title="Privacy Policy">

    <p>
        This Privacy Policy explains how {{ config('app.name') }} ("we", "us", "our") collects, uses, stores and
        protects information when you use our website and our AI match-scoring and resume-parsing APIs (the "Service").
        By using the Service you agree to the practices described here.
    </p>

    <h2>Information we collect</h2>
    <ul>
        <li><strong>Account information</strong> — your name, email address, and password (stored only as a secure hash). If you sign in with a social provider, we receive your name, email, and a provider identifier.</li>
        <li><strong>Billing information</strong> — subscriptions and payments are handled by our secure third-party payment processor. We do not see or store your full card details; we retain only subscription status, plan, order references, and renewal dates needed to provision your account.</li>
        <li><strong>API content</strong> — the job descriptions, applications, and resume files (PDF, Word, image, or text) you submit to the API for scoring or parsing, and the structured results returned.</li>
        <li><strong>Usage data</strong> — a log of each API call (endpoint, AI usage consumed, status, timestamp, IP address) used for billing, security, and support.</li>
        <li><strong>Technical data</strong> — standard request metadata such as IP address, browser type, and pages visited.</li>
    </ul>

    <h2>How we use your information</h2>
    <ul>
        <li>To provide, operate, and maintain the Service and process your API requests.</li>
        <li>To manage your account, subscription, and AI usage.</li>
        <li>To respond to your support and contact requests.</li>
        <li>To monitor for abuse, enforce rate limits, and secure the Service.</li>
        <li>To comply with legal obligations.</li>
    </ul>

    <h2>AI processing and third parties</h2>
    <p>
        To generate match scores and parse resumes, the content you submit is processed by third-party AI providers acting
        as our subprocessors. Content is transmitted securely and used only to produce your result. We do not sell your data,
        and we do not use the content you submit to train our own models. We also share limited data with:
    </p>
    <ul>
        <li><strong>Payment processor</strong> — our secure third-party payment provider, for billing and subscription management.</li>
        <li><strong>Infrastructure and email providers</strong> — for hosting and transactional communications.</li>
        <li><strong>Authorities</strong> — where required by law or to protect our rights.</li>
    </ul>

    <h2>Data retention</h2>
    <p>
        We retain account and billing records for as long as your account is active and as required for legal and accounting
        purposes. Uploaded resume files sent through the asynchronous flow are deleted from our servers immediately after
        processing. Usage logs are kept to support billing and security. You may request deletion of your account at any time.
    </p>

    <h2>Security</h2>
    <p>
        We use industry-standard measures to protect your data, including encryption in transit (HTTPS), encryption of
        sensitive credentials at rest, hashed passwords, and access controls. API keys are shown only once at creation and are
        stored in hashed form. No method of transmission or storage is completely secure, but we work to protect your
        information and promptly address any issues.
    </p>

    <h2>Your rights</h2>
    <p>
        Depending on your location, you may have rights to access, correct, export, or delete your personal data, and to
        object to or restrict certain processing. You can manage your profile from your dashboard, or contact us to exercise
        any of these rights.
    </p>

    <h2>Cookies</h2>
    <p>
        We use essential cookies to keep you signed in and to secure forms (session and CSRF protection). We do not use
        cookies to sell your data. reCAPTCHA may be used on our forms to prevent abuse, subject to Google's privacy terms.
    </p>

    <h2>International transfers</h2>
    <p>
        Your data may be processed in countries other than your own, including by our subprocessors. Where required, we rely
        on appropriate safeguards for such transfers.
    </p>

    <h2>Children</h2>
    <p>The Service is not directed to individuals under 16, and we do not knowingly collect their personal data.</p>

    <h2>Changes to this policy</h2>
    <p>
        We may update this Privacy Policy from time to time. Material changes will be reflected by the "Last updated" date
        above, and where appropriate we will notify you.
    </p>

</x-site.legal-page>
