import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Search, Eye, Edit, Trash2, Shield, ShieldOff, MoreVertical } from 'lucide-react';
import { useState } from 'react';
import admin from '@/routes/admin';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { toast } from 'sonner';

interface User {
    id: number;
    name: string;
    email: string;
    created_at: string;
    exam_attempts_count: number;
    subscription_status: string | null;
    subscription_type: string | null;
    subscription_expires_at: string | null;
    is_admin: boolean;
}

interface Props {
    users: {
        data: User[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        search?: string;
        date_from?: string;
        date_to?: string;
    };
}

function SubscriptionBadge({ status, type }: { status: string | null; type: string | null }) {
    if (status === 'active') {
        const color =
            type === 'pin'
                ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                : type === 'manual'
                  ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
                  : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        const label = type === 'pin' ? 'PIN' : type === 'manual' ? 'Manual' : 'Active';
        return (
            <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${color}`}>
                {label}
            </span>
        );
    }
    if (status === 'cancelled') {
        return (
            <span className="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900 dark:text-red-200">
                Cancelled
            </span>
        );
    }
    return (
        <span className="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
            No subscription
        </span>
    );
}

export default function UsersIndex({ users, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [userToDelete, setUserToDelete] = useState<{ id: number; name: string } | null>(null);

    const handleFilter = () => {
        router.get(
            admin.users.index().url,
            {
                search: search || undefined,
                date_from: dateFrom || undefined,
                date_to: dateTo || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    const handleReset = () => {
        setSearch('');
        setDateFrom('');
        setDateTo('');
        router.get(admin.users.index().url, {}, {
            preserveState: false,
        });
    };

    const handleToggleAdmin = (userId: number) => {
        router.post(
            `/admin/users/${userId}/toggle-admin`,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('User admin status updated successfully');
                },
                onError: () => {
                    toast.error('Failed to update user admin status');
                },
            }
        );
    };

    const handleDelete = (userId: number, userName: string) => {
        setUserToDelete({ id: userId, name: userName });
        setDeleteDialogOpen(true);
    };

    const confirmDelete = () => {
        if (userToDelete) {
            router.delete(admin.users.destroy({ user: userToDelete.id }).url, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('User deleted successfully');
                    setDeleteDialogOpen(false);
                    setUserToDelete(null);
                },
                onError: (errors) => {
                    const errorMessage = errors?.user || 'Failed to delete user';
                    toast.error(errorMessage);
                },
            });
        }
    };

    return (
        <AppLayout>
            <Head title="Users" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-bold">Users</h1>
                    <p className="text-muted-foreground">Manage platform users</p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>Search and filter users</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Search</label>
                                <div className="relative">
                                    <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder="Name or email..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="pl-8"
                                        onKeyDown={(e) => e.key === 'Enter' && handleFilter()}
                                    />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Date From</label>
                                <Input
                                    type="date"
                                    value={dateFrom}
                                    onChange={(e) => setDateFrom(e.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Date To</label>
                                <Input
                                    type="date"
                                    value={dateTo}
                                    onChange={(e) => setDateTo(e.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium">&nbsp;</label>
                                <div className="flex gap-2">
                                    <Button onClick={handleFilter} className="flex-1">
                                        Apply
                                    </Button>
                                    <Button onClick={handleReset} variant="outline">
                                        Reset
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>All Users ({users.total})</CardTitle>
                        <CardDescription>
                            {users.data.length > 0
                                ? 'User accounts, subscriptions, and admin access'
                                : 'No users match your filters'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {users.data.length > 0 ? (
                            <>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Name</TableHead>
                                            <TableHead>Email</TableHead>
                                            <TableHead>Role</TableHead>
                                            <TableHead>Subscription</TableHead>
                                            <TableHead className="text-right">Attempts</TableHead>
                                            <TableHead>Joined</TableHead>
                                            <TableHead className="w-[70px] text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {users.data.map((user) => (
                                            <TableRow key={user.id}>
                                                <TableCell className="font-medium">{user.name}</TableCell>
                                                <TableCell className="max-w-[220px] truncate">
                                                    {user.email}
                                                </TableCell>
                                                <TableCell>
                                                    {user.is_admin ? (
                                                        <span className="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                                                            Admin
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground text-sm">User</span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <SubscriptionBadge
                                                        status={user.subscription_status}
                                                        type={user.subscription_type}
                                                    />
                                                </TableCell>
                                                <TableCell className="text-right tabular-nums">
                                                    {user.exam_attempts_count}
                                                </TableCell>
                                                <TableCell>
                                                    {new Date(user.created_at).toLocaleDateString()}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="sm">
                                                                <MoreVertical className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem asChild>
                                                                <Link href={admin.users.show({ user: user.id }).url}>
                                                                    <Eye className="mr-2 h-4 w-4" />
                                                                    View
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem asChild>
                                                                <Link href={admin.users.edit({ user: user.id }).url}>
                                                                    <Edit className="mr-2 h-4 w-4" />
                                                                    Edit
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                onClick={() => handleToggleAdmin(user.id)}
                                                            >
                                                                {user.is_admin ? (
                                                                    <>
                                                                        <ShieldOff className="mr-2 h-4 w-4" />
                                                                        Remove admin
                                                                    </>
                                                                ) : (
                                                                    <>
                                                                        <Shield className="mr-2 h-4 w-4" />
                                                                        Make admin
                                                                    </>
                                                                )}
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                onClick={() => handleDelete(user.id, user.name)}
                                                                className="text-red-600"
                                                            >
                                                                <Trash2 className="mr-2 h-4 w-4" />
                                                                Delete
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>

                                {users.last_page > 1 && (
                                    <div className="flex items-center justify-between border-t pt-4 mt-4">
                                        <p className="text-sm text-muted-foreground">
                                            Showing {(users.current_page - 1) * users.per_page + 1} to{' '}
                                            {Math.min(users.current_page * users.per_page, users.total)} of{' '}
                                            {users.total} users
                                        </p>
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={users.current_page === 1}
                                                onClick={() =>
                                                    router.get(admin.users.index().url, {
                                                        ...filters,
                                                        page: users.current_page - 1,
                                                    })
                                                }
                                            >
                                                Previous
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={users.current_page === users.last_page}
                                                onClick={() =>
                                                    router.get(admin.users.index().url, {
                                                        ...filters,
                                                        page: users.current_page + 1,
                                                    })
                                                }
                                            >
                                                Next
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </>
                        ) : (
                            <p className="py-8 text-center text-muted-foreground">No users found</p>
                        )}
                    </CardContent>
                </Card>

                <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete User</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to delete "{userToDelete?.name}"? This action cannot be
                                undone.
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
