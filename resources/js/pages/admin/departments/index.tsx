import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Search, Power, PowerOff, Trash2, Edit, MoreVertical, Save } from 'lucide-react';
import { useState } from 'react';
import admin from '@/routes/admin';
import { Link } from '@inertiajs/react';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { toast } from 'sonner';

interface Department {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    created_at: string;
    subjects_count: number;
}

interface Props {
    departments: {
        data: Department[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        search?: string;
        is_active?: string;
    };
}

export default function DepartmentsIndex({ departments, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [isActive, setIsActive] = useState(filters.is_active || '');
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [createDialogOpen, setCreateDialogOpen] = useState(false);
    const [departmentToDelete, setDepartmentToDelete] = useState<{ id: number; name: string } | null>(null);

    const createForm = useForm({
        name: '',
        description: '',
        is_active: true,
    });

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post(admin.departments.store().url, {
            preserveScroll: true,
            onSuccess: () => {
                setCreateDialogOpen(false);
                createForm.reset();
                toast.success('Department created successfully');
            },
        });
    };

    const handleFilter = () => {
        router.get('/admin/departments', {
            search: search || undefined,
            is_active: isActive !== 'all' ? isActive : undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleToggleActive = (departmentId: number) => {
        router.post(`/admin/departments/${departmentId}/toggle-active`, {}, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Department status updated successfully');
            },
            onError: () => {
                toast.error('Failed to update department status');
            },
        });
    };

    const handleDelete = (departmentId: number, departmentName: string) => {
        setDepartmentToDelete({ id: departmentId, name: departmentName });
        setDeleteDialogOpen(true);
    };

    const confirmDelete = () => {
        if (departmentToDelete) {
            router.delete(`/admin/departments/${departmentToDelete.id}`, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Department deleted successfully');
                    setDeleteDialogOpen(false);
                    setDepartmentToDelete(null);
                },
                onError: (errors) => {
                    const errorMessage = errors?.department || 'Failed to delete department';
                    toast.error(errorMessage);
                },
            });
        }
    };

