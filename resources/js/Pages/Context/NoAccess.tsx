import { Head, router } from '@inertiajs/react';
import { Building2, LogOut } from 'lucide-react';
import { Button } from '../../components/ui/button';

export default function NoAccess() {
    return <><Head title="Sem vínculo empresarial" /><div className="flex min-h-screen items-center justify-center bg-slate-50 px-5 dark:bg-slate-950"><div className="max-w-md rounded-3xl border border-slate-200 bg-white p-8 text-center dark:border-slate-800 dark:bg-slate-900"><div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300"><Building2 size={26} /></div><h1 className="mt-6 text-2xl font-bold">Nenhum vínculo empresarial</h1><p className="mt-3 text-slate-500">Sua conta ainda não possui uma transportadora e filial autorizadas. Solicite acesso ao administrador da empresa.</p><Button variant="outline" className="mt-7" onClick={() => router.post('/logout')}><LogOut size={16} /> Sair</Button></div></div></>;
}
