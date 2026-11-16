import { Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { Button } from '../../../components/ui/button';

type Branch = { id: number; nome: string; cnpj: string | null; cidade: string; uf: string; matriz: boolean; ativo: boolean } | null;
type Carrier = { id: number; nome_fantasia: string };

export default function Form({ transportadora, filial }: { transportadora: Carrier; filial: Branch }) {
    const editing = Boolean(filial);
    const form = useForm({
        nome: filial?.nome ?? '',
        cnpj: filial?.cnpj ?? '',
        cidade: filial?.cidade ?? '',
        uf: filial?.uf ?? '',
        matriz: filial?.matriz ?? false,
        ativo: filial?.ativo ?? true,
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        editing
            ? form.put('/admin/transportadoras/' + transportadora.id + '/filiais/' + filial?.id)
            : form.post('/admin/transportadoras/' + transportadora.id + '/filiais');
    }

    return (
        <AdminLayout title={editing ? 'Editar filial' : 'Nova filial'} backHref={'/admin/transportadoras/' + transportadora.id + '/filiais'}>
            <div className="max-w-2xl">
                <h1 className="text-3xl font-bold">{editing ? 'Editar filial' : 'Nova filial'}</h1>
                <p className="mt-2 text-slate-500">Unidade da transportadora {transportadora.nome_fantasia}.</p>
                <form onSubmit={submit} className="mt-8 grid gap-5 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <label className="grid gap-2 text-sm font-semibold">Nome<input className="rounded-xl border-slate-200 bg-transparent px-4 py-3 font-normal dark:border-slate-700" value={form.data.nome} onChange={(e) => form.setData('nome', e.target.value)} /></label>
                        <label className="grid gap-2 text-sm font-semibold">CNPJ<input className="rounded-xl border-slate-200 bg-transparent px-4 py-3 font-normal dark:border-slate-700" value={form.data.cnpj} onChange={(e) => form.setData('cnpj', e.target.value)} /></label>
                        <label className="grid gap-2 text-sm font-semibold">Cidade<input className="rounded-xl border-slate-200 bg-transparent px-4 py-3 font-normal dark:border-slate-700" value={form.data.cidade} onChange={(e) => form.setData('cidade', e.target.value)} /></label>
                        <label className="grid gap-2 text-sm font-semibold">UF<input maxLength={2} className="rounded-xl border-slate-200 bg-transparent px-4 py-3 font-normal uppercase dark:border-slate-700" value={form.data.uf} onChange={(e) => form.setData('uf', e.target.value.toUpperCase())} /></label>
                    </div>
                    <label className="flex items-center gap-3 text-sm font-semibold"><input type="checkbox" checked={form.data.matriz} disabled={Boolean(filial?.matriz)} onChange={(e) => form.setData('matriz', e.target.checked)} /> Filial matriz</label>
                    <label className="flex items-center gap-3 text-sm font-semibold"><input type="checkbox" checked={form.data.ativo} disabled={Boolean(filial?.matriz)} onChange={(e) => form.setData('ativo', e.target.checked)} /> Cadastro ativo</label>
                    <div className="flex gap-3"><Button type="submit" disabled={form.processing}>Salvar</Button><Button type="button" variant="outline" asChild><Link href={'/admin/transportadoras/' + transportadora.id + '/filiais'}>Cancelar</Link></Button></div>
                </form>
            </div>
        </AdminLayout>
    );
}
