import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';

export default function CreateExamCategory() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        slug: '',
        icon_name: '',
        flow_type: 'standard',
        description: '',
        is_active: true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/exam-categories');
    };

    return (
        <AppLayout>
            <Head title="Create ExamCategory" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <div>
                            <h1 className="text-2xl font-bold">Create ExamCategory</h1>
                            <p className="text-muted-foreground">Add a new examCategory for Unilag practice</p>
                        </div>
                    </div>
                </div>

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>ExamCategory Information</CardTitle>
                        <CardDescription>Enter the details for the new examCategory</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">ExamCategory Name *</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g., Faculty of Science, Faculty of Arts"
                                    required
                                />
                                {errors.name && (
                                    <p className="text-sm text-red-500">{errors.name}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="icon_name">Icon Name (MaterialIcons)</Label>
                                <Input
                                    id="icon_name"
                                    value={data.icon_name}
                                    onChange={(e) => setData('icon_name', e.target.value)}
                                    placeholder="e.g., menu-book, account-balance"
                                />
                                {errors.icon_name && (
                                    <p className="text-sm text-red-500">{errors.icon_name}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="flow_type">Flow Type *</Label>
                                <select
                                    id="flow_type"
                                    value={data.flow_type}
                                    onChange={(e) => setData('flow_type', e.target.value)}
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    required
                                >
                                    <option value="standard">Standard Flow</option>
                                    <option value="departmental">Departmental Flow</option>
                                </select>
                                {errors.flow_type && (
                                    <p className="text-sm text-red-500">{errors.flow_type}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Optional description for this examCategory"
                                    rows={3}
                                />
                                {errors.description && (
                                    <p className="text-sm text-red-500">{errors.description}</p>
                                )}
                            </div>

                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) => setData('is_active', checked === true)}
                                />
                                <Label htmlFor="is_active" className="cursor-pointer">
                                    Active (visible to students)
                                </Label>
                            </div>

                            <div className="flex gap-2 pt-4">
                                <Button type="submit" disabled={processing}>
                                    <Save className="mr-2 h-4 w-4" />
                                    {processing ? 'Creating...' : 'Create ExamCategory'}
                                </Button>
                                <Button type="button" variant="outline" asChild>
                                    <Link href="/admin/exam-categories">Cancel</Link>
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
