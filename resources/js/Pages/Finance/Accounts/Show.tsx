import { Link } from '@inertiajs/react';
import { Copy, Edit3 } from 'lucide-react';
import { useState } from 'react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { Button } from '../../../components/ui/button';

type Account = { id: number; titular: string; banco: string | null; identificacao: string; ativo: boolean; dados_completos: Record<string, string | null> | null };

export default function Show({ conta }: { conta: Account }) {
    const [copiado, setCopiado] = useState('');
    async function copiar(campo: string, valor: string | null) { if (!valor) return; await navigator.clipboard.writeText(valor); setCopiado(campo); window.setTimeout(() => setCopiado(''), 2000); }
    return <AdminLayout title="Conta bancária" backHref="/contas-bancarias"><div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-sm font-semibold uppercase tracking-widest text-sky-600">Conta adotada</p><h1 className="mt-2 text-3xl font-bold">{conta.titular}</h1><p className="mt-2 text-slate-500">{conta.banco || 'PIX'} · {conta.identificacao}</p></div><Button variant="outline" asChild><Link href={'/contas-bancarias/' + conta.id + '/editar'}><Edit3 size={16} /> Editar vínculo</Link></Button></div><div className="mt-8 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"><p className="text-sm text-slate-500">Situação na transportadora ativa</p><p className="mt-2 text-xl font-bold">{conta.ativo ? 'Ativa' : 'Inativa'}</p>{conta.dados_completos ? <><div className="mt-6 grid gap-4 sm:grid-cols-2">{Object.entries(conta.dados_completos).map(([campo, valor]) => <div key={campo} className="rounded-xl bg-slate-50 p-4 dark:bg-slate-950"><p className="text-xs font-semibold uppercase tracking-wide text-slate-500">{campo.replaceAll('_', ' ')}</p><div className="mt-2 flex items-center justify-between gap-2"><span className="font-semibold">{valor || 'Não informado'}</span>{valor && <Button variant="ghost" className="px-2" onClick={() => copiar(campo, valor)} aria-label={'Copiar ' + campo}><Copy size={15} /></Button>}</div>{copiado === campo && <p className="mt-1 text-xs text-emerald-600">Copiado. O valor está agora sob responsabilidade de quem o recebeu.</p>}</div>)}</div></> : <p className="mt-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-600 dark:bg-slate-950 dark:text-slate-300">Os dados completos estão restritos a usuários autorizados. Esta tela exibe apenas identificação mascarada.</p>}</div></AdminLayout>;
}
