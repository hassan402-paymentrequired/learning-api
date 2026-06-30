import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Search, FileQuestion, Edit, Eye, ArrowLeft } from 'lucide-react';
import admin from '@/routes/admin';
import { type BreadcrumbItem } from '@/types';

interface Department {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    subjects_count: number;
}

interface Subject {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    exam_types: string[] | null;
    is_active: boolean;
    questions_count: number;
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

export default function DepartmentShow({ department, subjects, filters }: Props) {
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${department.name} — Courses`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">{department.name}</h1>
                        <p className="text-muted-foreground">
                            {department.subjects_count}{' '}
                            {department.subjects_count === 1 ? 'course' : 'courses'} in this department
                        </p>
                        {department.description && (
                            <p className="mt-2 text-sm text-muted-foreground max-w-2xl">
                                {department.description}
                            </p>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={admin.departments.index().url}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={`/admin/departments/${department.id}/edit`}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit Department
                            </Link>
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
                                    <div className="flex gap-2 pt-1">
                                        <Button variant="default" size="sm" asChild className="flex-1">
                                            <Link href={admin.subjects.show({ subject: subject.id }).url}>
                                                <Eye className="mr-2 h-4 w-4" />
                                                View Questions
                                            </Link>
                                        </Button>
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={admin.subjects.edit(subject.id).url}>
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
                            {!hasActiveFilters && (
                                <Button className="mt-4" asChild>
                                    <Link href={admin.subjects.index().url}>Manage Subjects</Link>
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
            </div>
        </AppLayout>
    );
}
