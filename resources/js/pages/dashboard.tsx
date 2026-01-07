import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    BookOpen,
    FileQuestion,
    TrendingUp,
    Users,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

interface Stats {
    total_users: number;
    active_users: number;
    total_exams: number;
    active_exams: number;
    total_questions: number;
    total_attempts: number;
    completed_attempts: number;
    average_score: number;
}

interface RecentAttempt {
    id: number;
    user_name: string;
    exam_title: string;
    score: number;
    percentage: number;
    completed_at: string;
}

interface RecentUser {
    id: number;
    name: string;
    email: string;
    created_at: string;
}

interface RecentExam {
    id: number;
    title: string;
    type: string;
    exam_type: string;
    created_at: string;
}

interface TopExam {
    id: number;
    title: string;
    attempts: number;
    avg_score: number;
}

interface SubjectPerformance {
    subject: string;
    attempts: number;
    avg_score: number;
}

interface Props {
    stats: Stats;
    recentAttempts: RecentAttempt[];
    recentUsers: RecentUser[];
    recentExams: RecentExam[];
    topExams: TopExam[];
    subjectPerformance: SubjectPerformance[];
}

export default function Dashboard({
    stats,
    recentAttempts,
    recentUsers,
    recentExams,
    topExams,
    subjectPerformance,
}: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-3xl font-bold">Dashboard</h1>
                    <p className="text-muted-foreground">
                        Overview of your platform
                    </p>
                </div>

                {/* Statistics Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Users
                            </CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.total_users}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {stats.active_users} active in last 30 days
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Exams
                            </CardTitle>
                            <BookOpen className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.total_exams}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {stats.active_exams} active exams
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Questions
                            </CardTitle>
                            <FileQuestion className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.total_questions}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Across all exams
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Practice Attempts
                            </CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats.total_attempts}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {stats.completed_attempts} completed
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Performance Metrics */}
                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Average Score</CardTitle>
                            <CardDescription>
                                Overall platform performance
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="text-4xl font-bold">
                                {typeof stats?.average_score === 'number'
                                    ? stats.average_score.toFixed(1)
                                    : '0.0'}
                                %
                            </div>
                            <div className="mt-4 flex items-center gap-2">
                                <TrendingUp className="h-4 w-4 text-green-500" />
                                <span className="text-sm text-muted-foreground">
                                    Platform average
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Top Performing Exams</CardTitle>
                            <CardDescription>
                                Most attempted exams
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {topExams.length > 0 ? (
                                    topExams.map((exam, index) => (
                                        <div
                                            key={exam.id}
                                            className="flex items-center justify-between"
                                        >
                                            <div className="flex items-center gap-2">
                                                <span className="text-sm font-medium">
                                                    #{index + 1}
                                                </span>
                                                <span className="text-sm">
                                                    {exam.title}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-4">
                                                <span className="text-xs text-muted-foreground">
                                                    {exam.attempts} attempts
                                                </span>
                                                <span className="text-sm font-medium">
                                                    {typeof exam.avg_score ===
                                                    'number'
                                                        ? exam.avg_score.toFixed(
                                                              1,
                                                          )
                                                        : '0.0'}
                                                    %
                                                </span>
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        No data available
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Recent Activity and Subject Performance */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent Practice Attempts</CardTitle>
                            <CardDescription>
                                Latest completed attempts
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {recentAttempts.length > 0 ? (
                                    recentAttempts.map((attempt) => (
                                        <div
                                            key={attempt.id}
                                            className="flex items-center justify-between border-b pb-2 last:border-0"
                                        >
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {attempt.user_name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {attempt.exam_title}
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-sm font-medium">
                                                    {typeof attempt.percentage ===
                                                    'number'
                                                        ? attempt.percentage.toFixed(
                                                              1,
                                                          )
                                                        : '0.0'}
                                                    %
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {attempt.completed_at}
                                                </p>
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        No recent attempts
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Recent Users</CardTitle>
                            <CardDescription>
                                Newly registered users
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {recentUsers.length > 0 ? (
                                    recentUsers.map((user) => (
                                        <div
                                            key={user.id}
                                            className="flex items-center justify-between border-b pb-2 last:border-0"
                                        >
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {user.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {user.email}
                                                </p>
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                {user.created_at}
                                            </p>
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        No recent users
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Subject Performance</CardTitle>
                            <CardDescription>
                                Average scores by subject
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {subjectPerformance.length > 0 ? (
                                    subjectPerformance.map((subject, index) => (
                                        <div
                                            key={index}
                                            className="flex items-center justify-between border-b pb-2 last:border-0"
                                        >
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {subject.subject}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {subject.attempts} attempts
                                                </p>
                                            </div>
                                            <p className="text-sm font-medium">
                                                {typeof subject.avg_score ===
                                                'number'
                                                    ? subject.avg_score.toFixed(
                                                          1,
                                                      )
                                                    : '0.0'}
                                                %
                                            </p>
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        No data available
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Recent Exams */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Recent Exams</CardTitle>
                                <CardDescription>
                                    Latest added practice sets
                                </CardDescription>
                            </div>
                            <Link
                                href={admin.exams.index().url}
                                className="text-sm text-primary hover:underline"
                            >
                                View all
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {recentExams.length > 0 ? (
                                recentExams.map((exam) => (
                                    <div
                                        key={exam.id}
                                        className="flex items-center justify-between border-b pb-2 last:border-0"
                                    >
                                        <div>
                                            <p className="text-sm font-medium">
                                                {exam.title}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {exam.type} • {exam.exam_type}
                                            </p>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {exam.created_at}
                                        </p>
                                    </div>
                                ))
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No exams yet
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
