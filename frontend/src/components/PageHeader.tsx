import type { ReactNode } from "react";

interface PageHeaderProps {
  title: string;
  subtitle?: ReactNode;
  /** Right-aligned controls or figures. */
  actions?: ReactNode;
}

/** Title block every page opens with; rendered once per page, not per state. */
export function PageHeader({
  title,
  subtitle,
  actions,
}: PageHeaderProps): React.ReactElement {
  return (
    <header className="flex items-start justify-between mb-6">
      <div>
        <h2 className="text-3xl font-bold text-stone-800">{title}</h2>
        {subtitle && <p className="text-stone-500 mt-1">{subtitle}</p>}
      </div>
      {actions}
    </header>
  );
}
