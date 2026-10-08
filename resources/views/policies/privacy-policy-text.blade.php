{{--
    The privacy policy wording, and ONLY the wording — replace this file when
    the NRCS Legal department provides the final text. Shown by
    pages/privacy-policy.blade.php (route privacy-policy), above the
    Data Protection Officer's contact box.

    $isDraft: set to false once the final text is in; it removes the draft banner.
--}}
@php
    $isDraft = true;
    $lastUpdated = '8 October 2026';
@endphp

@if($isDraft)
    <div class="mb-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="note">
        <i class="fas fa-triangle-exclamation mr-1 text-amber-500"></i>
        Draft — the final text will be provided by the NRCS Legal department.
    </div>
@endif

<div class="text-gray-700 leading-relaxed space-y-4">
    <p class="text-sm text-gray-500">Last updated: {{ $lastUpdated }}</p>

    <p class="text-lg font-semibold text-gray-900">
        Privacy Policy — Nigerian Red Cross Society membership and volunteer database
    </p>

    <h2 class="text-xl font-bold text-gray-900 pt-4">Who we are</h2>
    <p>
        The Nigerian Red Cross Society (NRCS) keeps this database to manage its members and volunteers.
        NRCS is responsible for the personal data stored in it.
    </p>

    <h2 class="text-xl font-bold text-gray-900 pt-4">What we collect</h2>
    <p>
        When you register, we collect your name, gender, year of birth, contact details (phone, email and
        address), your branch, division and Red Cross unit, and some optional information such as your
        occupation and education. If you choose to, we also collect your National Identification Number (NIN),
        a photo and your signature. We also record your trainings, activities, membership payments and donations.
    </p>

    <h2 class="text-xl font-bold text-gray-900 pt-4">Why we collect it</h2>
    <p>We use your data to:</p>
    <ul class="list-disc pl-6 space-y-1">
        <li>register and manage you as a member or volunteer</li>
        <li>organise trainings, activities and emergency response</li>
        <li>print your Red Cross ID card, which shows your name, photo, signature and NIN</li>
        <li>contact you about Red Cross work by SMS or email</li>
        <li>record membership fees and donations</li>
        <li>produce statistics, for example how many volunteers we have in each age group and branch</li>
    </ul>
    <p>We only collect what we need for these purposes.</p>

    <h2 class="text-xl font-bold text-gray-900 pt-4">Legal basis</h2>
    <p>
        We process your data with your consent, which you give when you register, and because it is necessary
        to manage your membership in the Society, in line with the Nigeria Data Protection Act 2023.
    </p>

    <h2 class="text-xl font-bold text-gray-900 pt-4">Who can see your data</h2>
    <p>
        Only authorised Red Cross staff and volunteers who manage membership records can see your data, and
        only for the branch, division or unit they are responsible for. Sensitive data such as your NIN is
        stored encrypted. Your photo and signature are stored in a protected area and are never placed on a
        public web page.
    </p>

    <h2 class="text-xl font-bold text-gray-900 pt-4">Sharing</h2>
    <p>
        We do not sell your data. We share data only with service providers that work on our behalf, such as
        the payment provider that processes online payments and the services that send our SMS and email
        messages, and only as much as they need for that task. We may also disclose data where the law
        requires us to.
    </p>

    <h2 class="text-xl font-bold text-gray-900 pt-4">How long we keep your data</h2>
    <p>
        We keep your data while you are a member or volunteer. If your account is archived, we keep it for
        7 years in case you return or for legal and accounting reasons. After that, your account is anonymized:
        everything that identifies you is permanently removed, and only general information such as gender,
        branch and age group is kept for statistics.
    </p>

    <h2 class="text-xl font-bold text-gray-900 pt-4">Your rights</h2>
    <p>You have the right to:</p>
    <ul class="list-disc pl-6 space-y-1">
        <li>see the data we hold about you (most of it is shown on your profile page)</li>
        <li>correct data that is wrong</li>
        <li>withdraw your consent</li>
        <li>ask us to archive or delete your account</li>
        <li>complain to the Nigeria Data Protection Commission (NDPC)</li>
    </ul>
    <p>To use these rights, contact your branch or the Data Protection Officer below.</p>
</div>
