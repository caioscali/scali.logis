import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, MapPin } from 'lucide-react';

type ContextOption = { transportadora: { id: number; nome: string; grupo: string }; perfil: string; filiais: { id: number; nome: string; cidade: string; uf: string }[] };
type ContextProps = { contexto?: { atual: { transportadora: { nome: string }; filial: { nome: string }; perfil: string } | null; opcoes: ContextOption[] } };

export function ContextoSelector() {
    const { contexto } = usePage<ContextProps>().props;
    const atual = contexto?.atual;

    if (!contexto || contexto.opcoes.length === 0) {
        return null;
    }

    return <Link href="/contexto/selecionar" className="inline-flex max-w-full items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-left text-sm hover:border-sky-300 dark:border-slate-700 dark:bg-slate-900"><MapPin size={16} className="shrink-0 text-sky-500" /><span className="min-w-0 truncate">{atual ? <><strong>{atual.transportadora.nome}</strong> <span className="text-slate-400">—</span> {atual.filial.nome}</> : 'Selecionar contexto'}</span><ChevronDown size={15} className="shrink-0 text-slate-400" /></Link>;
}
