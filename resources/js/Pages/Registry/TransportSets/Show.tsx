import { Link, useForm } from '@inertiajs/react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { Button } from '../../../components/ui/button';

type Conjunto = { id: number; ativo: boolean; motorista: { nome: string }; transportador: { nome: string }; veiculo: { placa: string; cidade: string }; conta_bancaria: { pessoa: { nome: string } }; tipo_composicao: string; tipo_carroceria: string; implementos: { implemento: { placa: string }; posicao: number }[] };

export default function Show({ conjunto }: { conjunto: Conjunto }) {
    const form = useForm({ ativo: conjunto.ativo });
    function alternar() { form.put('/conjuntos-transporte/' + conjunto.id, { preserveScroll: true }); }
    return <AdminLayout title="Detalhes do conjunto" backHref="/conjuntos-transporte"><div className="max-w-3xl"><div className="flex flex-wrap items-start justify-between gap-4"><div><h1 className="text-3xl font-bold">Conjunto #{conjunto.id}</h1><p className="mt-2 text-slate-500">Configuração preservada para histórico.</p></div><div className="flex gap-2"><Button variant="outline" asChild><Link href={'/conjuntos-transporte/criar?origem=' + conjunto.id}>Duplicar</Link></Button><Button variant="outline" onClick={alternar}>{conjunto.ativo ? 'Desativar' : 'Ativar'}</Button></div></div><div className="mt-8 grid gap-4 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"><Info label="Situação" value={conjunto.ativo ? 'Ativo' : 'Inativo'} /><Info label="Motorista" value={conjunto.motorista.nome} /><Info label="Transportador" value={conjunto.transportador.nome} /><Info label="Veículo" value={conjunto.veiculo.placa + ' · ' + conjunto.veiculo.cidade} /><Info label="Composição" value={conjunto.tipo_composicao} /><Info label="Carroceria" value={conjunto.tipo_carroceria} /><Info label="Conta de pagamento" value={conjunto.conta_bancaria.pessoa.nome} /><div><dt className="text-xs font-semibold uppercase text-slate-400">Implementos</dt><dd className="mt-1">{conjunto.implementos.length ? conjunto.implementos.map((item) => item.posicao + '. ' + item.implemento.placa).join(' · ') : 'Nenhum'}</dd></div></div></div></AdminLayout>;
}

function Info({ label, value }: { label: string; value: string }) { return <div><dt className="text-xs font-semibold uppercase text-slate-400">{label}</dt><dd className="mt-1">{value}</dd></div>; }
