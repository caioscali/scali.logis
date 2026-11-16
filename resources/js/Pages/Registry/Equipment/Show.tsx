import { Link } from '@inertiajs/react';
import { Edit3 } from 'lucide-react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { Button } from '../../../components/ui/button';

type EquipmentLink = { id: number; ativo: boolean; veiculo?: { id: number; placa: string; cidade: string }; implemento?: { id: number; placa: string } };

export default function Show({ tipo, titulo, equipamento }: { tipo: string; titulo: string; equipamento: EquipmentLink }) {
    const base = tipo === 'veiculo' ? '/veiculos' : '/implementos';
    const item = equipamento.veiculo ?? equipamento.implemento;
    return <AdminLayout title={titulo} backHref={base}><div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-sm font-semibold uppercase tracking-widest text-sky-600">Cadastro global</p><h1 className="mt-2 text-3xl font-bold">{item?.placa}</h1><p className="mt-2 text-slate-500">{tipo === 'veiculo' && equipamento.veiculo?.cidade}</p></div><Button variant="outline" asChild><Link href={base + '/' + item?.id + '/editar'}><Edit3 size={16} /> Editar vínculo</Link></Button></div><div className="mt-8 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"><p className="text-sm text-slate-500">Situação na transportadora ativa</p><p className="mt-2 text-xl font-bold">{equipamento.ativo ? 'Ativo' : 'Inativo'}</p></div></AdminLayout>;
}
