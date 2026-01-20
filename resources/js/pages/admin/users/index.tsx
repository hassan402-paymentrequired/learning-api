import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Search, User as UserIcon, Mail, Calendar, Eye, Edit, Trash2, Shield, ShieldOff } from 'lucide-react';
import { useState } from 'react';
import admin from '@/routes/admin';
import { Link, router } from '@inertiajs/react';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { toast } from 'sonner';

interface User {
    id: number;
    name: string;
    email: string;
    created_at: string;
    exam_attempts_count: number;
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

interface UserWithAdmin extends User {
    is_admin?: boolean;
}

export default function UsersIndex({ users, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [userToDelete, setUserToDelete] = useState<number | null>(null);

    const handleFilter = () => {
        router.get(admin.users.index().url, {
            search: search || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
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
        router.post(`/admin/users/${userId}/toggle-admin`, {}, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                toast.success('User admin status updated successfully');
            },
            onError: () => {
                toast.error('Failed to update user admin status');
            },
        });
    };

    const handleDelete = (userId: number) => {
        setUserToDelete(userId);
        setDeleteDialogOpen(true);
    };

    const confirmDelete = () => {
        if (userToDelete) {
            router.delete(admin.users.destroy({ user: userToDelete }).url, {
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
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Users</h1>
                        <p className="text-muted-foreground">Manage platform users</p>
                    </div>
                </div>

                {/* Filters */}
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

                {/* Users List */}
                <Card>
                    <CardHeader>
                        <CardTitle>All Users ({users.total})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {users.data.length > 0 ? (
                            <div className="space-y-4">
                                {users.data.map((user) => (
                                    <div
                                        key={user.id}
                                        className="flex items-center justify-between border-b pb-4 last:border-0"
                                    >
                                        <div className="flex items-center gap-4">
                                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                                                <UserIcon className="h-5 w-5 text-primary" />
                                            </div>
                                            <div>
                                                <p className="font-medium">{user.name}</p>
                                                <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                                    <span className="flex items-center gap-1">
                                                        <Mail className="h-3 w-3" />
                                                        {user.email}
                                                    </span>
                                                    <span className="flex items-center gap-1">
                                                        <Calendar className="h-3 w-3" />
                                                        {new Date(user.created_at).toLocaleDateString()}
                                                    </span>
                                                    <span>{user.exam_attempts_count} attempts</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <Button asChild variant="outline" size="sm">
                                                <Link href={admin.users.show({ user: user.id }).url}>
                                                    <Eye className="mr-2 h-4 w-4" />
                                                    View
                                                </Link>
                                            </Button>
                                            <Button asChild variant="outline" size="sm">
                                                <Link href={admin.users.edit({ user: user.id }).url}>
                                                    <Edit className="mr-2 h-4 w-4" />
                                                    Edit
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => handleToggleAdmin(user.id)}
                                            >
                                                <Shield className="mr-2 h-4 w-4" />
                                                Toggle Admin
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                onClick={() => handleDelete(user.id)}
                                            >
                                                <Trash2 className="mr-2 h-4 w-4" />
                                                Delete
                                            </Button>
                                        </div>
                                    </div>
                                ))}

                                {/* Pagination */}
                                {users.last_page > 1 && (
                                    <div className="flex items-center justify-between pt-4">
                                        <p className="text-sm text-muted-foreground">
                                            Showing {((users.current_page - 1) * users.per_page) + 1} to{' '}
                                            {Math.min(users.current_page * users.per_page, users.total)} of {users.total} users
                                        </p>
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={users.current_page === 1}
                                                onClick={() => router.get(admin.users.index().url, { ...filters, page: users.current_page - 1 })}
                                            >
                                                Previous
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={users.current_page === users.last_page}
                                                onClick={() => router.get(admin.users.index().url, { ...filters, page: users.current_page + 1 })}
                                            >
                                                Next
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <p className="text-center text-muted-foreground py-8">No users found</p>
                        )}
                    </CardContent>
                </Card>

                {/* Delete Confirmation Dialog */}
                <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete User</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to delete this user? This action cannot be undone.
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
