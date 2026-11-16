import { Link, router } from '@inertiajs/react';
import { Edit3, Plus, Power, Trash2 } from 'lucide-react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { Button } from '../../../components/ui/button';

type Group = { id: number; nome: string; ativo: boolean; transportadoras_count: number };

export default function Index({ grupos }: { grupos: Group[] }) {
    return <AdminLayout title="Grupos empresariais"><div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-sm font-semibold uppercase tracking-widest text-sky-600">Estrutura empresarial</p><h1 className="mt-2 text-3xl font-bold tracking-tight">Grupos empresariais</h1><p className="mt-2 text-slate-500">Organize as transportadoras por grupo econômico.</p></div><Button asChild><Link href="/admin/grupos-empresariais/create"><Plus size={17} /> Novo grupo</Link></Button></div>
        <div className="mt-8 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"><div className="divide-y divide-slate-100 dark:divide-slate-800">{grupos.length === 0 && <p className="p-8 text-center text-slate-500">Nenhum grupo cadastrado.</p>}{grupos.map((grupo) => <div key={grupo.id} className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between"><div><div className="flex items-center gap-3"><h2 className="font-semibold">{grupo.nome}</h2><span className={grupo.ativo ? 'rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700' : 'rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-500'}>{grupo.ativo ? 'Ativo' : 'Inativo'}</span></div><p className="mt-1 text-sm text-slate-500">{grupo.transportadoras_count} transportadora(s)</p></div><div className="flex items-center gap-2"><Button variant="ghost" className="px-2" onClick={() => router.put('/admin/grupos-empresariais/' + grupo.id, { nome: grupo.nome, ativo: !grupo.ativo })} aria-label="Alternar status"><Power size={17} /></Button><Button variant="ghost" className="px-2" asChild><Link href={'/admin/grupos-empresariais/' + grupo.id + '/edit'}><Edit3 size={17} /></Link></Button><Button variant="ghost" className="px-2 text-rose-500" onClick={() => router.delete('/admin/grupos-empresariais/' + grupo.id)} aria-label="Excluir"><Trash2 size={17} /></Button></div></div>)}</div></div>
    </AdminLayout>;
}
