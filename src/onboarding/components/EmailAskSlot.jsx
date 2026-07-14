/**
 * EmailAskSlot Component
 *
 * Mount point for the 011 email opt-in on the tour's finish stop.
 * Renders nothing until Phase 5 implements the ask — the slot exists
 * now so the finish layout and the Phase 5 work stay decoupled.
 *
 * Phase 5 contract: replace the null render with the email-only
 * opt-in (no segmentation — the tour collects no other data), gated
 * by its own eligibility rules.
 *
 * @package
 * @since 2.2.0
 */

const EmailAskSlot = () => null;

export default EmailAskSlot;
