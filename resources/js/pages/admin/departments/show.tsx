import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { MultiSelect } from '@/components/ui/multi-select';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Search, FileQuestion, Edit, Eye, Link2, Building2 } from 'lucide-react';
import admin from '@/routes/admin';
import { type BreadcrumbItem } from '@/types';
import { useState } from 'react';
import { toast } from 'sonner';

interface Department {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    subjects_count: number;
}

interface LinkedDepartment {
    id: number;
    name: string;
    is_active: boolean;
}

interface Subject {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    exam_types: string[] | null;
    is_active: boolean;
    questions_count: number;
    department_ids: number[];
    linked_departments: LinkedDepartment[];
}

interface LinkableSubject {
    id: number;
    name: string;
}

interface DepartmentOption {
    id: number;
    name: string;
    is_active: boolean;
}

interface Props {
    department: Department;
    subjects: {
        data: Subject[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    linkableSubjects: LinkableSubject[];
    allDepartments: DepartmentOption[];
    filters: {
        search: string;
        is_active: string;
    };
}

function courseFilterParams(
    filters: Props['filters'],
    overrides: Partial<Props['filters'] & { page?: number }> = {},
) {
    const search = overrides.search ?? filters.search;
    const isActive = overrides.is_active ?? filters.is_active;
    const page = overrides.page ?? 1;

    return {
        search: search || undefined,
        is_active: isActive !== 'all' ? isActive : undefined,
        page: page > 1 ? page : undefined,
    };
}

export default function DepartmentShow({
    department,
    subjects,
    linkableSubjects,
    allDepartments,
    filters,
}: Props) {
    const [linkDialogOpen, setLinkDialogOpen] = useState(false);
    const [manageDialogOpen, setManageDialogOpen] = useState(false);
    const [subjectToManage, setSubjectToManage] = useState<Subject | null>(null);

    const linkForm = useForm({
        subject_ids: [] as number[],
    });

    const manageForm = useForm({
        department_ids: [] as number[],
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Departments', href: admin.departments.index().url },
        { title: department.name, href: '#' },
    ];

    const showUrl = `/admin/departments/${department.id}`;

    const applyFilters = (overrides: Partial<Props['filters'] & { page?: number }> = {}) => {
        router.get(showUrl, courseFilterParams(filters, overrides), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const clearFilters = () => {
        router.get(showUrl, {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const hasActiveFilters = filters.search !== '' || filters.is_active !== 'all';

    const handleLink = (e: React.FormEvent) => {
        e.preventDefault();
        linkForm.post(`/admin/departments/${department.id}/link-subjects`, {
            preserveScroll: true,
            onSuccess: () => {
                setLinkDialogOpen(false);
                linkForm.reset();
                toast.success('Courses linked successfully');
            },
            onError: () => {
                toast.error('Failed to link courses');
            },
        });
    };

    const openManageDialog = (subject: Subject) => {
        setSubjectToManage(subject);
        manageForm.setData('department_ids', subject.department_ids ?? []);
        manageForm.clearErrors();
        setManageDialogOpen(true);
    };

    const handleManageDepartments = (e: React.FormEvent) => {
        e.preventDefault();
        if (!subjectToManage) return;

        manageForm.patch(`/admin/subjects/${subjectToManage.id}/departments`, {
            preserveScroll: true,
            onSuccess: () => {
                setManageDialogOpen(false);
                setSubjectToManage(null);
                manageForm.reset();
                toast.success('Department links updated');
            },
            onError: () => {
                toast.error('Failed to update department links');
            },
        });
    };

    const otherDepartments = (subject: Subject) =>
        (subject.linked_departments ?? []).filter((dept) => dept.id !== department.id);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${department.name} — Courses`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">{department.name}</h1>
                        <p className="text-muted-foreground">
                            {department.subjects_count}{' '}
                            {department.subjects_count === 1 ? 'course' : 'courses'} linked to this department
                        </p>
                        {department.description && (
                            <p className="mt-2 text-sm text-muted-foreground max-w-2xl">
                                {department.description}
                            </p>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`/admin/departments/${department.id}/edit`}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit Department
                            </Link>
                        </Button>
                        <Button onClick={() => setLinkDialogOpen(true)} disabled={linkableSubjects.length === 0}>
                            <Link2 className="mr-2 h-4 w-4" />
                            Link Course
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>Filter courses in this department</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                const formData = new FormData(e.currentTarget);
                                applyFilters({
                                    search: (formData.get('search') as string) ?? '',
                                    page: 1,
                                });
                            }}
                            className="flex flex-col lg:flex-row gap-4"
                        >
                            <div className="flex-1">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        name="search"
                                        placeholder="Search courses..."
                                        defaultValue={filters.search}
                                        key={`search-${filters.search}`}
                                        className="pl-9"
                                    />
                                </div>
                            </div>
                            <div className="flex flex-col sm:flex-row gap-2 flex-1 lg:flex-initial">
                                <Select
                                    value={filters.is_active}
                                    onValueChange={(value) => applyFilters({ is_active: value, page: 1 })}
                                >
                                    <SelectTrigger className="w-full sm:w-[180px]">
                                        <SelectValue placeholder="All Statuses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Statuses</SelectItem>
                                        <SelectItem value="true">Active</SelectItem>
                                        <SelectItem value="false">Inactive</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button type="submit" className="w-full sm:w-auto">Search</Button>
                                {hasActiveFilters && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="w-full sm:w-auto"
                                        onClick={clearFilters}
                                    >
                                        Clear
                                    </Button>
                                )}
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {subjects.data.map((subject) => (
                        <Card key={subject.id} className="hover:shadow-lg transition-shadow">
                            <CardHeader>
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0 flex-1">
                                        <CardTitle className="text-lg">{subject.name}</CardTitle>
                                        <CardDescription className="mt-1">
                                            {subject.exam_types && subject.exam_types.length > 0
                                                ? subject.exam_types.join(', ')
                                                : 'No exam types'}
                                        </CardDescription>
                                    </div>
                                    {subject.is_active ? (
                                        <span className="shrink-0 px-2 py-1 text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded">
                                            Active
                                        </span>
                                    ) : (
                                        <span className="shrink-0 px-2 py-1 text-xs bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200 rounded">
                                            Inactive
                                        </span>
                                    )}
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    {subject.description && (
                                        <p className="text-sm text-muted-foreground line-clamp-2">
                                            {subject.description}
                                        </p>
                                    )}
                                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <FileQuestion className="h-4 w-4" />
                                        <span>
                                            {subject.questions_count} question
                                            {subject.questions_count !== 1 ? 's' : ''}
                                        </span>
                                    </div>
                                    {(subject.linked_departments?.length ?? 0) > 0 && (
                                        <div className="space-y-1">
                                            <p className="text-xs font-medium text-muted-foreground">Also in</p>
                                            <div className="flex flex-wrap gap-1">
                                                {otherDepartments(subject).length > 0 ? (
                                                    otherDepartments(subject).map((dept) => (
                                                        <Link
                                                            key={dept.id}
                                                            href={`/admin/departments/${dept.id}`}
                                                            className="inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-xs hover:bg-muted/80"
                                                        >
                                                            {dept.name}
                                                            {!dept.is_active && ' (inactive)'}
                                                        </Link>
                                                    ))
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        No other departments
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    )}
                                    <div className="flex gap-2 pt-1">
                                        <Button variant="default" size="sm" asChild className="flex-1">
                                            <Link href={`/admin/departments/${department.id}/courses/${subject.id}`}>
                                                <Eye className="mr-2 h-4 w-4" />
                                                View
                                            </Link>
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => openManageDialog(subject)}
                                            title="Manage department links"
                                        >
                                            <Building2 className="h-4 w-4" />
                                        </Button>
                                        <Button variant="outline" size="sm" asChild>
                                            <Link
                                                href={`${admin.subjects.edit(subject.id).url}?return_to=${encodeURIComponent(showUrl)}`}
                                            >
                                                <Edit className="h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {subjects.data.length === 0 && (
                    <Card>
                        <CardContent className="py-10 text-center">
                            <p className="text-muted-foreground">
                                {hasActiveFilters
                                    ? 'No courses match your filters.'
                                    : 'No courses are linked to this department yet.'}
                            </p>
                            {!hasActiveFilters && linkableSubjects.length > 0 && (
                                <Button className="mt-4" onClick={() => setLinkDialogOpen(true)}>
                                    <Link2 className="mr-2 h-4 w-4" />
                                    Link Course
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                )}

                {subjects.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            Showing {(subjects.current_page - 1) * subjects.per_page + 1} to{' '}
                            {Math.min(subjects.current_page * subjects.per_page, subjects.total)} of{' '}
                            {subjects.total} courses
                        </p>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={subjects.current_page === 1}
                                onClick={() => applyFilters({ page: subjects.current_page - 1 })}
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={subjects.current_page === subjects.last_page}
                                onClick={() => applyFilters({ page: subjects.current_page + 1 })}
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                )}

                <Dialog
                    open={linkDialogOpen}
                    onOpenChange={(open) => {
                        setLinkDialogOpen(open);
                        if (!open) {
                            linkForm.reset();
                            linkForm.clearErrors();
                        }
                    }}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Link Courses</DialogTitle>
                            <DialogDescription>
                                Select existing courses to add to {department.name}. Questions stay shared — nothing is duplicated.
                            </DialogDescription>
                        </DialogHeader>
                        <form onSubmit={handleLink} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="link-subjects">Courses</Label>
                                <MultiSelect
                                    id="link-subjects"
                                    options={linkableSubjects.map((subject) => ({
                                        value: subject.id.toString(),
                                        label: subject.name,
                                    }))}
                                    value={linkForm.data.subject_ids.map(String)}
                                    onChange={(values) =>
                                        linkForm.setData(
                                            'subject_ids',
                                            values.map((value) => parseInt(value, 10)),
                                        )
                                    }
                                    placeholder="Select courses to link"
                                />
                                {linkForm.errors.subject_ids && (
                                    <p className="text-sm text-red-500">{linkForm.errors.subject_ids}</p>
                                )}
                            </div>
                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setLinkDialogOpen(false)}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={linkForm.processing || linkForm.data.subject_ids.length === 0}>
                                    <Link2 className="mr-2 h-4 w-4" />
                                    {linkForm.processing ? 'Linking...' : 'Link Courses'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={manageDialogOpen}
                    onOpenChange={(open) => {
                        setManageDialogOpen(open);
                        if (!open) {
                            setSubjectToManage(null);
                            manageForm.reset();
                            manageForm.clearErrors();
                        }
                    }}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Manage Departments</DialogTitle>
                            <DialogDescription>
                                Choose which departments "{subjectToManage?.name}" belongs to. You can link it to multiple departments or remove it from this one by unchecking "{department.name}".
                            </DialogDescription>
                        </DialogHeader>
                        <form onSubmit={handleManageDepartments} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="manage-departments">Departments</Label>
                                <MultiSelect
                                    id="manage-departments"
                                    options={allDepartments.map((dept) => ({
                                        value: dept.id.toString(),
                                        label: dept.is_active ? dept.name : `${dept.name} (inactive)`,
                                    }))}
                                    value={manageForm.data.department_ids.map(String)}
                                    onChange={(values) =>
                                        manageForm.setData(
                                            'department_ids',
                                            values.map((value) => parseInt(value, 10)),
                                        )
                                    }
                                    placeholder="Select departments"
                                />
                                {manageForm.errors.department_ids && (
                                    <p className="text-sm text-red-500">{manageForm.errors.department_ids}</p>
                                )}
                            </div>
                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={() => setManageDialogOpen(false)}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={manageForm.processing}>
                                    <Building2 className="mr-2 h-4 w-4" />
                                    {manageForm.processing ? 'Saving...' : 'Save Links'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
