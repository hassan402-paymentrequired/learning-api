import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { router } from '@inertiajs/react';
import { Head } from '@inertiajs/react';
import { Search, Eye, Calendar, User, BookOpen } from 'lucide-react';
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

    const handleReset = () => {
        setStatus('');
        setDateFrom('');
        setDateTo('');
        router.get('/admin/practice-attempts', {}, {
            preserveState: false,
        });
    };

    const getStatusBadge = (status: string) => {
        const colors = {
            'in_progress': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            'completed': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'abandoned': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        };
        return colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
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

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>Filter practice attempts</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-4">
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Status</label>
                                <Select value={status || 'all'} onValueChange={setStatus}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="All statuses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All statuses</SelectItem>
                                        <SelectItem value="in_progress">In Progress</SelectItem>
                                        <SelectItem value="completed">Completed</SelectItem>
                                        <SelectItem value="abandoned">Abandoned</SelectItem>
                                    </SelectContent>
                                </Select>
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

                {/* Attempts List */}
                <Card>
                    <CardHeader>
                        <CardTitle>All Attempts ({attempts.total})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {attempts.data.length > 0 ? (
                            <div className="space-y-4">
                                {attempts.data.map((attempt) => (
                                    <div
                                        key={attempt.id}
                                        className="flex items-center justify-between border-b pb-4 last:border-0"
                                    >
                                        <div className="flex items-center gap-4 flex-1">
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2 mb-2">
                                                    <User className="h-4 w-4 text-muted-foreground" />
                                                    <p className="font-medium">{attempt.user.name}</p>
                                                    <span className="text-sm text-muted-foreground">({attempt.user.email})</span>
                                                </div>
                                                <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                                    <span className="flex items-center gap-1">
                                                        <BookOpen className="h-3 w-3" />
                                                        {attempt.exam.title}
                                                    </span>
                                                    <span className="flex items-center gap-1">
                                                        <Calendar className="h-3 w-3" />
                                                        {new Date(attempt.started_at).toLocaleDateString()}
                                                    </span>
                                                    {attempt.status === 'completed' && (
                                                        <span className="font-medium text-foreground">
                                                            {calculatePercentage(attempt.correct_answers, attempt.total_questions)}%
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <span className={`px-2 py-1 rounded text-xs font-medium ${getStatusBadge(attempt.status)}`}>
                                                    {attempt.status.replace('_', ' ')}
                                                </span>
                                                <Button asChild variant="outline" size="sm">
                                                    <Link href={`/admin/practice-attempts/${attempt.id}`}>
                                                        <Eye className="mr-2 h-4 w-4" />
                                                        View
                                                    </Link>
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                ))}

                                {/* Pagination */}
                                {attempts.last_page > 1 && (
                                    <div className="flex items-center justify-between pt-4">
                                        <p className="text-sm text-muted-foreground">
                                            Showing {((attempts.current_page - 1) * attempts.per_page) + 1} to{' '}
                                            {Math.min(attempts.current_page * attempts.per_page, attempts.total)} of {attempts.total} attempts
                                        </p>
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={attempts.current_page === 1}
                                                onClick={() => router.get('/admin/practice-attempts', { ...filters, page: attempts.current_page - 1 })}
                                            >
                                                Previous
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={attempts.current_page === attempts.last_page}
                                                onClick={() => router.get('/admin/practice-attempts', { ...filters, page: attempts.current_page + 1 })}
                                            >
                                                Next
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <p className="text-center text-muted-foreground py-8">No attempts found</p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
