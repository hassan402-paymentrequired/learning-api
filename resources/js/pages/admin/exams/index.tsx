import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { BookOpen, Plus, Search, Power, PowerOff, Trash2, Edit, MoreVertical } from 'lucide-react';
import { useState } from 'react';
import admin from '@/routes/admin';
import { Link, router } from '@inertiajs/react';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { toast } from 'sonner';

interface Exam {
    id: number;
    title: string;
    description: string | null;
    exam_type: 'JAMB' | 'DLI' | 'UNILAG' | 'GENERAL';
    subject: string | null;
    total_questions: number;
    year: number | null;
    is_active: boolean;
    questions_count: number;
    created_at: string;
}

interface Props {
    exams: {
        data: Exam[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        search?: string;
        exam_type?: string;
    };
}

export default function ExamsIndex({ exams, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [examType, setExamType] = useState(filters.exam_type || '');
    const [selectedExams, setSelectedExams] = useState<number[]>([]);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [examToDelete, setExamToDelete] = useState<number | null>(null);

    const handleFilter = () => {
        router.get(admin.exams.index().url, {
            search: search || undefined,
            exam_type: examType || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleSelectAll = () => {
        if (selectedExams.length === exams.data.length) {
            setSelectedExams([]);
        } else {
            setSelectedExams(exams.data.map(exam => exam.id));
        }
    };

    const handleSelectExam = (examId: number) => {
        if (selectedExams.includes(examId)) {
            setSelectedExams(selectedExams.filter(id => id !== examId));
        } else {
            setSelectedExams([...selectedExams, examId]);
        }
    };

    const handleBulkAction = (action: 'activate' | 'deactivate') => {
        if (selectedExams.length === 0) {
            toast.error('Please select at least one exam.');
            return;
        }

        if (confirm(`Are you sure you want to ${action} ${selectedExams.length} exam(s)?`)) {
            router.post('/admin/exams/bulk-update', {
                exam_ids: selectedExams,
                action: action,
            }, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    toast.success(`${selectedExams.length} exam(s) ${action}d successfully`);
                    setSelectedExams([]);
                },
                onError: () => {
                    toast.error('Failed to update exams');
                },
            });
        }
    };

    const handleToggleActive = (examId: number) => {
        router.post(`/admin/exams/${examId}/toggle-active`, {}, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Exam status updated successfully');
            },
            onError: () => {
                toast.error('Failed to update exam status');
            },
        });
    };

    const handleDelete = (examId: number) => {
        setExamToDelete(examId);
        setDeleteDialogOpen(true);
    };

    const confirmDelete = () => {
        if (examToDelete) {
            router.delete(admin.exams.destroy({ exam: examToDelete }).url, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('Exam deleted successfully');
                    setDeleteDialogOpen(false);
                    setExamToDelete(null);
                },
                onError: () => {
                    toast.error('Failed to delete exam');
                },
            });
        }
    };

    return (
        <AppLayout>
            <Head title="Exams" />
            <div className="flex h-full flex-1 flex-col gap-4 p-3 sm:p-4 overflow-x-hidden">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">Exams</h1>
                        <p className="text-muted-foreground">Manage practice exams and past questions</p>
                    </div>
                    <Button asChild className="w-full sm:w-auto">
                        <Link href={admin.exams.create().url}>
                            <Plus className="mr-2 h-4 w-4" />
                            Create Exam
                        </Link>
                    </Button>
                </div>

                {/* Bulk Actions */}
                {selectedExams.length > 0 && (
                    <Card className="border-primary">
                        <CardContent className="pt-6">
                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <p className="text-sm font-medium">
                                    {selectedExams.length} exam(s) selected
                                </p>
                                <div className="flex flex-wrap gap-2">
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
                                        onClick={() => setSelectedExams([])}
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
                        <CardDescription>Filter exams by search, type, or exam type</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col lg:flex-row gap-4">
                            <div className="flex-1">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        placeholder="Search exams..."
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
                                        <SelectItem value="JAMB">JAMB</SelectItem>
                                        <SelectItem value="UNILAG">UNILAG</SelectItem>
                                        <SelectItem value="GENERAL">GENERAL</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button onClick={handleFilter} className="w-full sm:w-auto">Filter</Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {exams.data.map((exam) => (
                        <Card key={exam.id} className="hover:shadow-lg transition-shadow min-w-0">
                            <CardHeader>
                                <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                                    <div className="flex items-start gap-2 flex-1 min-w-0">
                                        <Checkbox
                                            checked={selectedExams.includes(exam.id)}
                                            onCheckedChange={() => handleSelectExam(exam.id)}
                                            className="shrink-0 mt-0.5"
                                        />
                                        <div className="flex-1 min-w-0">
                                            <CardTitle className="text-base sm:text-lg break-words">{exam.title}</CardTitle>
                                            <CardDescription className="mt-1 text-xs sm:text-sm">
                                                {exam.exam_type} • Past Question
                                                {exam.year && ` • ${exam.year}`}
                                            </CardDescription>
                                        </div>
                                    </div>
                                    {exam.is_active ? (
                                        <span className="px-2 py-1 text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded shrink-0 w-fit">
                                            Active
                                        </span>
                                    ) : (
                                        <span className="px-2 py-1 text-xs bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200 rounded shrink-0 w-fit">
                                            Inactive
                                        </span>
                                    )}
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <BookOpen className="h-4 w-4" />
                                        <span>{exam.questions_count} questions</span>
                                    </div>
                                    {exam.subject && (
                                        <div className="text-sm text-muted-foreground">
                                            Subject: {exam.subject}
                                        </div>
                                    )}
                                    <div className="flex items-center gap-2 pt-2 flex-wrap sm:flex-nowrap">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                            className="flex-1 min-w-0 sm:min-w-[80px]"
                                        >
                                            <Link href={admin.exams.show(exam.id).url}>View</Link>
                                        </Button>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="outline" size="sm" className="px-2">
                                                    <MoreVertical className="h-4 w-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem asChild>
                                                    <Link href={admin.exams.edit(exam.id).url}>
                                                        <Edit className="mr-2 h-4 w-4" />
                                                        Edit
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem onClick={() => handleToggleActive(exam.id)}>
                                                    {exam.is_active ? (
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
                                                    onClick={() => handleDelete(exam.id)}
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

                {exams.data.length === 0 && (
                    <Card>
                        <CardContent className="py-10 text-center">
                            <p className="text-muted-foreground">No exams found.</p>
                        </CardContent>
                    </Card>
                )}

                {exams.last_page > 1 && (
                    <div className="flex flex-wrap justify-center items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={exams.current_page === 1}
                            onClick={() => router.get(admin.exams.index().url, { page: exams.current_page - 1, ...filters })}
                        >
                            Previous
                        </Button>
                        <span className="px-2 sm:px-4 py-1.5 text-sm text-muted-foreground">
                            Page {exams.current_page} of {exams.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={exams.current_page === exams.last_page}
                            onClick={() => router.get(admin.exams.index().url, { page: exams.current_page + 1, ...filters })}
                        >
                            Next
                        </Button>
                    </div>
                )}

                {/* Delete Confirmation Dialog */}
                <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete Exam</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to delete this exam? This action cannot be undone.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setDeleteDialogOpen(false)}>
                                Cancel
                            </Button>
                            <Button variant="destructive" onClick={confirmDelete}>
                                Deletez
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