    return (
        <AppLayout>
            <Head title="Departments" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex flex-col sm:flex-row sm:items-center gap-4">
                        <div>
                            <h1 className="text-2xl font-bold">Departments</h1>
                            <p className="text-muted-foreground">Manage departments for Unilag practice</p>
                        </div>
                    </div>
                    <Button onClick={() => setCreateDialogOpen(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Department
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>Filter departments by search or status</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col lg:flex-row gap-4">
                            <div className="flex-1">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        placeholder="Search departments..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        onKeyDown={(e) => e.key === 'Enter' && handleFilter()}
                                        className="pl-9"
                                    />
                                </div>
                            </div>
                            <div className="flex flex-col sm:flex-row gap-2 flex-1 lg:flex-initial">
                                <Select value={isActive || 'all'} onValueChange={setIsActive}>
                                    <SelectTrigger className="w-full sm:w-[180px]">
                                        <SelectValue placeholder="All Status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Status</SelectItem>
                                        <SelectItem value="true">Active</SelectItem>
                                        <SelectItem value="false">Inactive</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button onClick={handleFilter} className="w-full sm:w-auto">Filter</Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {departments.data.map((department) => (
                        <Card key={department.id} className="hover:shadow-lg transition-shadow">
                            <CardHeader>
                                <div className="flex items-start justify-between">
                                    <div className="flex-1">
                                        <CardTitle className="text-lg">{department.name}</CardTitle>
                                        <CardDescription className="mt-1">
                                            {department.subjects_count} {department.subjects_count === 1 ? 'subject' : 'subjects'}
                                        </CardDescription>
                                    </div>
                                    {department.is_active ? (
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
                            {department.description && (
                                <CardContent>
                                    <p className="text-sm text-muted-foreground line-clamp-2">
                                        {department.description}
                                    </p>
                                </CardContent>
                            )}
                            <CardContent className="pt-0">
                                <div className="flex items-center justify-between">
                                    <div className="flex gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                        >
                                            <Link href={`/admin/departments/${department.id}/edit`}>
                                                <Edit className="h-4 w-4" />
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => handleToggleActive(department.id)}
                                        >
                                            {department.is_active ? (
                                                <PowerOff className="h-4 w-4" />
                                            ) : (
                                                <Power className="h-4 w-4" />
                                            )}
                                        </Button>
                                    </div>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button variant="ghost" size="sm">
                                                <MoreVertical className="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem asChild>
                                                <Link href={`/admin/departments/${department.id}/edit`}>
                                                    <Edit className="mr-2 h-4 w-4" />
                                                    Edit
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                onClick={() => handleToggleActive(department.id)}
                                            >
                                                {department.is_active ? (
                                                    <>
                                                        <PowerOff className="mr-2 h-4 w-4" />
                                                        Deactivate
                                                    </>
                                                ) : (
                                                    <>
                                                        <Power className="mr-2 h-4 w-4" />
                                                        Activate
                                                    </>
                                                )}
                                            </DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                onClick={() => handleDelete(department.id, department.name)}
                                                className="text-red-600"
                                            >
                                                <Trash2 className="mr-2 h-4 w-4" />
                                                Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {departments.data.length === 0 && (
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-center py-8">
                                <p className="text-muted-foreground">No departments found.</p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Pagination */}
                {departments.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            Showing {((departments.current_page - 1) * departments.per_page) + 1} to{' '}
                            {Math.min(departments.current_page * departments.per_page, departments.total)} of{' '}
                            {departments.total} departments
                        </p>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={departments.current_page === 1}
                                onClick={() => router.get('/admin/departments', {
                                    ...filters,
                                    page: departments.current_page - 1,
                                })}
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={departments.current_page === departments.last_page}
                                onClick={() => router.get('/admin/departments', {
                                    ...filters,
                                    page: departments.current_page + 1,
                                })}
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}

                {/* Create Dialog */}
                <Dialog
                    open={createDialogOpen}
                    onOpenChange={(open) => {
                        setCreateDialogOpen(open);
                        if (!open) {
                            createForm.reset();
                            createForm.clearErrors();
                        }
                    }}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Add Department</DialogTitle>
                            <DialogDescription>
                                Create a new department for the DLI practice flow.
                            </DialogDescription>
                        </DialogHeader>
                        <form onSubmit={handleCreate} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="create-name">Department Name *</Label>
                                <Input
                                    id="create-name"
                                    value={createForm.data.name}
                                    onChange={(e) => createForm.setData('name', e.target.value)}
                                    placeholder="e.g., Business Administration Year 1"
                                    required
                                />
                                {createForm.errors.name && (
                                    <p className="text-sm text-red-500">{createForm.errors.name}</p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="create-description">Description</Label>
                                <Textarea
                                    id="create-description"
                                    value={createForm.data.description}
                                    onChange={(e) => createForm.setData('description', e.target.value)}
                                    placeholder="Optional description"
                                    rows={3}
                                />
                                {createForm.errors.description && (
                                    <p className="text-sm text-red-500">{createForm.errors.description}</p>
                                )}
                            </div>
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="create-is-active"
                                    checked={createForm.data.is_active}
                                    onCheckedChange={(checked) => createForm.setData('is_active', checked === true)}
                                />
                                <Label htmlFor="create-is-active" className="cursor-pointer">
                                    Active (visible to students)
                                </Label>
                            </div>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setCreateDialogOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={createForm.processing}>
                                    <Save className="mr-2 h-4 w-4" />
                                    {createForm.processing ? 'Creating...' : 'Create'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                {/* Delete Confirmation Dialog */}
                <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete Department</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to delete "{departmentToDelete?.name}"? This action cannot be undone.
                                {departmentToDelete && (
                                    <span className="block mt-2 text-red-600">
                                        Note: This department has {departments.data.find(d => d.id === departmentToDelete.id)?.subjects_count || 0} associated subject(s).
                                    </span>
                                )}
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
