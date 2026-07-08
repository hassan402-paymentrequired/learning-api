import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { BookOpen, Folder, LayoutGrid, FileText, Users, Activity, Settings, Database, GraduationCap, Building2, Wallet } from 'lucide-react';
import AppLogo from './app-logo';
import admin from '@/routes/admin';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Users',
        href: admin.users.index().url,
        icon: Users,
    },
    {
        title: 'Past Questions',
        href: admin.exams.index().url,
        icon: FileText,
    },
    {
        title: 'Practice Attempts',
        href: '/admin/practice-attempts',
        icon: Activity,
    },
    {
        title: 'Referral Withdrawals',
        href: '/admin/referral-withdrawals',
        icon: Wallet,
    },
    {
        title: 'Question Bank',
        href: '/admin/questions',
        icon: Database,
    },
    {
        title: 'Exam Categories',
        href: '/admin/exam-categories',
        icon: BookOpen,
    },
    {
        title: 'Departments',
        href: '/admin/departments',
        icon: Building2,
    },
    {
        title: 'Subjects',
        href: admin.subjects.index().url,
        icon: GraduationCap,
    },
    {
        title: 'Settings',
        href: admin.settings.index().url,
        icon: Settings,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                {/* <NavFooter items={footerNavItems} className="mt-auto" /> */}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
