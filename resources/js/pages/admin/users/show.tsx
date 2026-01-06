import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { User as UserIcon, Mail, Calendar, Activity, Award, Clock, TrendingUp, ArrowLeft } from 'lucide-react';
import { Link } from '@inertiajs/react';
import admin from '@/routes/admin';
import { Button } from '@/components/ui/button';

interface User {
    id: number;
    name: string;
    email: string;
    created_at: string;
    exam_attempts_count: number;
}

interface Stats {
    total_attempts: number;
    completed_attempts: number;
    average_score: number;
    total_time_spent: number;
}

interface PracticeHistory {
    id: number;
    exam_title: string;
    exam_type: string;
    status: string;
    score: number;
    correct_answers: number;
    total_questions: number;
    percentage: number;
    started_at: string;
    completed_at: string | null;
}

interface SubjectPerformance {
    subject: string;
    attempts: number;
    avg_score: number;
}

interface Props {
    user: User;
    stats: Stats;
    practiceHistory: PracticeHistory[];
    subjectPerformance: SubjectPerformance[];
}

export default function ShowUser({ user, stats, practiceHistory, subjectPerformance }: Props) {
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
            <Head title={`User: ${user.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button asChild variant="outline" size="sm">
                            <Link href={admin.users.index().url}>
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold">{user.name}</h1>
                            <p className="text-muted-foreground">User profile and practice history</p>
                        </div>
                    </div>
                </div>

                {/* User Info */}
                <Card>
                    <CardHeader>
                        <CardTitle>User Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="flex items-center gap-3">
                                <UserIcon className="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p className="text-sm font-medium">Name</p>
                                    <p className="text-sm text-muted-foreground">{user.name}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Mail className="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p className="text-sm font-medium">Email</p>
                                    <p className="text-sm text-muted-foreground">{user.email}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Calendar className="h-5 w-5 text-muted-foreground" />
                                <div>
                                    <p className="text-sm font-medium">Joined</p>
                                    <p className="text-sm text-muted-foreground">
                                        {new Date(user.created_at).toLocaleDateString()}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Statistics */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Attempts</CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_attempts}</div>
                            <p className="text-xs text-muted-foreground">
                                {stats.completed_attempts} completed
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Average Score</CardTitle>
                            <Award className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.average_score.toFixed(1)}%</div>
                            <p className="text-xs text-muted-foreground">
                                Across all attempts
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Time Spent</CardTitle>
                            <Clock className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatTime(stats.total_time_spent)}</div>
                            <p className="text-xs text-muted-foreground">
                                Total practice time
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Completion Rate</CardTitle>
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.total_attempts > 0 
                                    ? ((stats.completed_attempts / stats.total_attempts) * 100).toFixed(1)
                                    : 0}%
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Completed attempts
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Practice History and Subject Performance */}
                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Practice History</CardTitle>
                            <CardDescription>Recent practice attempts</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {practiceHistory.length > 0 ? (
                                    practiceHistory.map((attempt) => (
                                        <div key={attempt.id} className="flex items-center justify-between border-b pb-3 last:border-0">
                                            <div>
                                                <p className="text-sm font-medium">{attempt.exam_title}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {attempt.exam_type} • {attempt.status}
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-sm font-medium">{attempt.percentage.toFixed(1)}%</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {attempt.correct_answers}/{attempt.total_questions}
                                                </p>
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-sm text-muted-foreground">No practice history</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Subject Performance</CardTitle>
                            <CardDescription>Average scores by subject</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {subjectPerformance.length > 0 ? (
                                    subjectPerformance.map((subject, index) => (
                                        <div key={index} className="flex items-center justify-between border-b pb-3 last:border-0">
                                            <div>
                                                <p className="text-sm font-medium">{subject.subject}</p>
                                                <p className="text-xs text-muted-foreground">{subject.attempts} attempts</p>
                                            </div>
                                            <p className="text-sm font-medium">{subject.avg_score.toFixed(1)}%</p>
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-sm text-muted-foreground">No subject data available</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
