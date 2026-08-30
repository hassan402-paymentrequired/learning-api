import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { type BreadcrumbItem } from '@/types';
import admin from '@/routes/admin';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Campaigns', href: admin.campaigns.index().url },
    { title: 'Create Campaign', href: '#' },
];

export default function CreateCampaign() {
    const { data, setData, post, processing, errors } = useForm({
        campaign_type: 'marquee' as 'marquee' | 'countdown' | 'popup',
        title: '',
        message: '',
        image: null as File | null,
        link: '',
        link_text: '',
        countdown_target_at: '',
        popup_frequency_days: 1,
        is_active: true,
        priority: 0,
        start_date: '',
        end_date: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(admin.campaigns.store().url, {
            forceFormData: !!data.image,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Campaign" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-bold">Create Campaign</h1>
                    <p className="text-muted-foreground">Set up a marquee, countdown, or popup campaign</p>
                </div>

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Campaign Details</CardTitle>
                        <CardDescription>Only one campaign of each type is shown at a time — the highest-priority active one wins.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="campaign_type">Campaign Type *</Label>
                                <Select
                                    value={data.campaign_type}
                                    onValueChange={(value) => setData('campaign_type', value as typeof data.campaign_type)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a campaign type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="marquee">Marquee (scrolling banner)</SelectItem>
                                        <SelectItem value="countdown">Countdown</SelectItem>
                                        <SelectItem value="popup">Popup (shown on app open)</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.campaign_type} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="title">Title *</Label>
                                <Input
                                    id="title"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    placeholder="e.g., Mock Exam Weekend"
                                    required
                                />
                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="message">Message</Label>
                                <Textarea
                                    id="message"
                                    value={data.message}
                                    onChange={(e) => setData('message', e.target.value)}
                                    placeholder="Optional supporting text"
                                    rows={3}
                                />
                                <InputError message={errors.message} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="image">Image (Optional)</Label>
                                <Input
                                    id="image"
                                    type="file"
                                    accept="image/*"
                                    onChange={(e) => setData('image', e.target.files?.[0] || null)}
                                />
                                {data.image && (
                                    <img
                                        src={URL.createObjectURL(data.image)}
                                        alt="Preview"
                                        className="mt-2 max-w-xs h-auto rounded-lg border border-border"
                                    />
                                )}
                                <InputError message={errors.image} />
                            </div>

                            {data.campaign_type === 'countdown' && (
                                <div className="grid gap-2">
                                    <Label htmlFor="countdown_target_at">Countdown Target Date/Time *</Label>
                                    <Input
                                        id="countdown_target_at"
                                        type="datetime-local"
                                        value={data.countdown_target_at}
                                        onChange={(e) => setData('countdown_target_at', e.target.value)}
                                        required
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Set an End Date below matching this so the countdown retires automatically once it hits zero.
                                    </p>
                                    <InputError message={errors.countdown_target_at} />
                                </div>
                            )}

                            {data.campaign_type === 'popup' && (
                                <div className="grid gap-2">
                                    <Label htmlFor="popup_frequency_days">Show Every N Days *</Label>
                                    <Input
                                        id="popup_frequency_days"
                                        type="number"
                                        min={1}
                                        value={data.popup_frequency_days}
                                        onChange={(e) => setData('popup_frequency_days', parseInt(e.target.value, 10) || 1)}
                                        required
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        1 = once a day, 2 = once every 2 days, etc. Shown the first time a user opens the app in each window.
                                    </p>
                                    <InputError message={errors.popup_frequency_days} />
                                </div>
                            )}

                            <div className="grid gap-2">
                                <Label htmlFor="link">CTA Link (Optional)</Label>
                                <Input
                                    id="link"
                                    value={data.link}
                                    onChange={(e) => setData('link', e.target.value)}
                                    placeholder="https://..."
                                />
                                <InputError message={errors.link} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="link_text">CTA Button Text (Optional)</Label>
                                <Input
                                    id="link_text"
                                    value={data.link_text}
                                    onChange={(e) => setData('link_text', e.target.value)}
                                    placeholder="e.g., Learn more"
                                />
                                <InputError message={errors.link_text} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="start_date">Start Date (Optional)</Label>
                                    <Input
                                        id="start_date"
                                        type="datetime-local"
                                        value={data.start_date}
                                        onChange={(e) => setData('start_date', e.target.value)}
                                    />
                                    <InputError message={errors.start_date} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="end_date">End Date (Optional)</Label>
                                    <Input
                                        id="end_date"
                                        type="datetime-local"
                                        value={data.end_date}
                                        onChange={(e) => setData('end_date', e.target.value)}
                                    />
                                    <InputError message={errors.end_date} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="priority">Priority</Label>
                                <Input
                                    id="priority"
                                    type="number"
                                    value={data.priority}
                                    onChange={(e) => setData('priority', parseInt(e.target.value, 10) || 0)}
                                />
                                <p className="text-xs text-muted-foreground">
                                    If more than one campaign of the same type is active, the highest priority wins.
                                </p>
                                <InputError message={errors.priority} />
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) => setData('is_active', checked === true)}
                                />
                                <Label htmlFor="is_active" className="cursor-pointer">
                                    Active
                                </Label>
                            </div>

                            <div className="flex gap-2 pt-4">
                                <Button type="submit" disabled={processing}>
                                    <Save className="mr-2 h-4 w-4" />
                                    {processing ? 'Creating...' : 'Create Campaign'}
                                </Button>
                                <Button type="button" variant="outline" asChild>
                                    <Link href={admin.campaigns.index().url}>Cancel</Link>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
