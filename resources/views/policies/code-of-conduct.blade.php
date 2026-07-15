{{-- resources/views/policies/code-of-conduct.blade.php --}}

<style>

    .coc-title {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        justify-content: center;
        margin: 0;
    }

    .coc-title-logo {
        height: 44px;        /* smaller than main logo */
        width: auto;
        display: inline-block;
        vertical-align: middle;
    }

    @media (max-width: 640px) {
        .coc-title-logo {
            height: 36px;
        }
    }

    /* Standalone, framework-agnostic styling */
    .coc-wrap {
        max-width: 920px;
        margin: 0 auto;
        padding: 24px 16px 48px;
        font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
        color: #111827; /* gray-900 */
    }

    .coc-card {
        background: #fff;
        border: 1px solid #e5e7eb; /* gray-200 */
        border-radius: 14px;
        box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        padding: 28px 26px;
    }

    .coc-logo {
        display: flex;
        justify-content: center;
        margin: 8px 0 22px;
    }
    .coc-logo img {
        height: 84px;
        width: auto;
        display: block;
    }

    .coc-header {
        text-align: center;
        margin-bottom: 22px;
    }
    .coc-header h1 {
        margin: 0;
        font-size: 28px;
        line-height: 1.2;
        font-weight: 700;
        letter-spacing: -0.01em;
    }
    .coc-header .lead {
        margin: 10px 0 0;
        color: #4b5563; /* gray-600 */
        font-size: 16px;
    }
    .coc-header .hint {
        margin: 8px 0 0;
        color: #6b7280; /* gray-500 */
        font-size: 13px;
    }

    .coc-body h2 {
        margin: 24px 0 10px;
        font-size: 18px;
        font-weight: 700;
        color: #111827;
    }
    .coc-body h3 {
        margin: 18px 0 8px;
        font-size: 15px;
        font-weight: 700;
        color: #111827;
    }

    .coc-body p {
        margin: 10px 0;
        line-height: 1.7;
        color: #1f2937; /* gray-800 */
    }

    .coc-body ol,
    .coc-body ul {
        margin: 10px 0 14px;
        padding-left: 22px;
        line-height: 1.65;
        color: #1f2937;
    }
    .coc-body ol { list-style: decimal; }
    .coc-body ul { list-style: disc; }

    .coc-body li { margin: 6px 0; }

    .coc-divider {
        margin: 22px 0;
        border: none;
        border-top: 1px solid #e5e7eb;
    }

    .coc-summary {
        margin-top: 28px;
        border: 1px solid #fecaca; /* red-200 */
        background: #fef2f2;      /* red-50 */
        border-radius: 12px;
        padding: 18px 18px 16px;
    }
    .coc-summary h2 {
        margin: 0 0 10px;
        font-size: 15px;
        font-weight: 800;
        color: #7f1d1d; /* red-900 */
        letter-spacing: 0.01em;
    }
    .coc-summary ul {
        margin: 0;
        padding-left: 18px;
        color: #7f1d1d;
        font-size: 14px;
    }
    .coc-summary li { margin: 7px 0; }
    .coc-summary p {
        margin: 10px 0 0;
        color: #7f1d1d;
        font-size: 14px;
        line-height: 1.55;
    }
    .coc-summary strong { font-weight: 800; }

    /* Small screens */
    @media (max-width: 640px) {
        .coc-card { padding: 20px 16px; }
        .coc-logo img { height: 72px; }
        .coc-header h1 { font-size: 24px; }
    }

    /* Print-friendly */
    @media print {
        .coc-wrap { max-width: none; padding: 0; }
        .coc-card { border: none; box-shadow: none; padding: 0; }
        .coc-summary { border: 1px solid #bbb; background: #fff; }
    }
</style>

<div class="coc-wrap">

    <article class="coc-card">
        <header class="coc-header">
            <h1 class="coc-title">
                <img
                    src="{{ asset('images/NRCS_logo.jpg') }}"
                    alt="Nigeria Red Cross Society (NRCS) logo"
                    class="coc-title-logo"
                >
                Code of Conduct
            </h1>

            <p class="lead">
                For the Governance, Members, Volunteers and Employees of Nigeria Red Cross Society (NRCS)
            </p>

            <p class="hint">
                A short practical summary is provided at the bottom of this page.
            </p>
        </header>

        <div class="coc-body">
            <section>
                <h2>I. Introduction</h2>
                <p>
                    The Nigeria Red Cross Society (NRCS) is a member of the worldwide Red Cross/Red Crescent Movement
                    and is committed to its Fundamental Principles of Humanity, Impartiality, Neutrality, Independence,
                    Voluntary Service, Unity and Universality.
                </p>

                <p>
                    As outlined in its Statutes, NRCS’ mission is to alleviate the situation of vulnerable people,
                    including those affected by disaster, epidemics and armed conflicts, and the poorest communities in
                    both urban and rural areas — including women, children, the aged, displaced persons, persons living
                    with disabilities, and other vulnerable people.
                </p>

                <p>
                    The credibility and success of NRCS is underpinned not only by the services the Society offers, but
                    also by how the services are offered and by the conduct of those who offer the services. This Code
                    of Conduct applies to all NRCS Governance, Members, Volunteers and Staff. It is designed to protect
                    the integrity, well-being and rights of all involved in the work of the organization, ensure
                    efficient operations, and ensure compliance with the laws of Nigeria, government regulations,
                    established policies and practices, and any other NRCS policies and procedures.
                </p>

                <p>
                    All Governance members are required to sign the Code immediately upon election/appointment.
                    Violations are subject to disciplinary measures in accordance with applicable disciplinary measures
                    or local staff regulations. NRCS reserves the right to recover expenses incurred as a result of any
                    violation. Disciplinary action does not negate criminal proceedings if the law has been violated.
                </p>
            </section>

            <hr class="coc-divider">

            <section>
                <h2>2. Rules of Conduct</h2>

                <h3>(a) General rules</h3>
                <ol>
                    <li>The conduct of all NRCS personnel must be consistent with the Fundamental Principles of the Movement.</li>
                    <li>Governance, Members, Volunteers and Staff must not conduct themselves in any manner that brings NRCS into disrepute.</li>
                    <li>
                        Personnel must respect the dignity of the people with whom they come into contact — especially beneficiaries —
                        and carry out their work mindful that actions can have serious repercussions for human beings.
                    </li>
                    <li>
                        Personnel must not make statements or create circumstances implying sponsorship or support by NRCS of an outside employer
                        or of a political, charitable, civic, religious, or similar organization when such is not the case.
                    </li>
                    <li>
                        Personnel must not solicit or agree to receive money from a client as an inducement to facilitate NRCS work or for personal reasons.
                        Similarly, employees may not seek or accept payment, loans, services, entertainment or other benefits from an individual or
                        representative of any concern doing or seeking to do business with NRCS.
                    </li>
                    <li>
                        Fraud in any form is strictly prohibited. Fraud is defined as any action aimed at obtaining an unauthorized benefit,
                        such as money, goods, services or other personal or commercial advantages, regardless of whether such advantage benefits
                        the person concerned, NRCS, or a third party.
                    </li>
                </ol>

                <h3>(b) Harassment, abuse of power and sexual exploitation</h3>
                <ol>
                    <li>
                        Harassment in any form, including sexual harassment, is strictly prohibited. Harassment refers to a pattern of hostile language
                        or actions expressed or carried out against an employee/volunteer over time. Sexual harassment refers to any sexual or gender-related
                        behavior that is not desired by the victim and that violates their dignity.
                    </li>
                    <li>
                        The purchase of sexual services and the practice of sexual exploitation are prohibited. Sexual exploitation includes abuse of authority,
                        trust or a situation of vulnerability for sexual ends in exchange for money, work, goods or services.
                    </li>
                    <li>
                        Entering into a sexual relationship with a direct beneficiary of NRCS programmes or with their immediate family, or using one’s position
                        to solicit sexual services in exchange for humanitarian services provided by NRCS, is prohibited.
                    </li>
                    <li>
                        Entering into a sexual relationship with a child (under 18) or inciting/forcing a child to take part in activities of a sexual nature —
                        irrespective of consent — is prohibited. This includes pornographic activities (photos, videos, games, etc.), as well as acquiring,
                        storing or circulating documents of a pedophiliac nature, irrespective of the medium used.
                    </li>
                    <li>
                        Abuse, neglect, exploitation and violence against children (under 18) is prohibited. Personnel must ensure children’s well-being is protected
                        at all times, and must prevent and respond to child abuse, neglect, exploitation and violence. In all actions concerning children, the best
                        interests of the child shall be a primary consideration.
                    </li>
                </ol>

                <h3>(c) Photographing/filming a child and using children’s images</h3>
                <p>When photographing or filming a child or using children’s images for work-related purposes, staff, volunteers, contractors and partners must:</p>
                <ul>
                    <li>Assess and endeavor to comply with local traditions or restrictions for reproducing personal images.</li>
                    <li>Obtain informed consent from the child and parent/guardian before photographing/filming, and explain how the image will be used.</li>
                    <li>
                        Ensure images present children in a dignified and respectful manner — not vulnerable or submissive.
                        Children should be adequately clothed and not posed in a sexually suggestive way.
                    </li>
                    <li>Ensure images are honest representations of the context and the facts.</li>
                    <li>
                        Ensure file labels, meta-data or text descriptions do not reveal identifying information about a child when sending images electronically
                        or publishing images in any form.
                    </li>
                </ul>

                <h3>(d) Ethical responsibilities to colleagues</h3>
                <p>Employees must:</p>
                <ul>
                    <li>Treat colleagues with respect, courtesy, fairness and good faith.</li>
                    <li>Cooperate with colleagues to promote NRCS professional interests and concerns.</li>
                    <li>Respect confidences shared by colleagues in professional relationships and transactions.</li>
                    <li>Create and maintain conditions of practice that facilitate ethical and competent professional performance by colleagues.</li>
                    <li>Act with consideration for the interest, character and reputation of any employee that replaces or is replaced.</li>
                    <li>Extend to colleagues of other professions the same respect and cooperation extended to other co-workers.</li>
                </ul>
            </section>

            <hr class="coc-divider">

            <section>
                <h2>3. Protection of Information</h2>
                <ol>
                    <li>
                        Governance, Members, Volunteers and Staff must exercise the utmost discretion in all matters of official business and handle all confidential
                        and sensitive information with the greatest care.
                    </li>
                    <li>
                        Personnel must not disclose sensitive information of individuals we serve where there is a risk of adverse consequences if identities are revealed.
                        All efforts must be made to protect beneficiary identities, including names, faces and geographical locations.
                    </li>
                </ol>
            </section>

            {{-- Highlighted Summary --}}
            <section class="coc-summary" aria-label="Code of Conduct summary">
                <h2>Code of Conduct — Summary</h2>
                <ul>
                    <li><strong>Act with integrity and neutrality</strong> in line with Red Cross principles</li>
                    <li><strong>Respect dignity and safety</strong> of beneficiaries, colleagues, and communities</li>
                    <li><strong>Zero tolerance for abuse of power</strong>, harassment, exploitation, or sexual misconduct</li>
                    <li><strong>Protect children at all times</strong> and follow safeguarding rules</li>
                    <li><strong>Avoid corruption and fraud</strong> — no bribes, inducements, or misuse of resources</li>
                    <li><strong>Respect confidentiality</strong> and protect sensitive personal information</li>
                </ul>
                <p>
                    Serious violations may result in disciplinary action and, where applicable, legal consequences.
                </p>
            </section>

        </div>
    </article>
</div>
