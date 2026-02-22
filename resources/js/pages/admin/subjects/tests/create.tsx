import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { type BreadcrumbItem } from '@/types';
import InputError from '@/components/input-error';
import admin from '@/routes/admin';

interface Subject {
    id: number;
    name: string;
}

interface Props {
    subject: Subject;
}

export default function SubjectTestCreate({ subject }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Subjects', href: admin.subjects.index().url },
        { title: subject.name, href: `/admin/subjects/${subject.id}` },
        { title: 'Tests', href: `/admin/subjects/${subject.id}/tests` },
        { title: 'Create', href: '#' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        name: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/admin/subjects/${subject.id}/tests`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Create Test - ${subject.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-bold">Create Test</h1>
                    <p className="text-muted-foreground">
                        Add a test for {subject.name}. You can name it anything (e.g. Test 1, Test 2, Module A).
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Test details</CardTitle>
                        <CardDescription>This name will appear when students select which test to attempt.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name *</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g. Test 1"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Creating...' : 'Create Test'}
                                </Button>
                                <Button type="button" variant="outline" asChild>
                                    <Link href={`/admin/subjects/${subject.id}/tests`}>Cancel</Link>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
