-- Pagamento parcial de parcelas.
--
-- Modelo:
--   parcela.valor_pago_em_centavos = SUM(parcela_pagamento.valor_em_centavos + juros_em_centavos)
--   parcela.juros_em_centavos      = SUM(parcela_pagamento.juros_em_centavos)
--   restante                       = valor_em_centavos + juros_em_centavos - valor_pago_em_centavos

USE sistema_financeiro;

ALTER TABLE parcela
    ADD COLUMN valor_pago_em_centavos INT UNSIGNED NOT NULL DEFAULT 0 AFTER juros_em_centavos;

CREATE TABLE parcela_pagamento(
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parcela_id INT UNSIGNED NOT NULL,
    banco_id SMALLINT UNSIGNED NOT NULL,
    valor_em_centavos INT UNSIGNED NOT NULL,
    juros_em_centavos INT UNSIGNED NOT NULL DEFAULT 0,
    data_pagamento DATE NOT NULL,
    usuario_id SMALLINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT NOW(),
    INDEX idx_pp_parcela (parcela_id),
    INDEX idx_pp_data (data_pagamento),
    INDEX idx_pp_banco_data (banco_id, data_pagamento),
    FOREIGN KEY (parcela_id) REFERENCES parcela(id),
    FOREIGN KEY (banco_id) REFERENCES banco(id),
    FOREIGN KEY (usuario_id) REFERENCES usuario(id)
) ENGINE=INNODB;

-- Backfill: cada parcela já paga vira um único pagamento integral.
UPDATE parcela
   SET valor_pago_em_centavos = valor_em_centavos + juros_em_centavos
 WHERE paid = 1;

INSERT INTO parcela_pagamento
    (parcela_id, banco_id, valor_em_centavos, juros_em_centavos, data_pagamento, usuario_id)
SELECT id, banco_id, valor_em_centavos, juros_em_centavos, data_pagamento, NULL
  FROM parcela
 WHERE paid = 1
   AND banco_id IS NOT NULL
   AND data_pagamento IS NOT NULL;

-- Conferência: parcelas pagas sem banco/data não podem ser backfilled e devem
-- ser corrigidas manualmente antes de liberar o estorno dessas contas.
SELECT id, conta_id, numero_parcela, valor_em_centavos, juros_em_centavos
  FROM parcela
 WHERE paid = 1
   AND (banco_id IS NULL OR data_pagamento IS NULL);
