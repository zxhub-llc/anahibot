import {
    Call02Icon,
    CheckmarkCircle02Icon,
    Mail01Icon,
    UserIcon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';

import type {
    DashboardSriProfile,
    DashboardSriStatus,
} from '@/types/dashboard';

type Props = {
    profile: DashboardSriProfile;
    status?: DashboardSriStatus | null;
};

export function SriProfileCard({ profile, status }: Props) {
    return (
        <div className="rounded-2xl border bg-card shadow-sm">
            <div className="flex items-start justify-between gap-4 border-b p-5">
                <div>
                    <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                        Información tributaria
                    </p>

                    <h2 className="mt-1 text-lg font-semibold">
                        Datos del SRI
                    </h2>
                </div>

                {status?.configured ? (
                    <div className="flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-medium text-emerald-600">
                        <HugeiconsIcon
                            icon={CheckmarkCircle02Icon}
                            className="size-3.5"
                        />
                        Conectado
                    </div>
                ) : (
                    <div className="rounded-full bg-amber-500/10 px-2.5 py-1 text-xs font-medium text-amber-600">
                        Sin conectar
                    </div>
                )}
            </div>

            <div className="space-y-5 p-5">
                <div>
                    <p className="text-xs text-muted-foreground">
                        Contribuyente
                    </p>

                    <p className="mt-1 text-xl font-semibold">
                        {profile.business_name ?? '—'}
                    </p>

                    <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                        RUC {profile.ruc ?? '—'}
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="flex gap-3">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted">
                            <HugeiconsIcon
                                icon={Mail01Icon}
                                className="size-4"
                            />
                        </div>

                        <div className="min-w-0">
                            <p className="text-xs text-muted-foreground">
                                Correo
                            </p>

                            <p className="truncate text-sm font-medium">
                                {profile.email ?? '—'}
                            </p>
                        </div>
                    </div>

                    <div className="flex gap-3">
                        <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted">
                            <HugeiconsIcon
                                icon={Call02Icon}
                                className="size-4"
                            />
                        </div>

                        <div>
                            <p className="text-xs text-muted-foreground">
                                Teléfono
                            </p>

                            <p className="text-sm font-medium">
                                {profile.phone ?? '—'}
                            </p>
                        </div>
                    </div>
                </div>

                <div className="flex gap-3 border-t pt-4">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted">
                        <HugeiconsIcon
                            icon={UserIcon}
                            className="size-4"
                        />
                    </div>

                    <div>
                        <p className="text-xs text-muted-foreground">
                            Representante legal
                        </p>

                        <p className="text-sm font-medium">
                            {profile.legal_representative ?? '—'}
                        </p>

                        {profile.legal_representative_id && (
                            <p className="font-mono text-xs text-muted-foreground">
                                {profile.legal_representative_id}
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}