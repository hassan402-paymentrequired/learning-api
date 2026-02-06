import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { ArrowLeft, User, BookOpen, CheckCircle, XCircle, Clock } from 'lucide-react';
import { Link } from '@inertiajs/react';
import admin from '@/routes/admin';
import { Button } from '@/components/ui/button';

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
    time_spent: number | null;
    percentage?: number;
}

interface Result {
    question: {
        id: number;
        question_text: string;
        explanation: string | null;
        points: number;
        order: number;
    };
    user_answer: {
        id: number;
        answer_text: string;
        order: string;
    } | null;
    correct_answer: {
        id: number;
        answer_text: string;
        order: string;
    } | null;
    is_correct: boolean;
    time_spent: number;
}

interface Props {
    attempt: Attempt;
    results: Result[];
}

export default function ShowPracticeAttempt({ attempt, results }: Props) {
    const formatTime = (seconds: number) => {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        if (hours > 0) {
            return `${hours}h ${minutes}m`;
        }
        return `${minutes}m`;
    };

    return (
        <AppLayout>
            <Head title={`Practice Attempt: ${attempt.exam.title}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button asChild variant="outline" size="sm">
                            <Link href="/admin/practice-attempts">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold">Practice Attempt Details</h1>
                            <p className="text-muted-foreground">View detailed results and answers</p>
                        </div>
                    </div>
                </div>

                {/* Attempt Info */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-medium">User</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-2">
                                <User className="h-4 w-4 text-muted-foreground" />
                                <div>
                                    <p className="font-medium">{attempt.user.name}</p>
                                    <p className="text-sm text-muted-foreground">{attempt.user.email}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-medium">Exam</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-2">
                                <BookOpen className="h-4 w-4 text-muted-foreground" />
                                <div>
                                    <p className="font-medium">{attempt.exam.title}</p>
                                    <p className="text-sm text-muted-foreground">{attempt.exam.exam_type}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm font-medium">Score</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {attempt.total_questions > 0 
                                    ? ((attempt.correct_answers / attempt.total_questions) * 100).toFixed(1)
                                    : 0}%
                            </div>
                            <p className="text-sm text-muted-foreground">
                                {attempt.correct_answers} / {attempt.total_questions} correct
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Attempt Details */}
                <Card>
                    <CardHeader>
                        <CardTitle>Attempt Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-4">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">Status</p>
                                <p className="text-sm capitalize">{attempt.status.replace('_', ' ')}</p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">Started</p>
                                <p className="text-sm">{new Date(attempt.started_at).toLocaleString()}</p>
                            </div>
                            {attempt.completed_at && (
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">Completed</p>
                                    <p className="text-sm">{new Date(attempt.completed_at).toLocaleString()}</p>
                                </div>
                            )}
                            {attempt.time_spent && (
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">Time Spent</p>
                                    <p className="text-sm">{formatTime(attempt.time_spent)}</p>
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Results */}
                {attempt.status === 'completed' && results.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Question Results</CardTitle>
                            <CardDescription>Detailed breakdown of answers</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-6">
                                {results.map((result, index) => (
                                    <div key={result.question.id} className="border-b pb-6 last:border-0">
                                        <div className="flex items-start justify-between mb-4">
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2 mb-2">
                                                    <span className="text-sm font-medium text-muted-foreground">
                                                        Question {result.question.order}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">•</span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {result.question.points} point{result.question.points !== 1 ? 's' : ''}
                                                    </span>
                                                    {result.is_correct ? (
                                                        <CheckCircle className="h-4 w-4 text-green-500" />
                                                    ) : (
                                                        <XCircle className="h-4 w-4 text-red-500" />
                                                    )}
                                                </div>
                                                <p className="text-sm mb-4">{result.question.question_text}</p>
                                            </div>
                                        </div>

                                        <div className="grid gap-3 md:grid-cols-2">
                                            <div className={`p-3 rounded border ${result.is_correct ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20' : 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20'}`}>
                                                <p className="text-xs font-medium text-muted-foreground mb-1">Your Answer</p>
                                                <p className="text-sm">
                                                    {result.user_answer ? (
                                                        <span>
                                                            <span className="font-medium">{result.user_answer.order}.</span> {result.user_answer.answer_text}
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground">No answer provided</span>
                                                    )}
                                                </p>
                                            </div>
                                            <div className="p-3 rounded border border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20">
                                                <p className="text-xs font-medium text-muted-foreground mb-1">Correct Answer</p>
                                                <p className="text-sm">
                                                    {result.correct_answer ? (
                                                        <span>
                                                            <span className="font-medium">{result.correct_answer.order}.</span> {result.correct_answer.answer_text}
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground">N/A</span>
                                                    )}
                                                </p>
                                            </div>
                                        </div>

                                        {result.question.explanation && (
                                            <div className="mt-3 p-3 rounded bg-muted">
                                                <p className="text-xs font-medium text-muted-foreground mb-1">Explanation</p>
                                                <p className="text-sm">{result.question.explanation}</p>
                                            </div>
                                        )}

                                        {result.time_spent > 0 && (
                                            <div className="mt-2 flex items-center gap-1 text-xs text-muted-foreground">
                                                <Clock className="h-3 w-3" />
                                                Time spent: {formatTime(result.time_spent)}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
