import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import {
    User as UserIcon, Mail, Calendar, Activity, Award, Clock, TrendingUp, ArrowLeft,
    Key, CheckCircle, XCircle, Ban, ToggleLeft, ToggleRight, Copy, RefreshCw, Settings2
} from 'lucide-react';
import { Link } from '@inertiajs/react';
import admin from '@/routes/admin';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { toast } from 'sonner';
import { useState } from 'react';

interface User {
    id: number;
    name: string;
    email: string;
    created_at: string;
    exam_attempts_count: number;
    subscription_status: string | null;
    subscription_type: string | null;
    subscription_expires_at: string | null;
}

interface Stats {
    total_attempts: number;
    completed_attempts: number;
    average_score: number;
    total_time_spent: number;
}

interface PracticeHistory {
    id: number;
    exam_title: string;
    exam_type: string;
    status: string;
    score: number;
    correct_answers: number;
    total_questions: number;
    percentage: number;
    started_at: string;
    completed_at: string | null;
}

interface SubjectPerformance {
    subject: string;
    attempts: number;
    avg_score: number;
}

interface SubscriptionPin {
    id: number;
    pin: string;
    status: 'unused' | 'used' | 'cancelled';
    generated_by: string;
    used_at: string | null;
    expires_at: string | null;
    created_at: string;
}

interface Props {
    user: User;
    stats: Stats;
    practiceHistory: PracticeHistory[];
    subjectPerformance: SubjectPerformance[];
    subscriptionPins: SubscriptionPin[];
    flash: { generated_pin?: string } | null;
}

function SubscriptionBadge({ status }: { status: string | null }) {
    if (status === 'active') return <Badge className="bg-green-500 hover:bg-green-600">Active</Badge>;
    if (status === 'cancelled') return <Badge variant="destructive">Cancelled</Badge>;
    return <Badge variant="secondary">Inactive</Badge>;
}

function TypeBadge({ type }: { type: string | null }) {
    if (type === 'pin') return <Badge variant="outline" className="border-blue-400 text-blue-600">PIN</Badge>;
    if (type === 'manual') return <Badge variant="outline" className="border-purple-400 text-purple-600">Manual</Badge>;
    if (type === 'paystack') return <Badge variant="outline" className="border-orange-400 text-orange-600">Paystack</Badge>;
    return <Badge variant="outline">—</Badge>;
}

function PinStatusBadge({ status }: { status: string }) {
    if (status === 'unused') return <Badge className="bg-blue-500 hover:bg-blue-600">Unused</Badge>;
    if (status === 'used') return <Badge className="bg-green-500 hover:bg-green-600">Used</Badge>;
    return <Badge variant="destructive">Cancelled</Badge>;
}

