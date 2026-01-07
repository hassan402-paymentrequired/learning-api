import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { router } from '@inertiajs/react';
import { Head } from '@inertiajs/react';
import { Plus, Search, Edit, Trash2, BookOpen, ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import admin from '@/routes/admin';
import { Link } from '@inertiajs/react';

interface Subject {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    order: number;
    created_at: string;
}

interface Props {
    subjects: {
        data: Subject[];
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

export default function SubjectsIndex({ subjects, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [isActive, setIsActive] = useState(filters.is_active || 'all');

    const handleFilter = () => {
        router.get(admin.subjects.index().url, {
            search: search || undefined,
            is_active: isActive !== 'all' ? isActive : undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleReset = () => {
        setSearch('');
        setIsActive('all');
        router.get(admin.subjects.index().url, {}, {
            preserveState: false,
        });
    };

    const handleDelete = (subjectId: number, subjectName: string) => {
        if (confirm(`Are you sure you want to delete "${subjectName}"?`)) {
            router.delete(admin.subjects.destroy(subjectId).url);
        }
    };

    return (
        <AppLayout>
            <Head title="Subjects" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button asChild variant="outline" size="sm">
                            <Link href={admin.settings.index().url}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back to Settings
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold">Subjects</h1>
                            <p className="text-muted-foreground">Manage subjects for practice exams</p>
                        </div>
                    </div>
                    <Button asChild>
                        <Link href={admin.subjects.create().url}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Subject
                        </Link>
                    </Button>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>Search and filter subjects</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Search</label>
                                <div className="relative">
                                    <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder="Search subjects..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="pl-8"
                                        onKeyDown={(e) => e.key === 'Enter' && handleFilter()}
                                    />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Status</label>
                                <Select value={isActive} onValueChange={setIsActive}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="All statuses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Statuses</SelectItem>
                                        <SelectItem value="true">Active</SelectItem>
                                        <SelectItem value="false">Inactive</SelectItem>
                                    </SelectContent>
                                </Select>
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

                {/* Subjects List */}
                <Card>
                    <CardHeader>
                        <CardTitle>All Subjects ({subjects.total})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {subjects.data.length > 0 ? (
                            <div className="space-y-4">
                                {subjects.data.map((subject) => (
                                    <div
                                        key={subject.id}
                                        className="flex items-center justify-between border-b pb-4 last:border-0"
                                    >
                                        <div className="flex items-center gap-4 flex-1">
                                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                                                <BookOpen className="h-5 w-5 text-primary" />
                                            </div>
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium">{subject.name}</p>
                                                    {subject.is_active ? (
                                                        <span className="px-2 py-0.5 text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded">
                                                            Active
                                                        </span>
                                                    ) : (
                                                        <span className="px-2 py-0.5 text-xs bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200 rounded">
                                                            Inactive
                                                        </span>
                                                    )}
                                                </div>
                                                {subject.description && (
                                                    <p className="text-sm text-muted-foreground mt-1">
                                                        {subject.description}
                                                    </p>
                                                )}
                                                <p className="text-xs text-muted-foreground mt-1">
                                                    Order: {subject.order}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <Link href={admin.subjects.edit(subject.id).url}>
                                                    <Edit className="mr-2 h-4 w-4" />
                                                    Edit
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => handleDelete(subject.id, subject.name)}
                                            >
                                                <Trash2 className="mr-2 h-4 w-4" />
                                                Delete
                                            </Button>
                                        </div>
                                    </div>
                                ))}

                                {/* Pagination */}
                                {subjects.last_page > 1 && (
                                    <div className="flex items-center justify-between pt-4">
                                        <p className="text-sm text-muted-foreground">
                                            Showing {((subjects.current_page - 1) * subjects.per_page) + 1} to{' '}
                                            {Math.min(subjects.current_page * subjects.per_page, subjects.total)} of {subjects.total} subjects
                                        </p>
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={subjects.current_page === 1}
                                                onClick={() => router.get(admin.subjects.index().url, { ...filters, page: subjects.current_page - 1 })}
                                            >
                                                Previous
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={subjects.current_page === subjects.last_page}
                                                onClick={() => router.get(admin.subjects.index().url, { ...filters, page: subjects.current_page + 1 })}
                                            >
                                                Next
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <p className="text-center text-muted-foreground py-8">No subjects found</p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
