import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, ArrowLeft, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import admin from '@/routes/admin';
import { type BreadcrumbItem } from '@/types';
import { toast } from 'sonner';

interface SubjectTest {
    id: number;
    name: string;
    order: number;
    subject_id: number;
}

interface Subject {
    id: number;
    name: string;
}

interface Props {
    subject: Subject;
    tests: SubjectTest[];
}

export default function SubjectTestsIndex({ subject, tests }: Props) {
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [testToDelete, setTestToDelete] = useState<{ id: number; name: string } | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Subjects', href: admin.subjects.index().url },
        { title: subject.name, href: `/admin/subjects/${subject.id}` },
        { title: 'Tests', href: '#' },
    ];

    const confirmDelete = () => {
        if (!testToDelete) return;
        router.delete(`/admin/subjects/${subject.id}/tests/${testToDelete.id}`, {
            onSuccess: () => {
                toast.success('Test deleted successfully');
                setDeleteDialogOpen(false);
                setTestToDelete(null);
            },
            onError: () => toast.error('Failed to delete test'),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Tests - ${subject.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="icon" asChild>
                            <Link href={`/admin/subjects/${subject.id}`}>
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold">Tests</h1>
                            <p className="text-muted-foreground">
                                Manage tests for {subject.name} (DLI question grouping)
                            </p>
                        </div>
                    </div>
                    <Button asChild>
                        <Link href={`/admin/subjects/${subject.id}/tests/create`}>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Test
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Tests for this subject</CardTitle>
                        <CardDescription>
                            Assign questions to these tests when creating/editing DLI questions. Students will select a test when starting practice.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {tests.length === 0 ? (
                            <p className="text-muted-foreground py-6 text-center">
                                No tests yet. Add a test (e.g. &quot;Test 1&quot;, &quot;Test 2&quot;) to group questions.
                            </p>
                        ) : (
                            <ul className="space-y-2">
                                {tests.map((test) => (
                                    <li
                                        key={test.id}
                                        className="flex items-center justify-between rounded-lg border p-3"
                                    >
                                        <span className="font-medium">{test.name}</span>
                                        <div className="flex gap-2">
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={`/admin/subjects/${subject.id}/tests/${test.id}/edit`}>
                                                    <Pencil className="h-4 w-4" />
                                                </Link>
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => {
                                                    setTestToDelete({ id: test.id, name: test.name });
                                                    setDeleteDialogOpen(true);
                                                }}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete test</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete &quot;{testToDelete?.name}&quot;? Questions linked to this test will be unlinked.
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
        </AppLayout>
    );
}