export default function ShowUser({ user, stats, practiceHistory, subjectPerformance, subscriptionPins, flash }: Props) {
    const [copiedPin, setCopiedPin] = useState<string | null>(flash?.generated_pin ?? null);
    const [generatePinOpen, setGeneratePinOpen] = useState(false);
    const [expiryOpen, setExpiryOpen] = useState(false);


    const { data: pinForm, setData: setPinData, post: postPin, processing: pinProcessing, reset: resetPin, errors: pinErrors } = useForm({
        expires_at: '',
        notes: '',
    });

    const { data: expiryForm, setData: setExpiryData, post: postExpiry, processing: expiryProcessing, errors: expiryErrors } = useForm({
        expires_at: '',
    });

    const formatTime = (seconds: number) => {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        if (hours > 0) return `${hours}h ${minutes}m`;
        return `${minutes}m`;
    };

    const copyToClipboard = async (text: string) => {
        console.log(text)
        await navigator.clipboard.writeText(text);
        toast.success(`PIN ${text} copied to clipboard!`);
    };

    const handleGeneratePin = (e: React.FormEvent) => {
        e.preventDefault();
        postPin(`/admin/users/${user.id}/generate-pin`, {
            onSuccess: (page: any) => {
                const newPin = (page as any).props?.flash?.generated_pin;
                if (newPin) {
                    setCopiedPin(newPin);
                    toast.success(`PIN ${newPin} generated! Copy and send it to the user.`);
                }
                setGeneratePinOpen(false);
                resetPin();
            },
            onError: () => toast.error('Failed to generate PIN'),
        });
    };

    const handleToggleSubscription = () => {
        router.post(`/admin/users/${user.id}/toggle-subscription`, {}, {
            onSuccess: () => toast.success('Subscription updated successfully'),
            onError: () => toast.error('Failed to update subscription'),
        });
    };

    const handleSetExpiry = (e: React.FormEvent) => {
        e.preventDefault();
        postExpiry(`/admin/users/${user.id}/set-expiry`, {
            onSuccess: () => {
                toast.success('Expiry date set successfully');
                setExpiryOpen(false);
            },
            onError: () => toast.error('Failed to set expiry date'),
        });
    };

    const handleSetType = (value: string) => {
        router.post(`/admin/users/${user.id}/set-type`, { subscription_type: value }, {
            onSuccess: () => toast.success(`Subscription type set to ${value}`),
            onError: () => toast.error('Failed to update subscription type'),
        });
    };

    const handleCancelPin = (pinId: number) => {
        router.delete(`/admin/users/${user.id}/pins/${pinId}`, {
            onSuccess: () => toast.success('PIN cancelled'),
            onError: () => toast.error('Failed to cancel PIN'),
        });
    };

    const isActive = user.subscription_status === 'active' &&
        user.subscription_expires_at &&
        new Date(user.subscription_expires_at) > new Date();

    return (
        <AppLayout>
            <Head title={`User: ${user.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button asChild variant="outline" size="sm">
                            <Link href={admin.users.index().url}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold">{user.name}</h1>
                            <p className="text-muted-foreground">User profile and practice history</p>
                        </div>
                    </div>
                </div>

                {/* User Info */}
                <Card>
                    <CardHeader>
                        <CardTitle>User Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="flex items-center gap-3">
                                <UserIcon className="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p className="text-sm font-medium">Name</p>
                                    <p className="text-sm text-muted-foreground">{user.name}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Mail className="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p className="text-sm font-medium">Email</p>
                                    <p className="text-sm text-muted-foreground">{user.email}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Calendar className="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p className="text-sm font-medium">Joined</p>
                                    <p className="text-sm text-muted-foreground">
                                        {new Date(user.created_at).toLocaleDateString()}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* ===== SUBSCRIPTION MANAGEMENT ===== */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Settings2 className="h-5 w-5" />
                            Subscription Management
                        </CardTitle>
                        <CardDescription>Manage this user's subscription — generate PINs, toggle activation, or set expiry dates</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">

                        {/* Latest generated PIN callout */}
                        {copiedPin && (
                            <div className="flex items-center justify-between rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950">
                                <div>
                                    <p className="text-sm font-semibold text-blue-800 dark:text-blue-200">Generated PIN</p>
                                    <p className="font-mono text-3xl font-bold tracking-[0.3em] text-blue-900 dark:text-blue-100">{copiedPin}</p>
                                    <p className="mt-1 text-xs text-blue-600 dark:text-blue-300">Send this PIN to {user.name} to activate their subscription</p>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => copyToClipboard(copiedPin)}
                                    className="ml-4 border-blue-300 text-blue-700"
                                >
                                    <Copy className="mr-2 h-4 w-4" />
                                    Copy
                                </Button>
                            </div>
                        )}

                        {/* Status row */}
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="space-y-1">
                                <p className="text-sm font-medium">Status</p>
                                <SubscriptionBadge status={user.subscription_status} />
                            </div>
                            <div className="space-y-1">
                                <p className="text-sm font-medium">Activation Type</p>
                                <TypeBadge type={user.subscription_type} />
                            </div>
                            <div className="space-y-1">
                                <p className="text-sm font-medium">Expires At</p>
                                <p className="text-sm text-muted-foreground">
                                    {user.subscription_expires_at
                                        ? new Date(user.subscription_expires_at).toLocaleDateString()
                                        : '—'}
                                </p>
                            </div>
                        </div>

                        {/* Actions row */}
                        <div className="flex flex-wrap gap-3">

                            {/* Generate PIN button */}
                            <Dialog open={generatePinOpen} onOpenChange={setGeneratePinOpen}>
                                <DialogTrigger asChild>
                                    <Button variant="default" size="sm">
                                        <Key className="mr-2 h-4 w-4" />
                                        Generate PIN
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Generate Subscription PIN</DialogTitle>
                                        <DialogDescription>
                                            A 6-digit PIN will be created for <strong>{user.name}</strong>. Share it with them to activate their subscription.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <form onSubmit={handleGeneratePin} className="space-y-4">
                                        <div className="space-y-2">
                                            <Label>PIN Expiry Date (optional)</Label>
                                            <Input
                                                type="date"
                                                value={pinForm.expires_at}
                                                onChange={e => setPinData('expires_at', e.target.value)}
                                            />
                                            {pinErrors.expires_at && <p className="text-xs text-destructive">{pinErrors.expires_at}</p>}
                                        </div>
                                        <div className="space-y-2">
                                            <Label>Notes (optional)</Label>
                                            <Input
                                                placeholder="e.g. Scholarship student, batch 2026"
                                                value={pinForm.notes}
                                                onChange={e => setPinData('notes', e.target.value)}
                                            />
                                        </div>
                                        <DialogFooter>
                                            <Button type="button" variant="outline" onClick={() => setGeneratePinOpen(false)}>Cancel</Button>
                                            <Button type="submit" disabled={pinProcessing}>
                                                <RefreshCw className={`mr-2 h-4 w-4 ${pinProcessing ? 'animate-spin' : ''}`} />
                                                Generate PIN
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>

                            {/* Toggle subscription */}
                            <Button
                                variant={isActive ? 'destructive' : 'outline'}
                                size="sm"
                                onClick={handleToggleSubscription}
                            >
                                {isActive ? (
                                    <><XCircle className="mr-2 h-4 w-4" /> Cancel Subscription</>
                                ) : (
                                    <><CheckCircle className="mr-2 h-4 w-4" /> Activate Subscription</>
                                )}
                            </Button>

                            {/* Set expiry */}
                            <Dialog open={expiryOpen} onOpenChange={setExpiryOpen}>
                                <DialogTrigger asChild>
                                    <Button variant="outline" size="sm">
                                        <Calendar className="mr-2 h-4 w-4" />
                                        Set Expiry
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Set Subscription Expiry</DialogTitle>
                                        <DialogDescription>
                                            Choose a specific date when {user.name}'s subscription should expire. This will also activate the subscription.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <form onSubmit={handleSetExpiry} className="space-y-4">
                                        <div className="space-y-2">
                                            <Label>Expiry Date</Label>
                                            <Input
                                                type="date"
                                                value={expiryForm.expires_at}
                                                onChange={e => setExpiryData('expires_at', e.target.value)}
                                                required
                                            />
                                            {expiryErrors.expires_at && <p className="text-xs text-destructive">{expiryErrors.expires_at}</p>}
                                        </div>
                                        <DialogFooter>
                                            <Button type="button" variant="outline" onClick={() => setExpiryOpen(false)}>Cancel</Button>
                                            <Button type="submit" disabled={expiryProcessing}>Save Expiry</Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>

                            {/* Change type */}
                            <div className="flex items-center gap-2">
                                <span className="text-sm text-muted-foreground">Type:</span>
                                <Select
                                    defaultValue={user.subscription_type ?? 'paystack'}
                                    onValueChange={handleSetType}
                                >
                                    <SelectTrigger className="w-[130px] h-9">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="paystack">Paystack</SelectItem>
                                        <SelectItem value="pin">PIN</SelectItem>
                                        <SelectItem value="manual">Manual</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* PIN History table */}
                        {subscriptionPins.length > 0 && (
                            <div>
                                <p className="mb-2 text-sm font-medium">PIN History</p>
                                <div className="rounded-md border">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted/50">
                                            <tr>
                                                <th className="p-2 text-left font-medium">PIN</th>
                                                <th className="p-2 text-left font-medium">Status</th>
                                                <th className="p-2 text-left font-medium">Generated By</th>
                                                <th className="p-2 text-left font-medium">Created</th>
                                                <th className="p-2 text-left font-medium">Expires</th>
                                                <th className="p-2 text-left font-medium">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {subscriptionPins.map(pin => (
                                                <tr key={pin.id} className="border-t">
                                                    <td className="p-2 font-mono font-bold tracking-widest">{pin.pin}</td>
                                                    <td className="p-2"><PinStatusBadge status={pin.status} /></td>
                                                    <td className="p-2 text-muted-foreground">{pin.generated_by}</td>
                                                    <td className="p-2 text-muted-foreground">{new Date(pin.created_at).toLocaleDateString()}</td>
                                                    <td className="p-2 text-muted-foreground">{pin.expires_at ? new Date(pin.expires_at).toLocaleDateString() : '—'}</td>
                                                    <td className="p-2">
                                                        <div className="flex gap-2">
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => copyToClipboard(pin.pin)}
                                                                title="Copy PIN"
                                                            >
                                                                <Copy className="h-3 w-3" />
                                                            </Button>
                                                            {pin.status === 'unused' && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="text-destructive hover:text-destructive"
                                                                    onClick={() => handleCancelPin(pin.id)}
                                                                    title="Cancel PIN"
                                                                >
                                                                    <Ban className="h-3 w-3" />
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Statistics */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Attempts</CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_attempts}</div>
                            <p className="text-xs text-muted-foreground">
                                {stats.completed_attempts} completed
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Average Score</CardTitle>
                            <Award className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats?.average_score?.toFixed(1) || 0}%</div>
                            <p className="text-xs text-muted-foreground">
                                Across all attempts
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Time Spent</CardTitle>
                            <Clock className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatTime(stats.total_time_spent)}</div>
                            <p className="text-xs text-muted-foreground">
                                Total practice time
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Completion Rate</CardTitle>
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.total_attempts > 0
                                    ? ((stats.completed_attempts / stats.total_attempts) * 100).toFixed(1)
                                    : 0}%
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Completed attempts
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Practice History and Subject Performance */}
                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Practice History</CardTitle>
                            <CardDescription>Recent practice attempts</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {practiceHistory.length > 0 ? (
                                    practiceHistory.map((attempt) => (
                                        <div key={attempt.id} className="flex items-center justify-between border-b pb-3 last:border-0">
                                            <div>
                                                <p className="text-sm font-medium">{attempt.exam_title}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {attempt.exam_type} • {attempt.status}
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-sm font-medium">{attempt.percentage.toFixed(1)}%</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {attempt.correct_answers}/{attempt.total_questions}
                                                </p>
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-sm text-muted-foreground">No practice history</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Subject Performance</CardTitle>
                            <CardDescription>Average scores by subject</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {subjectPerformance.length > 0 ? (
                                    subjectPerformance.map((subject, index) => (
                                        <div key={index} className="flex items-center justify-between border-b pb-3 last:border-0">
                                            <div>
                                                <p className="text-sm font-medium">{subject.subject}</p>
                                                <p className="text-xs text-muted-foreground">{subject.attempts} attempts</p>
                                            </div>
                                            <p className="text-sm font-medium">{Number(subject.avg_score || 0).toFixed(1)}%</p>
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-sm text-muted-foreground">No subject data available</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
