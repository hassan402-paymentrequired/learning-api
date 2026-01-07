import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import { router } from '@inertiajs/react';
import { Head } from '@inertiajs/react';
import { BookOpen, Plus, Search, Power, PowerOff } from 'lucide-react';
import { useState } from 'react';
import admin from '@/routes/admin';

interface Exam {
    id: number;
    title: string;
    description: string | null;
    type: 'practice' | 'past_question';
    exam_type: 'JAMB' | 'UNILAG' | 'GENERAL';
    subject: string | null;
    duration: number;
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
        type?: string;
        exam_type?: string;
    };
}

export default function ExamsIndex({ exams, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [type, setType] = useState(filters.type || '');
    const [examType, setExamType] = useState(filters.exam_type || '');
    const [selectedExams, setSelectedExams] = useState<number[]>([]);

    const handleFilter = () => {
        router.get(admin.exams.index().url, {
            search: search || undefined,
            type: type || undefined,
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
            alert('Please select at least one exam.');
            return;
        }

        if (confirm(`Are you sure you want to ${action} ${selectedExams.length} exam(s)?`)) {
            router.post('/admin/exams/bulk-update', {
                exam_ids: selectedExams,
                action: action,
            }, {
                onSuccess: () => {
                    setSelectedExams([]);
                },
            });
        }
    };

    return (
        <AppLayout>
            <Head title="Exams" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Exams</h1>
                        <p className="text-muted-foreground">Manage practice exams and past questions</p>
                    </div>
                    <Button asChild>
                        <a href={admin.exams.create().url}>
                            <Plus className="mr-2 h-4 w-4" />
                            Create Exam
                        </a>
                    </Button>
                </div>

                {/* Bulk Actions */}
                {selectedExams.length > 0 && (
                    <Card className="border-primary">
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <p className="text-sm font-medium">
                                    {selectedExams.length} exam(s) selected
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
                        <div className="flex gap-4">
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
                            <Select value={type} onValueChange={setType || 'all'}>
                                <SelectTrigger className="w-[180px]">
                                    <SelectValue placeholder="All Types" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Types</SelectItem>
                                    <SelectItem value="practice">Practice</SelectItem>
                                    <SelectItem value="past_question">Past Question</SelectItem>
                                </SelectContent>
                            </Select>
                            <Select value={examType} onValueChange={setExamType || 'all'}>
                                <SelectTrigger className="w-[180px]">
                                    <SelectValue placeholder="All Exam Types" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All Exam Types</SelectItem>
                                    <SelectItem value="JAMB">JAMB</SelectItem>
                                    <SelectItem value="UNILAG">UNILAG</SelectItem>
                                    <SelectItem value="GENERAL">GENERAL</SelectItem>
                                </SelectContent>
                            </Select>
                            <Button onClick={handleFilter}>Filter</Button>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {exams.data.map((exam) => (
                        <Card key={exam.id} className="hover:shadow-lg transition-shadow">
                            <CardHeader>
                                <div className="flex items-start justify-between">
                                    <div className="flex items-center gap-2 flex-1">
                                        <Checkbox
                                            checked={selectedExams.includes(exam.id)}
                                            onCheckedChange={() => handleSelectExam(exam.id)}
                                        />
                                        <div className="flex-1">
                                            <CardTitle className="text-lg">{exam.title}</CardTitle>
                                            <CardDescription className="mt-1">
                                                {exam.exam_type} • {exam.type === 'practice' ? 'Practice' : 'Past Question'}
                                                {exam.year && ` • ${exam.year}`}
                                            </CardDescription>
                                        </div>
                                    </div>
                                    {exam.is_active ? (
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
                                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <BookOpen className="h-4 w-4" />
                                        <span>{exam.questions_count} questions</span>
                                    </div>
                                    {exam.subject && (
                                        <div className="text-sm text-muted-foreground">
                                            Subject: {exam.subject}
                                        </div>
                                    )}
                                    <div className="text-sm text-muted-foreground">
                                        Duration: {exam.duration} minutes
                                    </div>
                                    <div className="flex gap-2 pt-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                            className="flex-1"
                                        >
                                            <a href={admin.exams.show(exam.id).url}>View</a>
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                            className="flex-1"
                                        >
                                            <a href={admin.exams.edit( exam.id).url}>Edit</a>
                                        </Button>
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
                    <div className="flex justify-center gap-2">
                        <Button
                            variant="outline"
                            disabled={exams.current_page === 1}
                            onClick={() => router.get(admin.exams.index().url, { page: exams.current_page - 1, ...filters })}
                        >
                            Previous
                        </Button>
                        <span className="flex items-center px-4 text-sm text-muted-foreground">
                            Page {exams.current_page} of {exams.last_page}
                        </span>
                        <Button
                            variant="outline"
                            disabled={exams.current_page === exams.last_page}
                            onClick={() => router.get(admin.exams.index().url, { page: exams.current_page + 1, ...filters })}
                        >
                            Next
                        </Button>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
