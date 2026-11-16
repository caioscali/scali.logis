import { Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { Button } from '../../../components/ui/button';

type Group = { id: number; nome: string; ativo: boolean } | null;
export default function Form({ grupo }: { grupo: Group }) {
    const editing = Boolean(grupo);
    const form = useForm({ nome: grupo?.nome ?? '', ativo: grupo?.ativo ?? true });
    function submit(event: FormEvent) { event.preventDefault(); editing ? form.put('/admin/grupos-empresariais/' + grupo?.id) : form.post('/admin/grupos-empresariais'); }
    return <AdminLayout title={editing ? 'Editar grupo empresarial' : 'Novo grupo empresarial'} backHref="/admin/grupos-empresariais"><div className="max-w-2xl"><h1 className="text-3xl font-bold">{editing ? 'Editar grupo' : 'Novo grupo empresarial'}</h1><p className="mt-2 text-slate-500">Informe os dados principais do grupo econômico.</p><form onSubmit={submit} className="mt-8 grid gap-5 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"><label className="grid gap-2 text-sm font-semibold">Nome<input className="rounded-xl border-slate-200 bg-transparent px-4 py-3 font-normal dark:border-slate-700" value={form.data.nome} onChange={(e) => form.setData('nome', e.target.value)} />{form.errors.nome && <span className="font-normal text-rose-500">{form.errors.nome}</span>}</label><label className="flex items-center gap-3 text-sm font-semibold"><input type="checkbox" checked={form.data.ativo} onChange={(e) => form.setData('ativo', e.target.checked)} /> Grupo ativo</label><div className="flex gap-3"><Button type="submit" disabled={form.processing}>Salvar</Button><Button type="button" variant="outline" asChild><Link href="/admin/grupos-empresariais">Cancelar</Link></Button></div></form></div></AdminLayout>;
}
