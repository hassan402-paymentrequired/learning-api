import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Plus, Search, Power, PowerOff, Trash2, Edit, FileQuestion, Eye, MoreVertical } from 'lucide-react';
import { useState } from 'react';
import admin from '@/routes/admin';
import { Link, router } from '@inertiajs/react';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { toast } from 'sonner';

interface Subject {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    exam_types: string[] | null;
    is_active: boolean;
    order: number;
    created_at: string;
    questions_count: number;
}

interface ExamCategory {
    id: number;
    name: string;
    slug: string;
}

interface Props {
    subjects: {
        data: Subject[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    examCategories: ExamCategory[];
    filters: {
        search?: string;
        is_active?: string;
        exam_type?: string;
    };
}

export default function SubjectsIndex({ subjects, examCategories, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [isActive, setIsActive] = useState(filters.is_active || '');
    const [examType, setExamType] = useState(filters.exam_type || '');
    const [selectedSubjects, setSelectedSubjects] = useState<number[]>([]);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [subjectToDelete, setSubjectToDelete] = useState<{ id: number; name: string } | null>(null);

    const handleFilter = () => {
        router.get(admin.subjects.index().url, {
            search: search || undefined,
            is_active: isActive !== 'all' ? isActive : undefined,
            exam_type: examType !== 'all' ? examType : undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleSelectSubject = (subjectId: number) => {
        if (selectedSubjects.includes(subjectId)) {
            setSelectedSubjects(selectedSubjects.filter(id => id !== subjectId));
        } else {
            setSelectedSubjects([...selectedSubjects, subjectId]);
        }
    };

    const handleBulkAction = (action: 'activate' | 'deactivate') => {
        if (selectedSubjects.length === 0) {
            alert('Please select at least one subject.');
            return;
        }

        if (confirm(`Are you sure you want to ${action} ${selectedSubjects.length} subject(s)?`)) {
            // Implement bulk action via API
            router.post('/admin/subjects/bulk-update', {
                subject_ids: selectedSubjects,
                action: action,
            }, {
                onSuccess: () => {
                    setSelectedSubjects([]);
                },
            });
        }
    };

    const handleToggleActive = (subjectId: number) => {
        router.post(`/admin/subjects/${subjectId}/toggle-active`, {}, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Subject status updated successfully');
            },
            onError: () => {
                toast.error('Failed to update subject status');
            },
        });
    };

    const handleDelete = (subjectId: number, subjectName: string) => {
        setSubjectToDelete({ id: subjectId, name: subjectName });
        setDeleteDialogOpen(true);
    };

    const confirmDelete = () => {
        if (subjectToDelete) {
            router.delete(admin.subjects.destroy(subjectToDelete.id).url, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Subject deleted successfully');
                    setDeleteDialogOpen(false);
                    setSubjectToDelete(null);
                },
                onError: (errors) => {
                    const errorMessage = errors?.subject || 'Failed to delete subject';
                    toast.error(errorMessage);
                },
            });
        }
    };

    return (
        <AppLayout>
            <Head title="Subjects" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex flex-col sm:flex-row sm:items-center gap-4">
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

                {/* Bulk Actions */}
                {selectedSubjects.length > 0 && (
                    <Card className="border-primary">
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <p className="text-sm font-medium">
                                    {selectedSubjects.length} subject(s) selected
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handleBulkAction('activate')}
                                    >
                                        <Power className="mr-2 h-4 w-4" />
                                        Activate
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handleBulkAction('deactivate')}
                                    >
                                        <PowerOff className="mr-2 h-4 w-4" />
                                        Deactivate
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setSelectedSubjects([])}
                                    >
                                        Clear Selection
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>Filter subjects by search, exam type or status</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col lg:flex-row gap-4">
                            <div className="flex-1">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        placeholder="Search subjects..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        onKeyDown={(e) => e.key === 'Enter' && handleFilter()}
                                        className="pl-9"
                                    />
                                </div>
                            </div>
                            <div className="flex flex-col sm:flex-row gap-2 flex-1 lg:flex-initial">
                                <Select value={examType || 'all'} onValueChange={setExamType}>
                                    <SelectTrigger className="w-full sm:w-[180px]">
                                        <SelectValue placeholder="All Exam Types" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Exam Types</SelectItem>
                                        {examCategories.map((category) => (
                                            <SelectItem key={category.id} value={category.slug}>
                                                {category.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select value={isActive || 'all'} onValueChange={setIsActive}>
                                    <SelectTrigger className="w-full sm:w-[180px]">
                                        <SelectValue placeholder="All Statuses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Statuses</SelectItem>
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
                    {subjects.data.map((subject) => (
                        <Card key={subject.id} className="hover:shadow-lg transition-shadow">
                            <CardHeader>
                                <div className="flex items-start justify-between">
                                    <div className="flex items-center gap-2 flex-1">
                                        <Checkbox
                                            checked={selectedSubjects.includes(subject.id)}
                                            onCheckedChange={() => handleSelectSubject(subject.id)}
                                        />
                                        <div className="flex-1">
                                            <CardTitle className="text-lg">{subject.name}</CardTitle>
                                            <CardDescription className="mt-1">
                                                {subject.exam_types && subject.exam_types.length > 0
                                                    ? subject.exam_types.join(', ')
                                                    : 'No exam types'}
                                            </CardDescription>
                                        </div>
                                    </div>
                                    {subject.is_active ? (
                                        <span className="px-2 py-1 text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded">
                                            Active
                                        </span>
                                    ) : (
                                        <span className="px-2 py-1 text-xs bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200 rounded">
                                            Inactive
                                        </span>
                                    )}
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    {subject.description && (
                                        <div className="text-sm text-muted-foreground">
                                            {subject.description}
                                        </div>
                                    )}
                                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <FileQuestion className="h-4 w-4" />
                                        <span>{subject.questions_count} question{subject.questions_count !== 1 ? 's' : ''}</span>
                                    </div>
                                    <div className="flex gap-2 pt-2">
                                        <Button
                                            variant="default"
                                            size="sm"
                                            asChild
                                            className="flex-1"
                                        >
                                            <Link href={admin.subjects.show({ subject: subject.id }).url}>
                                                <Eye className="mr-2 h-4 w-4" />
                                                View Questions
                                            </Link>
                                        </Button>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="outline" size="sm" className="px-2">
                                                    <MoreVertical className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem asChild>
                                                    <Link href={admin.subjects.edit(subject.id).url}>
                                                        <Edit className="mr-2 h-4 w-4" />
                                                        Edit
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem onClick={() => handleToggleActive(subject.id)}>
                                                    {subject.is_active ? (
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
                                                    onClick={() => handleDelete(subject.id, subject.name)}
                                                    variant="destructive"
                                                >
                                                    <Trash2 className="mr-2 h-4 w-4" />
                                                    Delete
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {subjects.data.length === 0 && (
                    <Card>
                        <CardContent className="py-10 text-center">
                            <p className="text-muted-foreground">No subjects found.</p>
                        </CardContent>
                    </Card>
                )}

                {subjects.last_page > 1 && (
                    <div className="flex justify-center gap-2">
                        <Button
                            variant="outline"
                            disabled={subjects.current_page === 1}
                            onClick={() => router.get(admin.subjects.index().url, { page: subjects.current_page - 1, ...filters })}
                        >
                            Previous
                        </Button>
                        <span className="flex items-center px-4 text-sm text-muted-foreground">
                            Page {subjects.current_page} of {subjects.last_page}
                        </span>
                        <Button
                            variant="outline"
                            disabled={subjects.current_page === subjects.last_page}
                            onClick={() => router.get(admin.subjects.index().url, { page: subjects.current_page + 1, ...filters })}
                        >
                            Next
                        </Button>
                    </div>
                )}

                {/* Delete Confirmation Dialog */}
                <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete Subject</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to delete "{subjectToDelete?.name}"? This action cannot be undone.
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
