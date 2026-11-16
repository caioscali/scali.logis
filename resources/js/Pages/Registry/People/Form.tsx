import { Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { Button } from '../../../components/ui/button';

type Option = { value: string; nome: string };
type Person = { vinculo_id: number; id: number; tipo: string; nome: string; documento: string; telefone: string | null; email: string | null; cidade: string | null; uf: string | null; ativo: boolean; funcoes: string[] } | null;

export default function Form({ pessoa, tipos, funcoes }: { pessoa: Person; tipos: Option[]; funcoes: Option[] }) {
    const editing = pessoa !== null;
    const [pesquisa, setPesquisa] = useState<{ existe: boolean; pessoa: { nome: string | null; documento: string; ja_vinculada: boolean } | null } | null>(null);
    const form = useForm({ tipo: pessoa?.tipo ?? 'fisica', nome: pessoa?.nome ?? '', documento: pessoa?.documento ?? '', telefone: pessoa?.telefone ?? '', email: pessoa?.email ?? '', cidade: pessoa?.cidade ?? '', uf: pessoa?.uf ?? '', ativo: pessoa?.ativo ?? true, funcoes: pessoa?.funcoes ?? [] as string[] });
    const selectedType = tipos.find((tipo) => tipo.value === form.data.tipo)?.nome ?? 'Pessoa física';

    async function pesquisar() {
        const params = new URLSearchParams({ tipo: form.data.tipo, documento: form.data.documento });
        const response = await fetch('/pessoas/pesquisar?' + params.toString(), { headers: { Accept: 'application/json' } });
        if (response.ok) setPesquisa(await response.json());
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        if (editing) form.put('/pessoas/vinculos/' + pessoa?.vinculo_id);
        else form.post('/pessoas');
    }

    function toggleFuncao(funcao: string) {
        form.setData('funcoes', form.data.funcoes.includes(funcao) ? form.data.funcoes.filter((item) => item !== funcao) : [...form.data.funcoes, funcao]);
    }

    return <AdminLayout title={editing ? 'Editar pessoa' : 'Nova pessoa'} backHref="/pessoas"><div className="max-w-3xl"><h1 className="text-3xl font-bold">{editing ? 'Editar vínculo da pessoa' : 'Nova pessoa'}</h1><p className="mt-2 text-slate-500">O cadastro global é reutilizado entre transportadoras; funções e situação são locais.</p><form onSubmit={submit} className="mt-8 grid gap-5 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <div className="grid gap-5 sm:grid-cols-2"><label className="grid gap-2 text-sm font-semibold">Tipo de pessoa<select disabled={editing} className="rounded-xl border-slate-200 bg-transparent px-4 py-3 font-normal disabled:opacity-60 dark:border-slate-700" value={form.data.tipo} onChange={(e) => form.setData('tipo', e.target.value)}>{tipos.map((tipo) => <option key={tipo.value} value={tipo.value}>{tipo.nome}</option>)}</select></label><label className="grid gap-2 text-sm font-semibold">{selectedType} · documento<div className="flex gap-2"><input disabled={editing} className="min-w-0 flex-1 rounded-xl border-slate-200 bg-transparent px-4 py-3 font-normal disabled:opacity-60 dark:border-slate-700" value={form.data.documento} onChange={(e) => form.setData('documento', e.target.value)} />{!editing && <Button type="button" variant="outline" onClick={pesquisar}>Pesquisar</Button>}</div>{form.errors.documento && <span className="font-normal text-rose-500">{form.errors.documento}</span>}</label></div>
        {pesquisa?.existe && <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">Já existe um cadastro global com o documento {pesquisa.pessoa?.documento}. {pesquisa.pessoa?.ja_vinculada ? 'Esta pessoa já está vinculada a esta transportadora.' : 'Ao salvar, a transportadora adotará este cadastro sem alterar seus dados compartilhados.'}</div>}
        <fieldset disabled={editing} className="grid gap-5 disabled:opacity-70"><legend className="mb-1 text-sm font-semibold">Dados compartilhados {editing && <span className="font-normal text-slate-500">(alterações dependem de confirmação futura)</span>}</legend><label className="grid gap-2 text-sm font-semibold">Nome<input className="rounded-xl border-slate-200 bg-transparent px-4 py-3 font-normal dark:border-slate-700" value={form.data.nome} onChange={(e) => form.setData('nome', e.target.value)} />{form.errors.nome && <span className="font-normal text-rose-500">{form.errors.nome}</span>}</label><div className="grid gap-5 sm:grid-cols-2"><label className="grid gap-2 text-sm font-semibold">Telefone<input className="rounded-xl border-slate-200 bg-transparent px-4 py-3 font-normal dark:border-slate-700" value={form.data.telefone} onChange={(e) => form.setData('telefone', e.target.value)} /></label><label className="grid gap-2 text-sm font-semibold">E-mail<input type="email" className="rounded-xl border-slate-200 bg-transparent px-4 py-3 font-normal dark:border-slate-700" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} /></label><label className="grid gap-2 text-sm font-semibold">Cidade<input className="rounded-xl border-slate-200 bg-transparent px-4 py-3 font-normal dark:border-slate-700" value={form.data.cidade} onChange={(e) => form.setData('cidade', e.target.value)} /></label><label className="grid gap-2 text-sm font-semibold">UF<input maxLength={2} className="rounded-xl border-slate-200 bg-transparent px-4 py-3 font-normal uppercase dark:border-slate-700" value={form.data.uf} onChange={(e) => form.setData('uf', e.target.value.toUpperCase())} /></label></div></fieldset>
        <div><p className="text-sm font-semibold">Funções na transportadora</p><div className="mt-3 grid gap-2 sm:grid-cols-2">{funcoes.map((funcao) => <label key={funcao.value} className="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-700"><input type="checkbox" checked={form.data.funcoes.includes(funcao.value)} onChange={() => toggleFuncao(funcao.value)} /> {funcao.nome}</label>)}</div>{form.errors.funcoes && <p className="mt-2 text-sm text-rose-500">{form.errors.funcoes}</p>}</div>
        {editing && <label className="flex items-center gap-3 text-sm font-semibold"><input type="checkbox" checked={form.data.ativo} onChange={(e) => form.setData('ativo', e.target.checked)} /> Vínculo ativo</label>}
        <div className="flex gap-3"><Button type="submit" disabled={form.processing}>Salvar</Button><Button type="button" variant="outline" asChild><Link href="/pessoas">Cancelar</Link></Button></div>
    </form></div></AdminLayout>;
}
