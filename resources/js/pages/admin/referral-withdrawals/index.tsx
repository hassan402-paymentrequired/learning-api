import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { CheckCircle, Clock, Loader2, Search, XCircle } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';

interface WithdrawalUser {
  id: number;
  name: string;
  email: string;
}

interface Withdrawal {
  id: number;
  uuid: string;
  amount: string;
  account_name: string | null;
  account_number: string | null;
  bank_name: string | null;
  phone_number: string | null;
  network: string | null;
  status: 'pending' | 'paid' | 'rejected';
  admin_notes: string | null;
  processed_at: string | null;
  created_at: string;
  user: WithdrawalUser;
  processor?: { id: number; name: string } | null;
}

interface Props {
  withdrawals: {
    data: Withdrawal[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  pendingCount: number;
  filters: {
    status?: string;
    search?: string;
  };
}

export default function ReferralWithdrawalsIndex({ withdrawals, pendingCount, filters }: Props) {
  const [status, setStatus] = useState(filters.status || '');
  const [search, setSearch] = useState(filters.search || '');
  const [selectedWithdrawal, setSelectedWithdrawal] = useState<Withdrawal | null>(null);
  const [nextStatus, setNextStatus] = useState<'paid' | 'rejected' | null>(null);
  const [adminNotes, setAdminNotes] = useState('');
  const [processing, setProcessing] = useState(false);

  const handleFilter = () => {
    router.get('/admin/referral-withdrawals', {
      status: status || undefined,
      search: search || undefined,
    }, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const openAction = (withdrawal: Withdrawal, action: 'paid' | 'rejected') => {
    setSelectedWithdrawal(withdrawal);
    setNextStatus(action);
    setAdminNotes('');
  };

  const submitAction = () => {
    if (!selectedWithdrawal || !nextStatus) return;

    setProcessing(true);
    router.patch(`/admin/referral-withdrawals/${selectedWithdrawal.id}`, {
      status: nextStatus,
      admin_notes: adminNotes || undefined,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(`Withdrawal marked as ${nextStatus}.`);
        setSelectedWithdrawal(null);
        setNextStatus(null);
      },
      onError: () => {
        toast.error('Failed to update withdrawal.');
      },
      onFinish: () => setProcessing(false),
    });
  };

  const statusBadge = (value: string) => {
    const styles = {
      pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
      paid: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
      rejected: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    };

    return styles[value as keyof typeof styles] || 'bg-gray-100 text-gray-800';
  };

  return (
    <AppLayout>
      <Head title="Referral Withdrawals" />

      <div className="space-y-6">
        <div>
          <h1 className="text-3xl font-bold">Referral Withdrawals</h1>
          <p className="text-muted-foreground">
            Review and process referral payout requests. {pendingCount} pending request{pendingCount === 1 ? '' : 's'}.
          </p>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Filters</CardTitle>
            <CardDescription>Search by user or filter by status</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid gap-4 md:grid-cols-3">
              <Input
                placeholder="Search name or email"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
              <Select value={status || 'all'} onValueChange={(value) => setStatus(value === 'all' ? '' : value)}>
                <SelectTrigger>
                  <SelectValue placeholder="All statuses" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All statuses</SelectItem>
                  <SelectItem value="pending">Pending</SelectItem>
                  <SelectItem value="paid">Paid</SelectItem>
                  <SelectItem value="rejected">Rejected</SelectItem>
                </SelectContent>
              </Select>
              <Button onClick={handleFilter}>
                <Search className="mr-2 h-4 w-4" />
                Apply
              </Button>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Requests</CardTitle>
            <CardDescription>{withdrawals.total} total withdrawal request(s)</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b text-left">
                    <th className="py-3 pr-4">User</th>
                    <th className="py-3 pr-4">Amount</th>
                    <th className="py-3 pr-4">Payout details</th>
                    <th className="py-3 pr-4">Status</th>
                    <th className="py-3 pr-4">Requested</th>
                    <th className="py-3">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {withdrawals.data.map((withdrawal) => (
                    <tr key={withdrawal.id} className="border-b align-top">
                      <td className="py-4 pr-4">
                        <div className="font-medium">{withdrawal.user.name}</div>
                        <div className="text-muted-foreground">{withdrawal.user.email}</div>
                      </td>
                      <td className="py-4 pr-4 font-semibold">
                        ₦{Number(withdrawal.amount).toLocaleString()}
                      </td>
                      <td className="py-4 pr-4">
                        {withdrawal.account_number ? (
                          <>
                            <div className="font-medium">{withdrawal.account_name}</div>
                            <div>{withdrawal.bank_name}</div>
                            <div className="text-muted-foreground">{withdrawal.account_number}</div>
                          </>
                        ) : (
                          <>
                            <div>{withdrawal.phone_number}</div>
                            <div className="uppercase text-muted-foreground">{withdrawal.network}</div>
                          </>
                        )}
                      </td>
                      <td className="py-4 pr-4">
                        <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${statusBadge(withdrawal.status)}`}>
                          {withdrawal.status}
                        </span>
                        {withdrawal.admin_notes && (
                          <p className="mt-2 text-xs text-muted-foreground">{withdrawal.admin_notes}</p>
                        )}
                      </td>
                      <td className="py-4 pr-4 text-muted-foreground">
                        {new Date(withdrawal.created_at).toLocaleString()}
                      </td>
                      <td className="py-4">
                        {withdrawal.status === 'pending' ? (
                          <div className="flex gap-2">
                            <Button size="sm" onClick={() => openAction(withdrawal, 'paid')}>
                              <CheckCircle className="mr-1 h-4 w-4" />
                              Mark paid
                            </Button>
                            <Button size="sm" variant="outline" onClick={() => openAction(withdrawal, 'rejected')}>
                              <XCircle className="mr-1 h-4 w-4" />
                              Reject
                            </Button>
                          </div>
                        ) : (
                          <div className="text-xs text-muted-foreground">
                            {withdrawal.processor?.name ? `By ${withdrawal.processor.name}` : 'Processed'}
                            {withdrawal.processed_at && (
                              <div>{new Date(withdrawal.processed_at).toLocaleString()}</div>
                            )}
                          </div>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>

              {withdrawals.data.length === 0 && (
                <div className="py-12 text-center text-muted-foreground">
                  <Clock className="mx-auto mb-3 h-10 w-10 opacity-40" />
                  No withdrawal requests found.
                </div>
              )}
            </div>

            {withdrawals.last_page > 1 && (
              <div className="mt-6 flex justify-between">
                <Button
                  variant="outline"
                  disabled={withdrawals.current_page <= 1}
                  onClick={() => router.get('/admin/referral-withdrawals', { page: withdrawals.current_page - 1, ...filters })}
                >
                  Previous
                </Button>
                <span className="self-center text-sm text-muted-foreground">
                  Page {withdrawals.current_page} of {withdrawals.last_page}
                </span>
                <Button
                  variant="outline"
                  disabled={withdrawals.current_page >= withdrawals.last_page}
                  onClick={() => router.get('/admin/referral-withdrawals', { page: withdrawals.current_page + 1, ...filters })}
                >
                  Next
                </Button>
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      <Dialog open={!!selectedWithdrawal} onOpenChange={(open) => !open && setSelectedWithdrawal(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {nextStatus === 'paid' ? 'Mark withdrawal as paid' : 'Reject withdrawal'}
            </DialogTitle>
            <DialogDescription>
              {selectedWithdrawal && (
                <>
                  Confirm {nextStatus === 'paid' ? 'payment of' : 'rejection for'} ₦
                  {Number(selectedWithdrawal.amount).toLocaleString()}
                  {selectedWithdrawal.account_number
                    ? ` to ${selectedWithdrawal.account_name} (${selectedWithdrawal.bank_name} · ${selectedWithdrawal.account_number})`
                    : ` to ${selectedWithdrawal.phone_number}`}.
                </>
              )}
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-2">
            <label className="text-sm font-medium">Admin notes (optional)</label>
            <Textarea
              value={adminNotes}
              onChange={(e) => setAdminNotes(e.target.value)}
              placeholder="Payment reference or rejection reason"
            />
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setSelectedWithdrawal(null)} disabled={processing}>
              Cancel
            </Button>
            <Button onClick={submitAction} disabled={processing}>
              {processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
              Confirm
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </AppLayout>
  );
}
