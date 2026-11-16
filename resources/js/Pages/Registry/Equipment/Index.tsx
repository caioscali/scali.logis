import { Link, router } from '@inertiajs/react';
import { Edit3, Plus, Search, Truck, Wrench } from 'lucide-react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { Button } from '../../../components/ui/button';

type Equipment = { id: number; equipamento_id: number; placa: string; cidade?: string; ativo: boolean };
type Paginator = { current_page: number; last_page: number; links: { url: string | null; label: string; active: boolean }[] };
type Carrier = { id: number; nome: string };

export default function Index({ tipo, titulo, transportadora, equipamentos, paginator, busca }: { tipo: string; titulo: string; transportadora: Carrier; equipamentos: { data: Equipment[] }; paginator: Paginator; busca: string }) {
    const base = tipo === 'veiculo' ? '/veiculos' : '/implementos';
    const Icon = tipo === 'veiculo' ? Truck : Wrench;

    return <AdminLayout title={titulo}>
        <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="flex items-center gap-2 text-sm font-semibold uppercase tracking-widest text-sky-600"><Icon size={16} /> Cadastro global</p><h1 className="mt-2 text-3xl font-bold">{titulo}</h1><p className="mt-2 text-slate-500">{titulo} vinculados a {transportadora.nome} no contexto ativo.</p></div><Button asChild><Link href={base + '/criar'}><Plus size={16} /> Novo {titulo.slice(0, -1).toLowerCase()}</Link></Button></div>
        <form className="mt-8 flex max-w-xl gap-2" onSubmit={(event) => { event.preventDefault(); const data = new FormData(event.currentTarget); router.get(base, { busca: data.get('busca') }, { preserveState: true }); }}><input name="busca" defaultValue={busca} placeholder="Buscar por placa" className="min-w-0 flex-1 rounded-xl border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-900" /><Button type="submit"><Search size={16} /> Buscar</Button></form>
        <div className="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"><div className="divide-y divide-slate-100 dark:divide-slate-800">{equipamentos.data.length === 0 && <p className="p-8 text-center text-slate-500">Nenhum {tipo} vinculado encontrado.</p>}{equipamentos.data.map((item) => <div key={item.id} className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between"><div><div className="flex flex-wrap items-center gap-2"><h2 className="font-semibold tracking-wide">{item.placa}</h2><span className="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">{item.ativo ? 'Ativo' : 'Inativo'}</span></div>{item.cidade && <p className="mt-1 text-sm text-slate-500">{item.cidade}</p>}</div><div className="flex gap-2"><Button variant="ghost" className="px-2" asChild><Link href={base + '/' + item.equipamento_id}>Ver</Link></Button><Button variant="ghost" className="px-2" asChild><Link href={base + '/' + item.equipamento_id + '/editar'}><Edit3 size={16} /> Editar</Link></Button></div></div>)}</div><div className="flex gap-2 p-4">{paginator.links.map((link) => <Link key={link.label} href={link.url ?? '#'} className={link.active ? 'rounded-lg bg-sky-100 px-3 py-2 text-sm font-semibold text-sky-700' : 'rounded-lg px-3 py-2 text-sm text-slate-500'} dangerouslySetInnerHTML={{ __html: link.label }} />)}</div></div>
    </AdminLayout>;
}
