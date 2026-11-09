import calendar
import os
import random
from datetime import date, datetime, timedelta


def add_months(d: date, months: int) -> date:
    month_index = d.year * 12 + (d.month - 1) + months
    year, month0 = divmod(month_index, 12)
    month = month0 + 1
    day = min(d.day, calendar.monthrange(year, month)[1])
    return date(year, month, day)


def first_day(d: date) -> date:
    return d.replace(day=1)


def last_day(d: date) -> date:
    return d.replace(day=calendar.monthrange(d.year, d.month)[1])


def date_range(start: date, end: date):
    days = []
    d = start
    while d <= end:
        days.append(d)
        d += timedelta(days=1)
    return days


def random_date(start: date, end: date) -> date:
    if start >= end:
        return start
    return start + timedelta(days=random.randint(0, (end - start).days))


def chunker(seq, size):
    return (seq[pos:pos + size] for pos in range(0, len(seq), size))


def main():
    today = date.today()
    start_paid = first_day(add_months(today, -3))
    # Paid window: full months -3, -2, -1 (all days < today) plus, within the
    # current month, the days before today. Unpaid window: from today up to the
    # last day of month +3.
    slots = []
    for off in (-3, -2, -1):
        m = add_months(today, off)
        slots.append(('past', date_range(first_day(m), last_day(m))))
    current_first = first_day(today)
    current_last = last_day(today)
    already_elapsed_days = (today - current_first).days
    slots.append(('past', date_range(current_first, today - timedelta(days=1))))
    slots.append(('future', date_range(today, current_last)))
    for off in (1, 2, 3):
        m = add_months(today, off)
        slots.append(('future', date_range(first_day(m), last_day(m))))

    banks = ["Banco do Brasil", "Itaú Unibanco", "Santander"]
    total_paid_by_bank = {b: 0 for b in range(1, len(banks) + 1)}

    sql_statements = []

    sql_statements.append("use sistema_financeiro;")
    sql_statements.append("SET NAMES 'utf8mb4';")

    sql_statements.append("""INSERT INTO usuario(cpf, nome, senha, permissions) VALUES
("00000000000", "Admin", "$2y$12$iO0uR70UbguPjcvtd7vCr.CDfPNZgHKAMho5QX/H/8j6g55JLimJK", 1);""")

    sql_statements.append("""INSERT INTO natureza(nome) VALUES
("CHOCOLATE"),
("SORVETE"),
("PIRULITO"),
("BOMBOM"),
("CHICLETE");""")

    sql_statements.append("""INSERT INTO centro_de_custo(nome) VALUES
("Embalagens"),
("Refrigeração"),
("Veículos"),
("Recursos Humanos"),
("Administração"),
("Logística"),
("Marketing");""")

    # Sub-cost centers (no new cost centers created without categoria_id)
    sub_cc_names_by_parent = {
        1: ["Caixas de Papelão", "Sacos Plásticos", "Fitas Adesivas", "Bandejas", "Etiquetas"],  # Embalagens
        2: ["Manutenção", "Gás Refrigerante", "Peças de Reposição", "Limpeza de Filtros"],       # Refrigeração
        3: ["Fiorino", "Caminhão 1", "Caminhão 2", "Van de Entrega", "Carro de Serviço"],        # Veículos
        4: ["Folha de Pagamento", "Benefícios", "Treinamento", "Recrutamento", "Eventos"],       # Recursos Humanos
        5: ["Material Escritório", "Aluguel", "Energia", "Internet", "Software e Licenças"],     # Administração
        6: ["Combustível", "Pedágio", "Manutenção Frota", "Fretes", "Seguro de Carga"],          # Logística
        7: ["Anúncios", "Material Promocional", "Eventos", "Brindes", "Agência de Marketing"]    # Marketing
    }

    parent_cc_ids = list(range(1, 8))
    sub_cost_centers = []
    sub_cc_ids = []
    current_cc_id = 8
    for parent_id in parent_cc_ids:
        chosen_names = random.sample(sub_cc_names_by_parent[parent_id], random.randint(2, 4))
        for name in chosen_names:
            sub_cost_centers.append(f'("{name}", {parent_id})')
            sub_cc_ids.append(current_cc_id)
            current_cc_id += 1

    sql_statements.append("INSERT INTO centro_de_custo(nome, categoria_id) VALUES\n" + ",\n".join(sub_cost_centers) + ";")

    fornecedores = [
        '("Empresa de Embalagens", "(99) 99999-9999")',
        '("Empresa de Refrigeração", "(99) 99999-1234")',
        '("Empresa de Veículos", "(99) 12345-9999")',
        '("Posto de Combustível", null)'
    ]
    for i in range(1, 6):
        fornecedores.append(f'("Novo Fornecedor {i}", "(99) 88888-000{i}")')

    sql_statements.append("INSERT INTO fornecedor(nome, telefone) VALUES\n" + ",\n".join(fornecedores) + ";")

    sql_statements.append("INSERT INTO banco(nome, saldo_em_centavos) VALUES\n" + ",\n".join(
        [f'("{name}", 0)' for name in banks]) + ";")

    # Contas and parcelas. Natureza IDs 1-5, Fornecedor IDs 1-9.
    contas = []
    parcelas = []
    conta_base_words = ["Compra de", "Pagamento de", "Manutenção de", "Serviço de", "Fatura de", "Renovação de", "Conserto de", "Fornecimento de"]
    conta_subjects = ["Equipamentos", "Refrigeração", "Ferramentas", "Licenças", "Materiais", "Consultoria", "Suprimentos", "Peças", "Serviços Gerais"]

    conta_id = 1
    for _ in range(300):
        word = random.choice(conta_base_words)
        subject = random.choice(conta_subjects)
        historico = f"{word} {subject} {random.randint(0, 10)}"

        valor_total_centavos = random.randint(1000, 500000)
        cc_id = random.choice(sub_cc_ids)
        nat_id = random.randint(1, 5)
        forn_id = random.randint(1, 9)

        zone, days = random.choice(slots)
        # Current-month past window can be empty (e.g. today is the 1st).
        while len(days) == 0:
            zone, days = random.choice(slots)

        is_paid = zone == 'past'
        num_parcelas = max(1, min(random.randint(1, 6), len(days)))
        chosen_days = sorted(random.sample(days, num_parcelas))
        valor_parcela = valor_total_centavos // num_parcelas
        resto = valor_total_centavos % num_parcelas

        for i in range(1, num_parcelas + 1):
            val = valor_parcela + (resto if i == 1 else 0)
            vencimento = chosen_days[i - 1]

            if is_paid:
                banco_id = random.randint(1, len(banks))
                pagamento = min(vencimento + timedelta(days=random.randint(0, 10)), today)
                atraso = (pagamento - vencimento).days
                if atraso >= 5 and random.random() < 0.30:
                    juros = random.randint(0, int(val * 0.02))
                else:
                    juros = 0
                total_paid_by_bank[banco_id] += val + juros
                parcelas.append(
                    f'({i}, {val}, {juros}, "{vencimento.strftime("%Y-%m-%d")}", "{pagamento.strftime("%Y-%m-%d")}", {banco_id}, {conta_id}, 1)'
                )
            else:
                parcelas.append(
                    f'({i}, {val}, 0, "{vencimento.strftime("%Y-%m-%d")}", NULL, NULL, {conta_id}, 0)'
                )

        contas.append(f'("{historico}", {valor_total_centavos}, {cc_id}, {nat_id}, {forn_id}, {1 if is_paid else 0})')
        conta_id += 1

    sql_statements.append(
        "INSERT INTO conta(historico, valor_em_centavos, centro_de_custo_id, natureza_id, fornecedor_id, paid) VALUES\n" + ",\n".join(contas) + ";"
    )

    for chunk in chunker(parcelas, 1000):
        sql_statements.append(
            "INSERT INTO parcela(numero_parcela, valor_em_centavos, juros_em_centavos, data_vencimento, data_pagamento, banco_id, conta_id, paid) VALUES\n" + ",\n".join(chunk) + ";"
        )

    # Livro caixa: as parcelas já pagas acima precisam de uma linha de pagamento
    # para que o saldo dos bancos e os relatórios de caixa fechem.
    sql_statements.append(
        "INSERT INTO parcela_pagamento(parcela_id, banco_id, valor_em_centavos, juros_em_centavos, data_pagamento, usuario_id)\n"
        "SELECT id, banco_id, valor_em_centavos, juros_em_centavos, data_pagamento, 1\n"
        "FROM parcela\n"
        "WHERE paid = 1\n"
        "  AND banco_id IS NOT NULL\n"
        "  AND data_pagamento IS NOT NULL;"
    )

    sql_statements.append(
        "UPDATE parcela\n"
        "SET valor_pago_em_centavos = valor_em_centavos + juros_em_centavos\n"
        "WHERE paid = 1;"
    )

    # Mesmo critério de Conta::syncValorFromParcelas(): conta paga inclui juros.
    sql_statements.append(
        "UPDATE conta\n"
        "SET valor_em_centavos = (\n"
        "    SELECT COALESCE(SUM(p.valor_em_centavos + p.juros_em_centavos), 0)\n"
        "    FROM parcela p\n"
        "    WHERE p.conta_id = conta.id\n"
        ")\n"
        "WHERE paid = 1;"
    )

    # Deposits: a "Saldo de abertura" per bank sized to cover payments plus a
    # few random deposits, keeping each bank balance consistent with its
    # movements (saldo = abertura + depositos - pagamentos).
    deposito_historico_pool = [
        "Venda à vista",
        "Recebimento de cliente",
        "Pagamento de fatura",
        "Transferência recebida",
        "Venda online"
    ]
    depositos = []
    for banco_id in range(1, len(banks) + 1):
        saidas = total_paid_by_bank[banco_id]
        abertura = int(saidas * 1.5) + random.randint(0, 500000)
        if abertura < 100000:
            abertura = 100000
        depositos.append(f'(1, {banco_id}, {abertura}, "Saldo de abertura", "{start_paid.strftime("%Y-%m-%d")} 09:00:00")')

        outros = 0
        for _ in range(random.randint(1, 3)):
            valor = random.randint(20000, 300000)
            dia = random_date(start_paid, today)
            historico = random.choice(deposito_historico_pool)
            registrado = dia if dia <= today else today
            timestamp = registrado.strftime("%Y-%m-%d") + f" {random.randint(7, 21):02d}:{random.randint(0, 59):02d}:00"
            depositos.append(f'(1, {banco_id}, {valor}, "{historico}", "{timestamp}")')
            outros += valor

        sql_statements.append(
            f'UPDATE banco SET saldo_em_centavos = {abertura + outros - saidas} WHERE id = {banco_id};'
        )

    for chunk in chunker(depositos, 1000):
        sql_statements.append(
            "INSERT INTO deposito(usuario_id, banco_id, valor_em_centavos, historico, data_deposito) VALUES\n" + ",\n".join(chunk) + ";"
        )

    script_dir = os.path.dirname(os.path.abspath(__file__))
    output_path = os.path.join(script_dir, "seed_generated.sql")

    with open(output_path, "w", encoding="utf-8") as f:
        f.write("\n\n".join(sql_statements))

    print(f"Seeds generated successfully at: {output_path}")
    print(f"Reference date: {today.strftime('%d/%m/%Y')} (paid window starts {start_paid.strftime('%d/%m/%Y')}, unpaid window ends {last_day(add_months(today, 3)).strftime('%d/%m/%Y')})")
    print(f"Paid parcelas per bank: {total_paid_by_bank}")


if __name__ == "__main__":
    main()