import { Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { Button } from '../../../components/ui/button';

type Equipment = { id: number; placa: string; cidade?: string; ativo: boolean } | null;
type SearchResult = { existe: boolean; equipamento: { placa: string; cidade: string | null; ja_vinculado: boolean } | null };

export default function Form({ tipo, titulo, equipamento }: { tipo: string; titulo: string; equipamento?: { id: number; placa: string; cidade?: string; ativo: boolean } | null }) {
    const editing = equipamento !== null && equipamento !== undefined;
    const base = tipo === 'veiculo' ? '/veiculos' : '/implementos';
    const [pesquisa, setPesquisa] = useState<SearchResult | null>(null);
    const form = useForm({ placa: equipamento?.placa ?? '', cidade: equipamento?.cidade ?? '', ativo: equipamento?.ativo ?? true });

    async function pesquisar() {
        const response = await fetch(base + '/pesquisar?placa=' + encodeURIComponent(form.data.placa), { headers: { Accept: 'application/json' } });
        if (response.ok) setPesquisa(await response.json());
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        editing ? form.put(base + '/vinculos/' + equipamento?.id) : form.post(base);
    }

    return <AdminLayout title={editing ? 'Editar ' + titulo.toLowerCase() : 'Novo ' + titulo.toLowerCase()} backHref={base}><div className="max-w-2xl"><h1 className="text-3xl font-bold">{editing ? 'Editar vínculo' : 'Novo ' + titulo.toLowerCase()}</h1><p className="mt-2 text-slate-500">O equipamento é global; a situação é exclusiva da transportadora ativa.</p><form onSubmit={submit} className="mt-8 grid gap-5 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"><label className="grid gap-2 text-sm font-semibold">Placa<div className="flex gap-2"><input disabled={editing} className="min-w-0 flex-1 rounded-xl border-slate-200 bg-transparent px-4 py-3 font-normal uppercase disabled:opacity-60 dark:border-slate-700" value={form.data.placa} onChange={(e) => form.setData('placa', e.target.value.toUpperCase())} />{!editing && <Button type="button" variant="outline" onClick={pesquisar}>Pesquisar</Button>}</div>{form.errors.placa && <span className="font-normal text-rose-500">{form.errors.placa}</span>}</label>{tipo === 'veiculo' && <label className="grid gap-2 text-sm font-semibold">Cidade<input disabled={editing} className="rounded-xl border-slate-200 bg-transparent px-4 py-3 font-normal disabled:opacity-60 dark:border-slate-700" value={form.data.cidade} onChange={(e) => form.setData('cidade', e.target.value)} />{form.errors.cidade && <span className="font-normal text-rose-500">{form.errors.cidade}</span>}</label>}{pesquisa?.existe && <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">A placa {pesquisa.equipamento?.placa} já existe no cadastro global. {pesquisa.equipamento?.ja_vinculado ? 'Ela já está vinculada à transportadora ativa.' : 'Ao salvar, será criada somente a adoção local, sem alterar o cadastro compartilhado.'}</div>}{editing && <label className="flex items-center gap-3 text-sm font-semibold"><input type="checkbox" checked={form.data.ativo} onChange={(e) => form.setData('ativo', e.target.checked)} /> Vínculo ativo</label>}<div className="flex gap-3"><Button type="submit" disabled={form.processing}>Salvar</Button><Button type="button" variant="outline" asChild><Link href={base}>Cancelar</Link></Button></div></form></div></AdminLayout>;
}
