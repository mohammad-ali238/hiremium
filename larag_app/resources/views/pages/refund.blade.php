<x-site.legal-page title="Refund Policy" updated="August 2026">

    <p>
        This Refund Policy explains when {{ config('app.name') }} refunds AI usage and subscription payments. It forms part
        of our <a href="{{ route('terms') }}">Terms of Service</a>. In short: <strong>every failed AI request is refunded
        automatically</strong>, and <strong>subscription fees are not refundable</strong> — because our Free plan lets you
        test the full product before you ever pay.
    </p>

    <h2>Failed AI requests are always refunded</h2>
    <p>
        You only pay for results. Each CV analysed against a job uses AI usage from your balance. If that request fails
        for any reason on our side, the usage it consumed is <strong>credited back to your balance automatically</strong> —
        no ticket, no waiting, no questions asked.
    </p>
    <ul>
        <li><strong>What counts as a failed request:</strong> an AI provider error or timeout, an unreadable or unparseable
            AI response, a processing error in our pipeline, or any other case where we could not produce a result for that
            CV. It also covers files that could not be read or parsed after the request was charged.</li>
        <li><strong>What does not count as failed:</strong> a completed analysis whose outcome you disagree with. A low match
            score, a "not a fit" verdict, or reasoning you find unhelpful is still a completed request — the AI did the work
            you asked for. Duplicate uploads of the same CV to the same job are detected before any usage is charged.</li>
        <li><strong>When it happens:</strong> immediately. The refund is applied by the same process that recorded the failure,
            usually within seconds, and the file is marked as failed in your batch so you can retry it.</li>
        <li><strong>Where you can see it:</strong> the failed file shows its reason in the batch view, and your
            <a href="{{ route('app.usage') }}">AI usage page</a> reflects the restored balance. Every charge and refund is
            logged, and that history is kept even if you later delete the job or batch.</li>
        <li><strong>Retrying:</strong> failed files can be re-run from the batch page. A retry is a new request and is charged
            (and, if it fails again, refunded) in exactly the same way.</li>
        <li><strong>Our own testing mode</strong> and any request that is stopped before it reaches the AI (for example,
            because your balance ran out) are never charged in the first place.</li>
    </ul>

    <h2>Subscription payments are not refundable</h2>
    <p>
        We do not refund subscription fees, in whole or in part, and we do not refund unused AI usage — with the single
        exception of where a refund is required by law. This applies to monthly and yearly plans alike, and to renewals.
    </p>
    <p>
        We take this position deliberately, and we think it is the fair one, because <strong>you never have to pay to find
        out whether {{ config('app.name') }} works for you</strong>:
    </p>
    <ul>
        <li>The <a href="{{ route('pricing') }}">Free plan</a> includes the same features as the paid plans — the same bulk
            upload, the same AI screening, the same match scores, reasons and reports, the same candidate tools. Paid plans
            differ only in limits: more AI usage, more jobs, more CVs per upload and more seats.</li>
        <li>Because the Free plan is a real, complete version of the product and not a limited demo, you can upload real
            CVs to a real job and judge the quality of the results yourself, at no cost, for as long as you like.</li>
        <li>Upgrading is therefore an informed decision about capacity, not a gamble on whether the product is any good.
            That is why we ask everyone to test on the Free plan first, and why we do not offer refunds afterwards.</li>
    </ul>

    <h2>Cancelling a subscription</h2>
    <ul>
        <li>You can cancel at any time from the receipt or customer-portal link in your payment confirmation email.</li>
        <li>Cancelling stops all future renewals. Your access and remaining AI usage continue until the end of the paid
            period you have already paid for; nothing is refunded for the remainder of that period.</li>
        <li>Unused monthly or yearly AI usage does not roll over and is not converted to a refund when a plan ends or changes.</li>
    </ul>

    <h2>Top-up packs</h2>
    <p>
        Top-up packs are one-off purchases of additional AI usage. They are not refundable once purchased, but they
        <strong>never expire</strong> and are used only after your plan's included usage — so a pack you buy today is still
        available on any future plan.
    </p>

    <h2>Billing mistakes</h2>
    <p>
        The no-refund rule is about product performance, not about our errors. If you were charged twice for the same
        period, charged after a confirmed cancellation, or billed an amount that does not match the price you agreed to,
        <a href="{{ route('contact') }}">contact us</a> with the receipt and we will correct it — including a refund of the
        wrongly charged amount.
    </p>

    <h2>Your statutory rights</h2>
    <p>
        Nothing in this policy limits rights you have under applicable consumer-protection law that cannot be excluded by
        agreement. Where such law requires a refund, we will provide it.
    </p>

    <h2>Questions</h2>
    <p>
        If anything here is unclear, or you believe a request was charged when it should have been refunded,
        <a href="{{ route('contact') }}">get in touch</a> from the email address on your account and we will look into it.
    </p>

</x-site.legal-page>
