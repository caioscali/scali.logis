import { Link, router } from '@inertiajs/react';
import { Edit3, Plus, Power } from 'lucide-react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { Button } from '../../../components/ui/button';

type Branch = { id: number; nome: string; cnpj: string | null; cidade: string; uf: string; matriz: boolean; ativo: boolean };
type Carrier = { id: number; nome_fantasia: string; grupo_empresarial: { nome: string } };

export default function Index({ transportadora, filiais }: { transportadora: Carrier; filiais: Branch[] }) {
    return (
        <AdminLayout title="Filiais" backHref={'/admin/transportadoras/' + transportadora.id}>
            <div className="flex items-end justify-between gap-4">
                <div><p className="text-sm font-semibold uppercase tracking-widest text-sky-600">{transportadora.grupo_empresarial.nome}</p><h1 className="mt-2 text-3xl font-bold">Filiais de {transportadora.nome_fantasia}</h1></div>
                <Button asChild><Link href={'/admin/transportadoras/' + transportadora.id + '/filiais/create'}><Plus size={16} /> Nova filial</Link></Button>
            </div>
            <div className="mt-8 grid gap-4 sm:grid-cols-2">
                {filiais.map((filial) => <div key={filial.id} className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex items-center justify-between gap-3"><h2 className="font-semibold">{filial.nome}</h2>{filial.matriz && <span className="rounded-full bg-sky-100 px-2 py-1 text-xs font-semibold text-sky-700">Matriz</span>}</div>
                    <p className="mt-2 text-sm text-slate-500">{filial.cidade} · {filial.uf}</p>
                    <div className="mt-4 flex gap-3">
                        <Button variant="ghost" className="px-0 text-sky-600" asChild><Link href={'/admin/transportadoras/' + transportadora.id + '/filiais/' + filial.id + '/edit'}><Edit3 size={15} /> Editar</Link></Button>
                        <Button variant="ghost" className="px-0 text-slate-500" disabled={filial.matriz} onClick={() => router.put('/admin/transportadoras/' + transportadora.id + '/filiais/' + filial.id, { nome: filial.nome, cnpj: filial.cnpj, cidade: filial.cidade, uf: filial.uf, matriz: filial.matriz, ativo: !filial.ativo })} aria-label="Alternar status"><Power size={15} /> {filial.ativo ? 'Desativar' : 'Ativar'}</Button>
                    </div>
                </div>)}
            </div>
        </AdminLayout>
    );
}
