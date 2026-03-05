import AppLayout from '@/layouts/app-layout';
import { Head, useForm, router } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Settings, Users, CheckCircle, XCircle, Globe } from 'lucide-react';
import { toast } from 'sonner';

interface Settings {
    default_subscription_days: string;
    global_expiry_date: string | null;
}

interface Summary {
    total_users: number;
    active_users: number;
    expired_users: number;
    inactive_users: number;
}

interface Props {
    settings: Settings;
    summary: Summary;
}

export default function SubscriptionSettingsIndex({ settings, summary }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        default_subscription_days: settings.default_subscription_days ?? '365',
        global_expiry_date: settings.global_expiry_date ?? '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/subscription-settings', {
            onSuccess: () => toast.success('Settings saved successfully'),
            onError: () => toast.error('Failed to save settings'),
        });
    };

    const handleApplyGlobalExpiry = () => {
        if (!data.global_expiry_date) {
            toast.error('Please set a global expiry date first and save settings.');
            return;
        }
        if (!confirm(`This will update ALL active subscriptions to expire on ${data.global_expiry_date}. Are you sure?`)) return;
        router.post('/admin/subscription-settings/apply-global-expiry', {}, {
            onSuccess: () => toast.success('Global expiry applied to all active subscriptions'),
            onError: () => toast.error('Failed to apply global expiry'),
        });
    };

    return (
        <AppLayout>
            <Head title="Subscription Settings" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-bold">Subscription Settings</h1>
                    <p className="text-muted-foreground">Configure global subscription defaults and expiry policies</p>
                </div>

                {/* Summary cards */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Users</CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{summary.total_users}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Active Subscriptions</CardTitle>
                            <CheckCircle className="h-4 w-4 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">{summary.active_users}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Expired</CardTitle>
                            <XCircle className="h-4 w-4 text-yellow-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-yellow-600">{summary.expired_users}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Inactive</CardTitle>
                            <XCircle className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-muted-foreground">{summary.inactive_users}</div>
                        </CardContent>
                    </Card>
                </div>

                {/* Settings form */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Settings className="h-5 w-5" />
                            Global Configuration
                        </CardTitle>
                        <CardDescription>These settings apply to all PIN and manual subscriptions</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6 max-w-xl">
                            <div className="space-y-2">
                                <Label htmlFor="default_subscription_days">Default Subscription Duration (days)</Label>
                                <Input
                                    id="default_subscription_days"
                                    type="number"
                                    min={1}
                                    max={3650}
                                    value={data.default_subscription_days}
                                    onChange={e => setData('default_subscription_days', e.target.value)}
                                />
                                {errors.default_subscription_days && (
                                    <p className="text-xs text-destructive">{errors.default_subscription_days}</p>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    When a subscription is activated via PIN or manually, it will last this many days from today.
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="global_expiry_date">Global Expiry Date (optional)</Label>
                                <Input
                                    id="global_expiry_date"
                                    type="date"
                                    value={data.global_expiry_date ?? ''}
                                    onChange={e => setData('global_expiry_date', e.target.value)}
                                />
                                {errors.global_expiry_date && (
                                    <p className="text-xs text-destructive">{errors.global_expiry_date}</p>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    If set, new PIN/manual subscriptions will expire on this date instead of using the duration above.
                                    Leave empty to use the duration-based setting.
                                </p>
                            </div>

                            <div className="flex gap-3">
                                <Button type="submit" disabled={processing}>
                                    Save Settings
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Bulk action */}
                <Card className="border-orange-200 dark:border-orange-800">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2 text-orange-700 dark:text-orange-400">
                            <Globe className="h-5 w-5" />
                            Bulk Actions
                        </CardTitle>
                        <CardDescription>Apply the global expiry date to all currently active subscriptions at once</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {settings.global_expiry_date ? (
                            <div className="flex items-center gap-4">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        Current global expiry: <span className="font-semibold text-foreground">{settings.global_expiry_date}</span>
                                    </p>
                                    <p className="text-xs text-muted-foreground mt-1">
                                        Clicking below will update <strong>{summary.active_users}</strong> active subscription(s) to expire on this date.
                                    </p>
                                </div>
                                <Button variant="outline" className="border-orange-400 text-orange-700 hover:bg-orange-50" onClick={handleApplyGlobalExpiry}>
                                    Apply to All Active Users
                                </Button>
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">No global expiry date is set. Set one above and save to use this action.</p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
