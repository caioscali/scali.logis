import { Link } from '@inertiajs/react';
import { Edit3 } from 'lucide-react';
import AdminLayout from '../../../Layouts/AdminLayout';
import { Button } from '../../../components/ui/button';

type LinkItem = { id: number; perfil: string; ativo: boolean; usuario: { name: string; email: string }; usuario_filiais: { filial: { nome: string; cidade: string; uf: string } }[] };
type Carrier = { id: number; nome_fantasia: string };

export default function Show({ transportadora, vinculo }: { transportadora: Carrier; vinculo: LinkItem }) {
    return <AdminLayout title="Usuários" backHref="/dashboard"><div className="flex items-end justify-between gap-4"><div><p className="text-sm font-semibold uppercase tracking-widest text-sky-600">{transportadora.nome_fantasia}</p><h1 className="mt-2 text-3xl font-bold">{vinculo.usuario.name}</h1><p className="mt-2 text-slate-500">{vinculo.usuario.email}</p></div><Button variant="outline" asChild><Link href={'/admin/transportadoras/' + transportadora.id + '/usuarios/' + vinculo.id + '/edit'}><Edit3 size={16} /> Editar vínculo</Link></Button></div><div className="mt-8 grid gap-4 sm:grid-cols-2"><div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"><p className="text-sm text-slate-500">Perfil</p><p className="mt-2 text-xl font-bold">{vinculo.perfil}</p><p className="mt-2 text-sm text-slate-500">{vinculo.ativo ? 'Vínculo ativo' : 'Vínculo inativo'}</p></div><div className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"><p className="text-sm text-slate-500">Filiais autorizadas</p><div className="mt-3 grid gap-2">{vinculo.usuario_filiais.map((item) => <p key={item.filial.nome} className="font-semibold">{item.filial.nome}<span className="ml-2 text-sm font-normal text-slate-500">{item.filial.cidade} · {item.filial.uf}</span></p>)}</div></div></div></AdminLayout>;
}
