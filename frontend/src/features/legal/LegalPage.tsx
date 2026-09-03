import { Link } from "react-router-dom";
import { ArrowLeft } from "lucide-react";
import { BrandMark } from "@/components/BrandMark";

/*
 * Privacy Policy and Terms of Service.
 *
 * EDIT THESE before launch: set your real company name, contact address and
 * effective date below, and have the text reviewed by legal counsel. The
 * content is a professional starting point, not legal advice.
 */
const COMPANY = "Intelligent ERP";
const CONTACT_EMAIL = "contact@intelligent-erp.tn"; // replace with your real address
const EFFECTIVE_DATE = "3 September 2026";
const JURISDICTION = "Tunisia";

type Block = { h: string; p: string[] };

function Doc({ title, intro, blocks }: { title: string; intro: string; blocks: Block[] }) {
  return (
    <div style={{ minHeight: "100vh", background: "var(--bg)", color: "var(--text-body)" }}>
      <header
        style={{
          display: "flex",
          alignItems: "center",
          justifyContent: "space-between",
          padding: "18px 24px",
          borderBottom: "1px solid var(--border)",
        }}
      >
        <Link to="/welcome" aria-label="Home">
          <BrandMark size="sm" />
        </Link>
        <Link
          to="/welcome"
          className="inline-flex items-center gap-1.5"
          style={{ fontSize: 13, color: "var(--text-muted)" }}
        >
          <ArrowLeft size={15} /> Back to site
        </Link>
      </header>

      <main style={{ maxWidth: 760, margin: "0 auto", padding: "48px 24px 96px" }}>
        <h1 style={{ fontSize: 30, fontWeight: 700, color: "var(--text-strong)", letterSpacing: "-0.02em" }}>
          {title}
        </h1>
        <p style={{ marginTop: 8, fontSize: 13, color: "var(--text-faint)" }}>
          Effective date: {EFFECTIVE_DATE}
        </p>
        <p style={{ marginTop: 24, fontSize: 15, lineHeight: 1.7 }}>{intro}</p>

        {blocks.map((b) => (
          <section key={b.h} style={{ marginTop: 32 }}>
            <h2 style={{ fontSize: 18, fontWeight: 600, color: "var(--text-strong)" }}>{b.h}</h2>
            {b.p.map((para, i) => (
              <p key={i} style={{ marginTop: 10, fontSize: 15, lineHeight: 1.7 }}>
                {para}
              </p>
            ))}
          </section>
        ))}

        <footer style={{ marginTop: 48, paddingTop: 20, borderTop: "1px solid var(--border)", fontSize: 13, color: "var(--text-faint)" }}>
          <p>
            Questions about this document? Contact us at{" "}
            <a href={`mailto:${CONTACT_EMAIL}`} style={{ color: "var(--emerald-400)" }}>
              {CONTACT_EMAIL}
            </a>
            .
          </p>
          <p style={{ marginTop: 10, display: "flex", gap: 18 }}>
            <Link to="/privacy" style={{ color: "var(--text-muted)" }}>Privacy Policy</Link>
            <Link to="/terms" style={{ color: "var(--text-muted)" }}>Terms of Service</Link>
          </p>
        </footer>
      </main>
    </div>
  );
}

