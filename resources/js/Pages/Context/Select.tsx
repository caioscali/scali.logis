import { Head, router } from '@inertiajs/react';
import { Building2, Check, MapPin } from 'lucide-react';
import { useState } from 'react';
import { Button } from '../../components/ui/button';

type Option = { transportadora: { id: number; nome: string; grupo: string }; perfil: string; filiais: { id: number; nome: string; cidade: string; uf: string }[] };

export default function Select({ opcoes }: { opcoes: Option[] }) {
    const [selected, setSelected] = useState<{ transportadoraId: number; filialId: number } | null>(null);
    const [processing, setProcessing] = useState(false);

    function select(transportadoraId: number, filialId: number) {
        setSelected({ transportadoraId, filialId });
    }

    function submit() {
        if (!selected) {
            return;
        }

        setProcessing(true);
        router.post('/contexto/selecionar', { transportadora_id: selected.transportadoraId, filial_id: selected.filialId }, { onFinish: () => setProcessing(false) });
    }

    return <><Head title="Selecionar contexto" /><div className="min-h-screen bg-slate-50 px-5 py-10 text-slate-950 dark:bg-slate-950 dark:text-white"><div className="mx-auto max-w-3xl"><div className="mb-10"><p className="text-sm font-semibold uppercase tracking-[0.2em] text-sky-600">Scali Logis</p><h1 className="mt-3 text-3xl font-bold tracking-tight md:text-4xl">Onde você deseja trabalhar?</h1><p className="mt-3 text-slate-500">Selecione uma transportadora e uma filial para acessar a operação autorizada.</p></div><div className="grid gap-4">{opcoes.map((opcao) => <section key={opcao.transportadora.id} className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"><div className="flex items-start gap-3"><div className="rounded-xl bg-sky-100 p-3 text-sky-700 dark:bg-sky-950 dark:text-sky-300"><Building2 size={20} /></div><div><h2 className="font-bold">{opcao.transportadora.nome}</h2><p className="text-sm text-slate-500">{opcao.transportadora.grupo} · Perfil: {opcao.perfil}</p></div></div><div className="mt-5 grid gap-2 sm:grid-cols-2">{opcao.filiais.map((filial) => { const active = selected?.transportadoraId === opcao.transportadora.id && selected.filialId === filial.id; return <button key={filial.id} type="button" onClick={() => select(opcao.transportadora.id, filial.id)} className={active ? 'flex items-center justify-between rounded-xl border-2 border-sky-500 bg-sky-50 p-4 text-left dark:bg-sky-950/40' : 'flex items-center justify-between rounded-xl border border-slate-200 p-4 text-left hover:border-sky-300 dark:border-slate-700'}><span className="flex items-center gap-3"><MapPin size={17} className="text-sky-500" /><span><strong className="block">{filial.nome}</strong><small className="text-slate-500">{filial.cidade} · {filial.uf}</small></span></span>{active && <Check size={18} className="text-sky-600" />}</button>; })}</div></section>)}</div>{opcoes.length === 0 && <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900"><p className="text-slate-500">Nenhum vínculo empresarial ativo com filial autorizada foi encontrado.</p></div>}<div className="mt-8 flex justify-end"><Button disabled={!selected || processing} onClick={submit}>Continuar</Button></div></div></div></>;
}
