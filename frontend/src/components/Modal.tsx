import {
  type ReactElement,
  type ReactNode,
  useEffect,
  useId,
  useRef,
} from "react";

interface ModalProps {
  title: string;
  onClose: () => void;
  children: ReactNode;
  /** md = forms (max-w-md), lg = wide forms such as recipes (max-w-lg). */
  size?: "md" | "lg";
}

/**
 * The one dialog shell. Every modal in the app renders through this so that
 * backdrop click, Escape, focus placement and the dialog role behave the same
 * everywhere instead of being re-implemented (and diverging) per feature.
 */
export function Modal({
  title,
  onClose,
  children,
  size = "md",
}: ModalProps): ReactElement {
  const titleId = useId();
  const panelRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    // Move focus into the dialog so keyboard users start inside it, without
    // stealing it from a field the form itself decides to focus.
    const panel = panelRef.current;
    if (panel && !panel.contains(document.activeElement)) {
      panel.focus();
    }
  }, []);

  return (
    // biome-ignore lint/a11y/noStaticElementInteractions: backdrop click-to-dismiss; the dialog itself is the semantic element
    <div
      className="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50"
      onClick={(event) => {
        if (event.target === event.currentTarget) onClose();
      }}
      onKeyDown={(event) => {
        // Escape bubbles up from any focused field inside the dialog; the panel
        // itself is focused on mount so the handler is reachable immediately.
        if (event.key === "Escape") onClose();
      }}
    >
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        tabIndex={-1}
        className={`bg-white rounded-2xl w-full p-6 shadow-xl max-h-[90vh] overflow-y-auto outline-none ${
          size === "lg" ? "max-w-lg" : "max-w-md"
        }`}
      >
        <h3 id={titleId} className="text-xl font-bold text-stone-800 mb-4">
          {title}
        </h3>
        {children}
      </div>
    </div>
  );
}