export function PrivacyPage() {
  return (
    <Doc
      title="Privacy Policy"
      intro={`This Privacy Policy explains how ${COMPANY} ("we", "us") handles information when you use our enterprise resource planning software (the "Service"). We designed the Service so that your business data stays under your control.`}
      blocks={[
        {
          h: "1. Information we process",
          p: [
            "Account information: the name, email address and role of each user you create so they can sign in and so we can apply access permissions.",
            "Business data you enter: the records you manage in the Service, such as products, customers, suppliers, sales, purchases, inventory, accounting entries and payroll. This data is entered and owned by you.",
            "Technical data: basic logs needed to operate and secure the Service, such as authentication events and error reports.",
          ],
        },
        {
          h: "2. How we use information",
          p: [
            "We use this information only to provide, secure and support the Service: to authenticate users, apply their permissions, keep an audit trail of changes, and diagnose problems.",
            "We do not sell your data, and we do not use your business data to train external models.",
          ],
        },
        {
          h: "3. The AI assistant and local processing",
          p: [
            "The Service includes an assistant powered by a language model that runs locally within your deployment. Requests to the assistant are processed on your own infrastructure and are not sent to a third-party AI provider.",
            "The assistant acts through the same permissions as the signed-in user and asks for confirmation before performing any action that changes data.",
          ],
        },
        {
          h: "4. Storage and security",
          p: [
            "Data is stored in your database. Access is protected by authentication (signed tokens) and a role-based permission system, and every change is recorded in an audit trail.",
            "You are responsible for securing the servers on which you deploy the Service, including backups and access control.",
          ],
        },
        {
          h: "5. Sharing with third parties",
          p: [
            "We share data with third parties only where you enable a feature that requires it - for example a payment gateway when you take an online payment, or the national e-invoicing platform when you file an electronic invoice. In those cases only the data needed for that operation is transmitted.",
            "We do not otherwise disclose your data except where required by law.",
          ],
        },
        {
          h: "6. Retention",
          p: [
            "Business and accounting records are retained for as long as you keep them in the Service, and for the periods required by applicable tax and commercial law. You can export or delete records subject to those legal retention rules.",
          ],
        },
        {
          h: "7. Your rights",
          p: [
            "Depending on your jurisdiction, you may have the right to access, correct, export or request deletion of personal data held about you. Contact us to exercise these rights.",
          ],
        },
        {
          h: "8. Cookies and local storage",
          p: [
            "The Service uses your browser's local storage to keep you signed in and to remember interface preferences such as your language and theme. It does not use third-party advertising or tracking cookies.",
          ],
        },
        {
          h: "9. Changes",
          p: [
            "We may update this policy as the Service evolves. Material changes will be reflected here with a new effective date.",
          ],
        },
      ]}
    />
  );
}

export function TermsPage() {
  return (
    <Doc
      title="Terms of Service"
      intro={`These Terms govern your use of ${COMPANY} (the "Service"). By creating an account or using the Service, you agree to these Terms.`}
      blocks={[
        {
          h: "1. The Service",
          p: [
            "The Service is enterprise management software that helps you run sales, purchasing, inventory, accounting, payroll, treasury and related operations, together with an assistant. It is a tool to support your work; it does not replace professional accounting, tax or legal advice.",
          ],
        },
        {
          h: "2. Accounts and responsibilities",
          p: [
            "You are responsible for the accounts you create, for keeping credentials confidential, and for the activity that happens under those accounts. Assign roles and permissions so that each user has only the access they need.",
          ],
        },
        {
          h: "3. Acceptable use",
          p: [
            "You agree not to misuse the Service, including attempting to breach its security, access data you are not authorised to access, or use it for unlawful purposes.",
          ],
        },
        {
          h: "4. Your data and ownership",
          p: [
            "You retain ownership of the data you enter into the Service. You are responsible for the accuracy of that data and for exporting or backing it up as needed.",
          ],
        },
        {
          h: "5. Compliance",
          p: [
            "You are responsible for using the Service in compliance with the laws that apply to your business, including tax, invoicing, social-security and data-protection rules. Configurable settings such as tax rates and fiscal identifiers must be reviewed and validated by you or your accountant.",
          ],
        },
        {
          h: "6. Availability and support",
          p: [
            "We work to keep the Service reliable, but it is provided \"as is\" without a warranty of uninterrupted or error-free operation. Where the Service is self-hosted, availability depends on your own infrastructure.",
          ],
        },
        {
          h: "7. Limitation of liability",
          p: [
            "To the extent permitted by law, we are not liable for indirect or consequential losses, or for loss of data or profits, arising from the use of the Service. Nothing in these Terms limits liability that cannot be limited by law.",
          ],
        },
        {
          h: "8. Termination",
          p: [
            "You may stop using the Service at any time. We may suspend access where these Terms are breached. On termination you remain able to export your data subject to legal retention requirements.",
          ],
        },
        {
          h: "9. Governing law",
          p: [
            `These Terms are governed by the laws of ${JURISDICTION}. Any dispute will be subject to the competent courts of ${JURISDICTION}, unless otherwise agreed in writing.`,
          ],
        },
        {
          h: "10. Changes",
          p: [
            "We may update these Terms as the Service evolves. Continued use after a change means you accept the updated Terms.",
          ],
        },
      ]}
    />
  );
}
