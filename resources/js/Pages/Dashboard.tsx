import { Head, router, usePage } from '@inertiajs/react';
import { BarChart3, ChevronDown, LayoutDashboard, LogOut, Menu, Moon, Settings2, Sun, Truck, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '../components/ui/button';
import { ContextoSelector } from '../components/ContextoSelector';
import { cn } from '../lib/utils';

type PageProps = {
    auth: {
        user: {
            name: string;
            email: string;
        };
    };
};

const navigation = [
    { label: 'Visão geral', icon: LayoutDashboard, active: true },
    { label: 'Operação', icon: Truck, active: false },
    { label: 'Relatórios', icon: BarChart3, active: false },
    { label: 'Configurações', icon: Settings2, active: false },
];

export default function Dashboard() {
    const { auth } = usePage<PageProps>().props;
    const [mobileOpen, setMobileOpen] = useState(false);
    const [dark, setDark] = useState(() => document.documentElement.classList.contains('dark'));
    const initials = auth.user.name.slice(0, 1).toUpperCase();

    function toggleTheme() {
        const nextDark = !dark;
        document.documentElement.classList.toggle('dark', nextDark);
        localStorage.theme = nextDark ? 'dark' : 'light';
        setDark(nextDark);
    }

    function logout() {
        router.post('/logout');
    }

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex min-h-screen bg-slate-50 dark:bg-slate-950">
                {mobileOpen && <button aria-label="Fechar menu" className="fixed inset-0 z-30 bg-slate-950/40 md:hidden" onClick={() => setMobileOpen(false)} />}
                <aside className={cn('fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-slate-200 bg-white p-6 transition-transform dark:border-slate-800 dark:bg-slate-900 md:static md:translate-x-0', mobileOpen && 'translate-x-0')}>
                    <div className="flex items-start justify-between">
                        <div>
                            <a href="/dashboard" className="text-2xl font-bold tracking-tight">Scali <span className="text-sky-500">Logis</span></a>
                            <p className="mt-1 text-xs uppercase tracking-[0.2em] text-slate-400">Gestão inteligente</p>
                        </div>
                        <Button variant="ghost" className="md:hidden" aria-label="Fechar menu" onClick={() => setMobileOpen(false)}><X size={18} /></Button>
                    </div>
                    <nav className="mt-12 grid gap-2">
                        {navigation.map(({ label, icon: Icon, active }) => <a key={label} href="#" className={cn('flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition-colors', active ? 'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-white')}><Icon size={18} />{label}</a>)}
                    </nav>
                    <div className="mt-auto grid gap-4">
                        <Button variant="outline" className="justify-start" onClick={toggleTheme}>{dark ? <Sun size={18} /> : <Moon size={18} />} {dark ? 'Tema claro' : 'Tema escuro'}</Button>
                        <div className="flex items-center gap-3 border-t border-slate-200 pt-4 dark:border-slate-800"><div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-sky-100 font-bold text-sky-700 dark:bg-sky-950 dark:text-sky-300">{initials}</div><div className="min-w-0"><p className="truncate text-sm font-semibold">{auth.user.name}</p><p className="truncate text-xs text-slate-500">{auth.user.email}</p></div><Button variant="ghost" className="ml-auto px-2" aria-label="Sair" onClick={logout}><LogOut size={17} /></Button></div>
                    </div>
                </aside>
                <main className="min-w-0 flex-1">
                    <header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-5 py-4 dark:border-slate-800 dark:bg-slate-900 md:px-10"><div className="flex items-center gap-3"><Button variant="ghost" className="px-2 md:hidden" aria-label="Abrir menu" onClick={() => setMobileOpen(true)}><Menu size={21} /></Button><div><p className="text-sm text-slate-500">Painel principal</p><h1 className="text-xl font-bold">Olá, {auth.user.name}</h1></div></div><div className="flex items-center gap-2"><ContextoSelector /><Button variant="outline" className="px-3" onClick={toggleTheme}>{dark ? <Sun size={17} /> : <Moon size={17} />}<span className="hidden sm:inline">Tema</span></Button></div></header>
                    <section className="p-5 md:p-10"><div className="rounded-3xl bg-gradient-to-br from-sky-600 to-indigo-700 p-7 text-white shadow-xl shadow-sky-900/10 md:p-10"><p className="text-sm font-medium text-sky-100">Fundação do projeto</p><h2 className="mt-2 text-3xl font-bold tracking-tight md:text-4xl">Sua operação começa aqui.</h2><p className="mt-4 max-w-2xl text-sky-100">O Scali Logis está preparado para receber os módulos que conectam sua empresa, equipe e operação em um só lugar.</p></div><div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">{[['Status da conta', 'Ativa', 'text-emerald-500'], ['Próxima etapa', 'Configuração', 'text-slate-950 dark:text-white'], ['Ambiente', 'Scali Logis', 'text-slate-950 dark:text-white']].map(([label, value, color]) => <div key={label} className="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"><p className="text-sm text-slate-500">{label}</p><p className={cn('mt-3 text-2xl font-bold', color)}>{value}</p></div>)}</div><div className="mt-8 flex items-center gap-2 text-sm text-slate-500"><ChevronDown size={16} /> Módulos empresariais e operacionais serão adicionados nas próximas etapas.</div></section>
                </main>
            </div>
        </>
    );
}
