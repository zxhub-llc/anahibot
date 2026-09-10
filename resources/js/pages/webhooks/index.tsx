import { Head, router, usePage } from '@inertiajs/react';
import { store, destroy } from '@/routes/webhooks';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { useState } from 'react';

const AVAILABLE_EVENTS = [
    'extraction.started',
    'extraction.completed',
    'extraction.failed',
    'document.received',
    'document.processed',
    'document.failed',
];

interface WebhookEndpoint {
    id: number;
    url: string;
    events: string[];
    is_active: boolean;
    created_at: string;
}

export default function Index({ webhooks }: { webhooks: WebhookEndpoint[] }) {
    const { currentTeam } = usePage().props;
    const teamSlug = currentTeam?.slug ?? '';

    const [url, setUrl] = useState('');
    const [events, setEvents] = useState<string[]>([]);

    function toggleEvent(event: string) {
        setEvents((current) => (current.includes(event) ? current.filter((item) => item !== event) : [...current, event]));
    }

    function handleCreate(event: React.FormEvent) {
        event.preventDefault();
        if (!teamSlug) return;

        router.post(
            store({ current_team: teamSlug }).url,
            { url, events },
            { preserveScroll: true }
        );
    }

    return (
        <>
            <Head title="Webhooks" />

            <div className="space-y-6 p-6">
                <h1 className="text-2xl font-semibold">Webhooks</h1>

                <Card>
                    <CardContent className="space-y-4 pt-6">
                        <form onSubmit={handleCreate} className="space-y-4">
                            <Input
                                placeholder="https://tu-app.com/webhooks/fiscal-extraction"
                                value={url}
                                onChange={(event) => setUrl(event.target.value)}
                                required
                            />
                            <div className="grid grid-cols-2 gap-2">
                                {AVAILABLE_EVENTS.map((event) => (
                                    <label key={event} className="flex items-center gap-2 text-sm">
                                        <Checkbox checked={events.includes(event)} onCheckedChange={() => toggleEvent(event)} />
                                        {event}
                                    </label>
                                ))}
                            </div>
                            <Button type="submit" disabled={!teamSlug}>Registrar endpoint</Button>
                        </form>
                    </CardContent>
                </Card>

                <div className="space-y-3">
                    {webhooks.map((webhook) => (
                        <Card key={webhook.id}>
                            <CardContent className="flex items-center justify-between pt-6">
                                <div>
                                    <div className="font-medium">{webhook.url}</div>
                                    <div className="mt-1 flex flex-wrap gap-1">
                                        {webhook.events.map((event) => (
                                            <Badge key={event} variant="outline">
                                                {event}
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    disabled={!teamSlug}
                                    onClick={() => {
                                        if (!teamSlug) return;
                                        router.delete(
                                            destroy({ current_team: teamSlug, webhook: webhook.id }).url,
                                            { preserveScroll: true }
                                        );
                                    }}
                                >
                                    Eliminar
                                </Button>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}