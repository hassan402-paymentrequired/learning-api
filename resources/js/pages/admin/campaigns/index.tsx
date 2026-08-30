import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search, Power, PowerOff, Trash2, Edit } from 'lucide-react';
import { useState } from 'react';
import admin from '@/routes/admin';
import { toast } from 'sonner';

interface Campaign {
    id: number;
    campaign_type: 'marquee' | 'countdown' | 'popup';
    title: string;
    message: string | null;
    is_active: boolean;
    priority: number;
    countdown_target_at: string | null;
    popup_frequency_days: number | null;
    start_date: string | null;
    end_date: string | null;
    created_at: string;
}

interface Props {
    campaigns: {
        data: Campaign[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        search?: string;
        campaign_type?: string;
        status?: string;
    };
}

const typeLabels: Record<Campaign['campaign_type'], string> = {
    marquee: 'Marquee',
    countdown: 'Countdown',
    popup: 'Popup',
};

export default function CampaignsIndex({ campaigns, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [campaignType, setCampaignType] = useState(filters.campaign_type || 'all');
    const [status, setStatus] = useState(filters.status || 'all');
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [campaignToDelete, setCampaignToDelete] = useState<{ id: number; title: string } | null>(null);

    const handleFilter = () => {
        router.get(admin.campaigns.index().url, {
            search: search || undefined,
            campaign_type: campaignType !== 'all' ? campaignType : undefined,
            status: status !== 'all' ? status : undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleToggleActive = (campaignId: number) => {
        router.post(admin.campaigns.toggleActive({ campaign: campaignId }).url, {}, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => toast.success('Campaign status updated successfully'),
            onError: () => toast.error('Failed to update campaign status'),
        });
    };

    const handleDelete = (campaignId: number, title: string) => {
        setCampaignToDelete({ id: campaignId, title });
        setDeleteDialogOpen(true);
    };

    const confirmDelete = () => {
        if (!campaignToDelete) return;
        router.delete(admin.campaigns.destroy({ campaign: campaignToDelete.id }).url, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Campaign deleted successfully');
                setDeleteDialogOpen(false);
                setCampaignToDelete(null);
            },
            onError: () => toast.error('Failed to delete campaign'),
        });
    };

    return (
        <AppLayout>
            <Head title="Campaigns" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">Campaigns</h1>
                        <p className="text-muted-foreground">Manage the marquee, countdown, and popup shown in the app</p>
                    </div>
                    <Button asChild>
                        <Link href={admin.campaigns.create().url}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Campaign
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>Filter campaigns by search, type, or status</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col lg:flex-row gap-4">
                            <div className="flex-1">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        placeholder="Search campaigns..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        onKeyDown={(e) => e.key === 'Enter' && handleFilter()}
                                        className="pl-9"
                                    />
                                </div>
                            </div>
                            <div className="flex flex-col sm:flex-row gap-2 flex-1 lg:flex-initial">
                                <Select value={campaignType} onValueChange={setCampaignType}>
                                    <SelectTrigger className="w-full sm:w-[180px]">
                                        <SelectValue placeholder="All Types" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Types</SelectItem>
                                        <SelectItem value="marquee">Marquee</SelectItem>
                                        <SelectItem value="countdown">Countdown</SelectItem>
                                        <SelectItem value="popup">Popup</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Select value={status} onValueChange={setStatus}>
                                    <SelectTrigger className="w-full sm:w-[180px]">
                                        <SelectValue placeholder="All Status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Status</SelectItem>
                                        <SelectItem value="active">Active</SelectItem>
                                        <SelectItem value="inactive">Inactive</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button onClick={handleFilter} className="w-full sm:w-auto">Filter</Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {campaigns.data.map((campaign) => (
                        <Card key={campaign.id} className="hover:shadow-lg transition-shadow">
                            <CardHeader>
                                <div className="flex items-start justify-between">
                                    <div className="flex-1">
                                        <CardTitle className="text-lg">{campaign.title}</CardTitle>
                                        <CardDescription className="mt-1">
                                            {typeLabels[campaign.campaign_type]} • Priority {campaign.priority}
                                        </CardDescription>
                                    </div>
                                    {campaign.is_active ? (
                                        <span className="px-2 py-1 text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded">
                                            Active
                                        </span>
                                    ) : (
                                        <span className="px-2 py-1 text-xs bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200 rounded">
                                            Inactive
                                        </span>
                                    )}
                                </div>
                            </CardHeader>
                            <CardContent>
                                {campaign.message && (
                                    <p className="text-sm text-muted-foreground line-clamp-2 mb-2">{campaign.message}</p>
                                )}
                                {campaign.campaign_type === 'countdown' && campaign.countdown_target_at && (
                                    <p className="text-xs text-muted-foreground">
                                        Target: {new Date(campaign.countdown_target_at).toLocaleString()}
                                    </p>
                                )}
                                {campaign.campaign_type === 'popup' && campaign.popup_frequency_days && (
                                    <p className="text-xs text-muted-foreground">
                                        Every {campaign.popup_frequency_days} day{campaign.popup_frequency_days > 1 ? 's' : ''}
                                    </p>
                                )}
                            </CardContent>
                            <CardContent className="pt-0">
                                <div className="flex items-center justify-between">
                                    <div className="flex gap-2">
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={admin.campaigns.edit({ campaign: campaign.id }).url}>
                                                <Edit className="h-4 w-4" />
                                            </Link>
                                        </Button>
                                        <Button variant="outline" size="sm" onClick={() => handleToggleActive(campaign.id)}>
                                            {campaign.is_active ? <PowerOff className="h-4 w-4" /> : <Power className="h-4 w-4" />}
                                        </Button>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="text-red-600"
                                        onClick={() => handleDelete(campaign.id, campaign.title)}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {campaigns.data.length === 0 && (
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-center py-8">
                                <p className="text-muted-foreground">No campaigns found.</p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {campaigns.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            Showing {((campaigns.current_page - 1) * campaigns.per_page) + 1} to{' '}
                            {campaigns.total} campaigns
                        </p>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={campaigns.current_page === 1}
                                onClick={() => router.get(admin.campaigns.index().url, { ...filters, page: campaigns.current_page - 1 })}
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={campaigns.current_page === campaigns.last_page}
                                onClick={() => router.get(admin.campaigns.index().url, { ...filters, page: campaigns.current_page + 1 })}
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}

                <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete Campaign</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to delete "{campaignToDelete?.title}"? This action cannot be undone.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setDeleteDialogOpen(false)}>
                                Cancel
                            </Button>
                            <Button variant="destructive" onClick={confirmDelete}>
                                Delete
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
