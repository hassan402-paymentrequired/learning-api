import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { router, Link,Head } from '@inertiajs/react';
import { Search, FileQuestion, BookOpen } from 'lucide-react';
import { useState } from 'react';
import admin from '@/routes/admin';

interface Question {
    id: number;
    question_text: string;
    question_type: string;
    points: number;
    order: number;
    exam: {
        id: number;
        title: string;
        exam_type: string;
    };
    answers_count?: number;
}

interface Exam {
    id: number;
    title: string;
    exam_type: string;
}

interface Props {
    questions: {
        data: Question[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    exams: Exam[];
    filters: {
        search?: string;
        exam_id?: string;
        exam_type?: string;
    };
}

export default function QuestionsAll({ questions, exams, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [examId, setExamId] = useState(filters.exam_id || '');
    const [examType, setExamType] = useState(filters.exam_type || '');

    const handleFilter = () => {
        router.get('/admin/questions', {
            search: search || undefined,
            exam_id: examId || undefined,
            exam_type: examType || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleReset = () => {
        setSearch('');
        setExamId('');
        setExamType('');
        router.get('/admin/questions', {}, {
            preserveState: false,
        });
    };

    return (
        <AppLayout>
            <Head title="Question Bank" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Question Bank</h1>
                        <p className="text-muted-foreground">View all questions across all exams</p>
                    </div>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>Search and filter questions</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-4">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Search</label>
                                <div className="relative">
                                    <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder="Search questions..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="pl-8"
                                        onKeyDown={(e) => e.key === 'Enter' && handleFilter()}
                                    />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Exam</label>
                                <Select value={examId || 'all'} onValueChange={setExamId}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="All exams" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All exams</SelectItem>
                                        {exams.map((exam) => (
                                            <SelectItem key={exam.id} value={exam.id.toString()}>
                                                {exam.title}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Exam Type</label>
                                <Select value={examType || 'all'} onValueChange={setExamType}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="All types" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All types</SelectItem>
                                        <SelectItem value="JAMB">JAMB</SelectItem>
                                        <SelectItem value="UNILAG">UNILAG</SelectItem>
                                        <SelectItem value="DLI">DLI</SelectItem>
                                        <SelectItem value="GENERAL">GENERAL</SelectItem>
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

                {/* Questions List */}
                <Card>
                    <CardHeader>
                        <CardTitle>All Questions ({questions.total})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {questions.data.length > 0 ? (
                            <div className="space-y-4">
                                {questions.data.map((question) => (
                                    <div
                                        key={question.id}
                                        className="flex items-start justify-between border-b pb-4 last:border-0"
                                    >
                                        <div className="flex-1">
                                            <div className="flex items-center gap-2 mb-2">
                                                <FileQuestion className="h-4 w-4 text-muted-foreground" />
                                                <span className="text-sm font-medium text-muted-foreground">
                                                    Q{question.order}
                                                </span>
                                                <span className="text-xs text-muted-foreground">•</span>
                                                <span className="text-sm text-muted-foreground">
                                                    {question.points} point{question.points !== 1 ? 's' : ''}
                                                </span>
                                            </div>
                                            <p className="text-sm mb-2">{question.question_text}</p>
                                            <div className="flex items-center gap-2">
                                                <Link
                                                    href={admin.exams.show({ exam: question.exam.id }).url}
                                                    className="flex items-center gap-1 text-xs text-primary hover:underline"
                                                >
                                                    <BookOpen className="h-3 w-3" />
                                                    {question.exam.title}
                                                </Link>
                                                <span className="text-xs text-muted-foreground">•</span>
                                                <span className="text-xs text-muted-foreground">{question.exam.exam_type}</span>
                                            </div>
                                        </div>
                                    </div>
                                ))}

                                {/* Pagination */}
                                {questions.last_page > 1 && (
                                    <div className="flex items-center justify-between pt-4">
                                        <p className="text-sm text-muted-foreground">
                                            Showing {((questions.current_page - 1) * questions.per_page) + 1} to{' '}
                                            {Math.min(questions.current_page * questions.per_page, questions.total)} of {questions.total} questions
                                        </p>
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={questions.current_page === 1}
                                                onClick={() => router.get('/admin/questions', { ...filters, page: questions.current_page - 1 })}
                                            >
                                                Previous
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={questions.current_page === questions.last_page}
                                                onClick={() => router.get('/admin/questions', { ...filters, page: questions.current_page + 1 })}
                                            >
                                                Next
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <p className="text-center text-muted-foreground py-8">No questions found</p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
