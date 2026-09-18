import {
    Alert02Icon,
    ArrowRight01Icon,
    InformationCircleIcon,
    Notification02Icon,
} from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';

import type { DashboardSriNotification } from '@/types/dashboard';

type Props = {
    notifications?: DashboardSriNotification[];
};

function getNotificationIcon(type: DashboardSriNotification['type']) {
    if (type === 'danger') {
        return Alert02Icon;
    }

    if (type === 'warning') {
        return Alert02Icon;
    }

    if (type === 'success') {
        return Notification02Icon;
    }

    return InformationCircleIcon;
}

export function SriNotificationsCard({
    notifications = [],
}: Props) {
    return (
        <div className="rounded-2xl border bg-card shadow-sm">
            <div className="flex items-center justify-between border-b p-5">
                <div>
                    <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                        SRI
                    </p>

                    <h2 className="mt-1 text-lg font-semibold">
                        Notificaciones
                    </h2>
                </div>

                <HugeiconsIcon
                    icon={Notification02Icon}
                    className="size-5 text-muted-foreground"
                />
            </div>

            {notifications.length > 0 ? (
                <div className="divide-y">
                    {notifications.map((notification) => {
                        const Icon = getNotificationIcon(
                            notification.type,
                        );

                        return (
                            <div
                                key={notification.id}
                                className="flex gap-3 p-4 transition-colors hover:bg-muted/40"
                            >
                                <div className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted">
                                    <HugeiconsIcon
                                        icon={Icon}
                                        className="size-4"
                                    />
                                </div>

                                <div className="min-w-0 flex-1">
                                    <div className="flex items-start justify-between gap-3">
                                        <p className="text-sm font-medium">
                                            {notification.title}
                                        </p>

                                        {!notification.read && (
                                            <span className="mt-1 size-2 shrink-0 rounded-full bg-primary" />
                                        )}
                                    </div>

                                    {notification.description && (
                                        <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                            {notification.description}
                                        </p>
                                    )}

                                    {notification.date && (
                                        <p className="mt-2 text-[11px] text-muted-foreground">
                                            {notification.date}
                                        </p>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            ) : (
                <div className="flex min-h-[220px] flex-col items-center justify-center p-6 text-center">
                    <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                        <HugeiconsIcon
                            icon={Notification02Icon}
                            className="size-5 text-muted-foreground"
                        />
                    </div>

                    <p className="mt-4 text-sm font-medium">
                        No hay notificaciones
                    </p>

                    <p className="mt-1 max-w-xs text-xs leading-5 text-muted-foreground">
                        Las comunicaciones y alertas obtenidas desde el SRI
                        aparecerán aquí.
                    </p>
                </div>
            )}

            <div className="border-t p-4">
                <button
                    type="button"
                    className="flex w-full items-center justify-center gap-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                >
                    Ver todas las notificaciones

                    <HugeiconsIcon
                        icon={ArrowRight01Icon}
                        className="size-4"
                    />
                </button>
            </div>
        </div>
    );
}