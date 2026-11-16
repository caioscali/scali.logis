import { Head, Link, router, usePage } from '@inertiajs/react';
import { BookUser, Building2, ChevronLeft, LogOut, Moon, Sun, Truck, Wrench } from 'lucide-react';
import { PropsWithChildren, useState } from 'react';
import { Button } from '../components/ui/button';
import { ContextoSelector } from '../components/ContextoSelector';
import { cn } from '../lib/utils';

type Props = PropsWithChildren<{ title: string; backHref?: string }>;

export default function AdminLayout({ children, title, backHref }: Props) {
    const { auth, contexto } = usePage<{ auth: { user: { name: string; email: string; is_platform_administrator: boolean } }; contexto: { atual: unknown } }>().props;
    const [dark, setDark] = useState(() => document.documentElement.classList.contains('dark'));

    function toggleTheme() {
        const next = !dark;
        document.documentElement.classList.toggle('dark', next);
        localStorage.theme = next ? 'dark' : 'light';
        setDark(next);
    }

    return (
        <>
            <Head title={title} />
            <div className="min-h-screen bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-white">
                <header className="border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-4 md:px-8">
                        <div className="flex items-center gap-5">
                            <Link href="/dashboard" className="text-xl font-bold tracking-tight">Scali <span className="text-sky-500">Logis</span></Link>
                            <span className="hidden h-6 w-px bg-slate-200 dark:bg-slate-700 md:block" />
                            <span className="hidden text-sm font-medium text-slate-500 md:block">Administração empresarial</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <ContextoSelector />
                            {contexto.atual !== null && <Link href="/pessoas" className="hidden items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-100 hover:text-sky-700 dark:hover:bg-slate-800 sm:inline-flex"><BookUser size={16} /> Pessoas</Link>}
                            {contexto.atual !== null && <Link href="/veiculos" className="hidden items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-100 hover:text-sky-700 dark:hover:bg-slate-800 sm:inline-flex"><Truck size={16} /> Veículos</Link>}
                            {contexto.atual !== null && <Link href="/implementos" className="hidden items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-100 hover:text-sky-700 dark:hover:bg-slate-800 sm:inline-flex"><Wrench size={16} /> Implementos</Link>}
                            <Button variant="ghost" className="px-2" onClick={toggleTheme} aria-label="Alternar tema">{dark ? <Sun size={18} /> : <Moon size={18} />}</Button>
                            <Button variant="ghost" className="px-2" onClick={() => router.post('/logout')} aria-label="Sair"><LogOut size={18} /></Button>
                        </div>
                    </div>
                </header>
                <main className="mx-auto max-w-7xl px-5 py-8 md:px-8 md:py-10">
                    {backHref && <Link href={backHref} className="mb-5 inline-flex items-center gap-1 text-sm font-semibold text-sky-600 hover:text-sky-700 dark:text-sky-400"><ChevronLeft size={16} /> Voltar</Link>}
                    {auth.user.is_platform_administrator && <nav className="mb-8 flex flex-wrap gap-2">
                        <Link href="/admin/grupos-empresariais" className={cn('inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold', title.includes('Grupo') ? 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-900')}><Building2 size={16} /> Grupos</Link>
                        <Link href="/admin/transportadoras" className={cn('inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold', title.includes('Transportadora') || title.includes('Filial') ? 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300' : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-900')}><Truck size={16} /> Transportadoras</Link>
                    </nav>}
                    {children}
                </main>
                <footer className="mx-auto max-w-7xl px-5 pb-8 text-xs text-slate-400 md:px-8">Olá, {auth.user.name} · Controle provisório de plataforma</footer>
            </div>
        </>
    );
}
