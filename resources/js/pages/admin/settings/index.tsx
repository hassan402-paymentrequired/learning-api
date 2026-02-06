import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Settings, Database, Users, BookOpen, Activity } from 'lucide-react';
import { Link } from '@inertiajs/react';
import admin from '@/routes/admin';

export default function SettingsIndex() {
    return (
        <AppLayout>
            <Head title="Settings" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-bold">Settings</h1>
                    <p className="text-muted-foreground">Manage system settings and configurations</p>
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <Card className="hover:shadow-lg transition-shadow cursor-pointer">
                        <Link href={admin.subjects.index().url}>
                            <CardHeader>
                                <div className="flex items-center gap-3">
                                    <BookOpen className="h-5 w-5 text-primary" />
                                    <CardTitle>Subject Management</CardTitle>
                                </div>
                                <CardDescription>
                                    Manage subjects available for practice exams
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    Add, edit, or remove subjects that students can practice
                                </p>
                            </CardContent>
                        </Link>
                    </Card>

                    <Card className="hover:shadow-lg transition-shadow">
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <Settings className="h-5 w-5 text-primary" />
                                <CardTitle>System Settings</CardTitle>
                            </div>
                            <CardDescription>
                                Configure platform-wide settings
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                Platform name, email settings, and general configuration
                            </p>
                            <p className="text-xs text-muted-foreground mt-2 italic">
                                Coming soon
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="hover:shadow-lg transition-shadow">
                        <CardHeader>
                            <div className="flex items-center gap-3">
                                <Database className="h-5 w-5 text-primary" />
                                <CardTitle>Data Management</CardTitle>
                            </div>
                            <CardDescription>
                                Export and manage platform data
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm text-muted-foreground">
                                Export exams, questions, and user data
                            </p>
                            <p className="text-xs text-muted-foreground mt-2 italic">
                                Coming soon
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
