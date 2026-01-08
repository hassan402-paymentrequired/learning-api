import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import { router } from '@inertiajs/react';
import { Head } from '@inertiajs/react';
import { Search, Eye, Calendar, User, BookOpen, CheckCircle, XCircle, Clock } from 'lucide-react';
import { useState } from 'react';
import admin from '@/routes/admin';
import { Link } from '@inertiajs/react';

interface Attempt {
    id: number;
    user: {
        id: number;
        name: string;
        email: string;
    };
    exam: {
        id: number;
        title: string;
        exam_type: string;
    };
    status: string;
    score: number;
    correct_answers: number;
    total_questions: number;
    started_at: string;
    completed_at: string | null;
}

interface Props {
    attempts: {
        data: Attempt[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        user_id?: string;
        exam_id?: string;
        status?: string;
        date_from?: string;
        date_to?: string;
    };
}

export default function PracticeAttemptsIndex({ attempts, filters }: Props) {
    const [status, setStatus] = useState(filters.status || '');
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');
    const [selectedAttempts, setSelectedAttempts] = useState<number[]>([]);

    const handleFilter = () => {
        router.get('/admin/practice-attempts', {
            status: status || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleSelectAll = () => {
        if (selectedAttempts.length === attempts.data.length) {
            setSelectedAttempts([]);
        } else {
            setSelectedAttempts(attempts.data.map(a => a.id));
        }
    };

    const handleSelectAttempt = (attemptId: number) => {
        if (selectedAttempts.includes(attemptId)) {
            setSelectedAttempts(selectedAttempts.filter(id => id !== attemptId));
        } else {
            setSelectedAttempts([...selectedAttempts, attemptId]);
        }
    };

    const getStatusBadge = (status: string) => {
        const colors = {
            'in_progress': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            'completed': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'abandoned': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        };
        return colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
    };

    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'completed':
                return <CheckCircle className="h-4 w-4" />;
            case 'in_progress':
                return <Clock className="h-4 w-4" />;
            case 'abandoned':
                return <XCircle className="h-4 w-4" />;
            default:
                return <Clock className="h-4 w-4" />;
        }
    };

    const calculatePercentage = (correct: number, total: number) => {
        if (total === 0) return 0;
        return ((correct / total) * 100).toFixed(1);
    };

    return (
        <AppLayout>
            <Head title="Practice Attempts" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Practice Attempts</h1>
                        <p className="text-muted-foreground">View and manage all practice attempts</p>
                    </div>
                </div>

                {/* Bulk Actions */}
                {selectedAttempts.length > 0 && (
                    <Card className="border-primary">
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <p className="text-sm font-medium">
                                    {selectedAttempts.length} attempt(s) selected
                                </p>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setSelectedAttempts([])}
                                >
                                    Clear Selection
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>Filter practice attempts</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex gap-4">
                            <div className="flex-1">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        placeholder="Search attempts..."
                                        className="pl-9"
                                        onKeyDown={(e) => e.key === 'Enter' && handleFilter()}
                                    />
                                </div>
                            </div>
                            <Select value={status || 'all'} onValueChange={setStatus}>
                                <SelectTrigger className="w-[180px]">
                                    <SelectValue placeholder="All statuses" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">All statuses</SelectItem>
                                    <SelectItem value="in_progress">In Progress</SelectItem>
                                    <SelectItem value="completed">Completed</SelectItem>
                                    <SelectItem value="abandoned">Abandoned</SelectItem>
                                </SelectContent>
                            </Select>
                            <Input
                                type="date"
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                                placeholder="Date From"
                                className="w-[180px]"
                            />
                            <Input
                                type="date"
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                                placeholder="Date To"
                                className="w-[180px]"
                            />
                            <Button onClick={handleFilter}>Filter</Button>
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {attempts.data.map((attempt) => (
                        <Card key={attempt.id} className="hover:shadow-lg transition-shadow">
                            <CardHeader>
                                <div className="flex items-start justify-between">
                                    <div className="flex items-center gap-2 flex-1">
                                        <Checkbox
                                            checked={selectedAttempts.includes(attempt.id)}
                                            onCheckedChange={() => handleSelectAttempt(attempt.id)}
                                        />
                                        <div className="flex-1">
                                            <CardTitle className="text-lg">{attempt.exam.title}</CardTitle>
                                            <CardDescription className="mt-1">
                                                {attempt.exam.exam_type}
                                                {attempt.status === 'completed' && (
                                                    <> • {calculatePercentage(attempt.correct_answers, attempt.total_questions)}%</>
                                                )}
                                            </CardDescription>
                                        </div>
                                    </div>
                                    <span className={`px-2 py-1 text-xs rounded flex items-center gap-1 ${getStatusBadge(attempt.status)}`}>
                                        {getStatusIcon(attempt.status)}
                                        {attempt.status.replace('_', ' ')}
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-2">
                                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <User className="h-4 w-4" />
                                        <span className="truncate">{attempt.user.name}</span>
                                    </div>
                                    <div className="text-xs text-muted-foreground truncate">
                                        {attempt.user.email}
                                    </div>
                                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <Calendar className="h-4 w-4" />
                                        <span>{new Date(attempt.started_at).toLocaleDateString()}</span>
                                    </div>
                                    {attempt.status === 'completed' && (
                                        <div className="flex items-center gap-2 text-sm">
                                            <BookOpen className="h-4 w-4 text-muted-foreground" />
                                            <span>
                                                {attempt.correct_answers}/{attempt.total_questions} correct
                                            </span>
                                        </div>
                                    )}
                                    <div className="flex gap-2 pt-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            asChild
                                            className="flex-1"
                                        >
                                            <Link href={`/admin/practice-attempts/${attempt.id}`}>
                                                <Eye className="mr-2 h-4 w-4" />
                                                View
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {attempts.data.length === 0 && (
                    <Card>
                        <CardContent className="py-10 text-center">
                            <p className="text-muted-foreground">No attempts found.</p>
                        </CardContent>
                    </Card>
                )}

                {attempts.last_page > 1 && (
                    <div className="flex justify-center gap-2">
                        <Button
                            variant="outline"
                            disabled={attempts.current_page === 1}
                            onClick={() => router.get('/admin/practice-attempts', { page: attempts.current_page - 1, ...filters })}
                        >
                            Previous
                        </Button>
                        <span className="flex items-center px-4 text-sm text-muted-foreground">
                            Page {attempts.current_page} of {attempts.last_page}
                        </span>
                        <Button
                            variant="outline"
                            disabled={attempts.current_page === attempts.last_page}
                            onClick={() => router.get('/admin/practice-attempts', { page: attempts.current_page + 1, ...filters })}
                        >
                            Next
                        </Button>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
