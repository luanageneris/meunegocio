<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

const lastUpdated = '19/01/2026';
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-amber-50 via-white to-orange-100 text-gray-900">
        <Head title="Política de Privacidade - MEU NEGÓCIO" />

        <header class="max-w-6xl mx-auto px-6 pt-10 pb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-orange-600">Privacidade e proteção de dados</p>
                <h1 class="text-3xl sm:text-4xl font-black leading-tight text-gray-900">Política de Privacidade</h1>
                <p class="text-gray-700 mt-2">Como tratamos dados pessoais no Meu Negócio, com isolamento por empresa e segurança desde o design.</p>
            </div>
            <div class="flex items-center gap-3 self-start sm:self-auto">
                <Link href="/" class="px-4 py-2 rounded-full border border-orange-200 text-orange-700 font-semibold hover:border-orange-400 hover:text-orange-800 transition">
                    Voltar
                </Link>
                <Link href="/login" class="px-4 py-2 rounded-full bg-orange-500 text-white font-semibold shadow-md hover:bg-orange-600 transition">
                    Acessar conta
                </Link>
            </div>
        </header>

        <main class="max-w-6xl mx-auto px-6 pb-16 space-y-10">
            <section class="bg-white border border-orange-100 rounded-2xl shadow-sm p-6 sm:p-8">
                <div class="grid md:grid-cols-3 gap-6">
                    <div class="p-4 rounded-xl bg-orange-50 border border-orange-100">
                        <p class="text-sm text-orange-700 font-semibold">Controladora</p>
                        <p class="text-lg font-bold text-gray-900">Meu Negócio (plataforma SaaS)</p>
                        <p class="text-sm text-gray-700 mt-1">Controla o tratamento de dados dos usuários e empresas que utilizam o serviço.</p>
                    </div>
                    <div class="p-4 rounded-xl bg-orange-50 border border-orange-100">
                        <p class="text-sm text-orange-700 font-semibold">Compromisso</p>
                        <p class="text-lg font-bold text-gray-900">Respeito à legislação aplicável</p>
                        <p class="text-sm text-gray-700 mt-1">Aplicamos princípios de privacidade por padrão e por design, com transparência e controle do titular.</p>
                    </div>
                    <div class="p-4 rounded-xl bg-orange-50 border border-orange-100">
                        <p class="text-sm text-orange-700 font-semibold">Última atualização</p>
                        <p class="text-lg font-bold text-gray-900">{{ lastUpdated }}</p>
                        <p class="text-sm text-gray-700 mt-1">Revisamos sempre que houver mudanças relevantes no produto ou na lei.</p>
                    </div>
                </div>
            </section>

            <section class="bg-white border border-orange-100 rounded-2xl shadow-sm p-6 sm:p-8 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-full bg-orange-100 text-orange-700 flex items-center justify-center font-bold">1</div>
                    <div>
                        <h2 class="text-2xl font-black text-gray-900">Quais dados coletamos</h2>
                        <p class="text-gray-700">Coletamos somente o necessário para operar o serviço e cumprir obrigações legais.</p>
                    </div>
                </div>
                <div class="grid md:grid-cols-2 gap-4">
                    <div class="p-4 rounded-xl border border-orange-100 bg-orange-50">
                        <p class="text-sm font-semibold text-orange-700">Dados de conta</p>
                        <p class="text-gray-800">Nome, e-mail, autenticação (incluindo login Google), papel (root/colaborador) e preferências.</p>
                    </div>
                    <div class="p-4 rounded-xl border border-orange-100 bg-orange-50">
                        <p class="text-sm font-semibold text-orange-700">Dados da empresa</p>
                        <p class="text-gray-800">Informações cadastrais da empresa e metadados de configuração.</p>
                    </div>
                    <div class="p-4 rounded-xl border border-orange-100 bg-orange-50">
                        <p class="text-sm font-semibold text-orange-700">Registros operacionais</p>
                        <p class="text-gray-800">Entradas, saídas, comissões, escalas, fechamentos diários e arquivos importados (Excel).</p>
                    </div>
                    <div class="p-4 rounded-xl border border-orange-100 bg-orange-50">
                        <p class="text-sm font-semibold text-orange-700">Logs e segurança</p>
                        <p class="text-gray-800">Logs de acesso, dispositivo, endereço IP e eventos relevantes para auditoria e fraude.</p>
                    </div>
                </div>
            </section>

            <section class="bg-white border border-orange-100 rounded-2xl shadow-sm p-6 sm:p-8 space-y-6">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-full bg-orange-100 text-orange-700 flex items-center justify-center font-bold">2</div>
                    <div>
                        <h2 class="text-2xl font-black text-gray-900">Como usamos os dados</h2>
                        <p class="text-gray-700">Tratamos dados pessoais apenas para operar a plataforma, prestar suporte e cumprir obrigações legais.</p>
                    </div>
                </div>
                <div class="grid md:grid-cols-2 gap-4">
                    <div class="p-4 rounded-xl border border-orange-100">
                        <p class="text-sm font-semibold text-orange-700">Execução do contrato</p>
                        <p class="text-gray-800">Criar contas, autenticar usuários, operar dashboards, calcular comissões e manter registros financeiros.</p>
                    </div>
                    <div class="p-4 rounded-xl border border-orange-100">
                        <p class="text-sm font-semibold text-orange-700">Segurança e prevenção a fraudes</p>
                        <p class="text-gray-800">Monitorar acesso, detectar uso indevido, aplicar isolamento por empresa e responder a incidentes.</p>
                    </div>
                    <div class="p-4 rounded-xl border border-orange-100">
                        <p class="text-sm font-semibold text-orange-700">Suporte</p>
                        <p class="text-gray-800">Responder solicitações em /suporte e manter histórico de atendimento.</p>
                    </div>
                    <div class="p-4 rounded-xl border border-orange-100">
                        <p class="text-sm font-semibold text-orange-700">Obrigação legal</p>
                        <p class="text-gray-800">Cumprir requisitos legais e regulatórios aplicáveis, com retenção mínima necessária.</p>
                    </div>
                </div>
            </section>

            <section class="bg-white border border-orange-100 rounded-2xl shadow-sm p-6 sm:p-8 space-y-6">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-full bg-orange-100 text-orange-700 flex items-center justify-center font-bold">3</div>
                    <div>
                        <h2 class="text-2xl font-black text-gray-900">Bases legais</h2>
                        <p class="text-gray-700">Fundamentamos cada tratamento nos princípios de necessidade, transparência e segurança.</p>
                    </div>
                </div>
                <div class="grid md:grid-cols-3 gap-4">
                    <div class="p-4 rounded-xl border border-orange-100 bg-orange-50">
                        <p class="text-sm font-semibold text-orange-700">Execução do contrato</p>
                        <p class="text-gray-800">Operar a conta, autenticar, processar registros financeiros e entregar funcionalidades contratadas.</p>
                    </div>
                    <div class="p-4 rounded-xl border border-orange-100 bg-orange-50">
                        <p class="text-sm font-semibold text-orange-700">Legítimo interesse</p>
                        <p class="text-gray-800">Prevenir fraude, manter segurança, melhorar o serviço e equilibrar direitos do titular com salvaguardas.</p>
                    </div>
                    <div class="p-4 rounded-xl border border-orange-100 bg-orange-50">
                        <p class="text-sm font-semibold text-orange-700">Consentimento</p>
                        <p class="text-gray-800">Para funcionalidades opcionais (ex.: analytics não essenciais). Você pode revogar a qualquer momento.</p>
                    </div>
                </div>
            </section>

            <section class="bg-white border border-orange-100 rounded-2xl shadow-sm p-6 sm:p-8 space-y-6">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-full bg-orange-100 text-orange-700 flex items-center justify-center font-bold">4</div>
                    <div>
                        <h2 class="text-2xl font-black text-gray-900">Direitos do titular</h2>
                        <p class="text-gray-700">Você pode exercer seus direitos a qualquer momento pelos canais indicados.</p>
                    </div>
                </div>
                <div class="grid md:grid-cols-2 gap-4">
                    <div class="p-4 rounded-xl border border-orange-100">
                        <p class="text-sm font-semibold text-orange-700">Transparência</p>
                        <ul class="list-disc list-inside text-gray-800 space-y-2 mt-2">
                            <li>Confirmação e acesso aos dados que tratamos sobre você.</li>
                            <li>Informação clara sobre finalidades e compartilhamentos.</li>
                        </ul>
                    </div>
                    <div class="p-4 rounded-xl border border-orange-100">
                        <p class="text-sm font-semibold text-orange-700">Controle</p>
                        <ul class="list-disc list-inside text-gray-800 space-y-2 mt-2">
                            <li>Correção ou atualização de dados.</li>
                            <li>Eliminação ou anonimização quando cabível.</li>
                            <li>Portabilidade quando tecnicamente viável.</li>
                            <li>Revogação de consentimento e oposição a tratamentos baseados em interesse legítimo.</li>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="bg-white border border-orange-100 rounded-2xl shadow-sm p-6 sm:p-8 space-y-6">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-full bg-orange-100 text-orange-700 flex items-center justify-center font-bold">5</div>
                    <div>
                        <h2 class="text-2xl font-black text-gray-900">Compartilhamento e transferências</h2>
                        <p class="text-gray-700">Não vendemos nem compartilhamos dados pessoais com terceiros para fins comerciais. Se um dia precisarmos compartilhar, faremos apenas o mínimo necessário, com base legal, contrato adequado e, quando exigido, consentimento prévio.</p>
                    </div>
                </div>
                <div class="grid md:grid-cols-3 gap-4">
                    <div class="p-4 rounded-xl border border-orange-100 bg-orange-50">
                        <p class="text-sm font-semibold text-orange-700">Operação segura</p>
                        <p class="text-gray-800">Quando usamos fornecedores (ex.: hospedagem, e-mail transacional), exigimos cláusulas de proteção de dados, minimização e controles de segurança.</p>
                    </div>
                    <div class="p-4 rounded-xl border border-orange-100 bg-orange-50">
                        <p class="text-sm font-semibold text-orange-700">Consentimento quando necessário</p>
                        <p class="text-gray-800">Qualquer compartilhamento opcional (ex.: analytics não essencial) só ocorre com aviso claro e possibilidade de recusa.</p>
                    </div>
                    <div class="p-4 rounded-xl border border-orange-100 bg-orange-50">
                        <p class="text-sm font-semibold text-orange-700">Transferências</p>
                        <p class="text-gray-800">Caso haja transferência de dados para outra jurisdição, aplicamos salvaguardas contratuais e técnicas adequadas.</p>
                    </div>
                </div>
            </section>

            <section class="bg-white border border-orange-100 rounded-2xl shadow-sm p-6 sm:p-8 space-y-6">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-full bg-orange-100 text-orange-700 flex items-center justify-center font-bold">6</div>
                    <div>
                        <h2 class="text-2xl font-black text-gray-900">Retenção, segurança e multi-tenant</h2>
                        <p class="text-gray-700">Protegemos dados por design e os mantemos apenas pelo tempo necessário.</p>
                    </div>
                </div>
                <div class="grid md:grid-cols-3 gap-4">
                    <div class="p-4 rounded-xl border border-orange-100">
                        <p class="text-sm font-semibold text-orange-700">Isolamento por empresa</p>
                        <p class="text-gray-800">Cada registro possui <span class="font-semibold">company_id</span> e middleware de isolamento garante que usuários vejam somente dados da sua empresa.</p>
                    </div>
                    <div class="p-4 rounded-xl border border-orange-100">
                        <p class="text-sm font-semibold text-orange-700">Segurança</p>
                        <p class="text-gray-800">Criptografia em trânsito (HTTPS), controle de acesso por perfil root/colaborador e logs de auditoria.</p>
                    </div>
                    <div class="p-4 rounded-xl border border-orange-100">
                        <p class="text-sm font-semibold text-orange-700">Retenção</p>
                        <p class="text-gray-800">Mantemos dados enquanto a conta estiver ativa e conforme exigências legais. Após encerramento, dados são eliminados ou anonimizados quando permitido.</p>
                    </div>
                </div>
            </section>

            <section class="bg-white border border-orange-100 rounded-2xl shadow-sm p-6 sm:p-8 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-full bg-orange-100 text-orange-700 flex items-center justify-center font-bold">7</div>
                    <div>
                        <h2 class="text-2xl font-black text-gray-900">Cookies e rastreamento</h2>
                        <p class="text-gray-700">Usamos cookies estritamente necessários para autenticação e segurança. Cookies opcionais (ex.: analytics) só são usados com aviso prévio e, quando exigido, consentimento.</p>
                    </div>
                </div>
            </section>

            <section class="bg-white border border-orange-100 rounded-2xl shadow-sm p-6 sm:p-8 space-y-4">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-full bg-orange-100 text-orange-700 flex items-center justify-center font-bold">8</div>
                    <div>
                        <h2 class="text-2xl font-black text-gray-900">Como exercer seus direitos</h2>
                        <p class="text-gray-700">Envie sua solicitação pelo canal de suporte. Responderemos dentro dos prazos legais aplicáveis e com transparência.</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a
                        href="mailto:privacidade@meunegocio.app?subject=Solicitacao%20de%20privacidade&body=Por%20favor,%20informe:%0D%0A-%20Email%20de%20contato:%0D%0A-%20Empresa%20(ou%20CNPJ/NIF):%0D%0A-%20Direito%20que%20deseja%20exercer:%0D%0A-%20Detalhes%20da%20solicitacao:%0D%0A"
                        class="px-5 py-3 rounded-xl bg-orange-500 text-white font-semibold shadow hover:bg-orange-600 transition"
                    >
                        Abrir solicitação de privacidade
                    </a>
                    <p class="text-sm text-gray-700">O e-mail será enviado a partir da sua conta de correio, garantindo que saibamos quem é o titular.</p>
                </div>
            </section>
        </main>
    </div>
</template>
