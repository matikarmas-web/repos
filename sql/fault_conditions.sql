-- SQL Server example. Adjust types for MySQL / Postgres if needed.
-- Empty condition (NULL or '') means the row always applies for that line+code.

IF OBJECT_ID('dbo.fault_conditions', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.fault_conditions (
        id          INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        line        VARCHAR(50)  NOT NULL,
        code        VARCHAR(50)  NOT NULL,
        condition   NVARCHAR(1000) NULL,
        enabled     BIT          NOT NULL CONSTRAINT DF_fault_conditions_enabled DEFAULT (1),
        sort_order  INT          NOT NULL CONSTRAINT DF_fault_conditions_sort DEFAULT (0),
        note        NVARCHAR(200) NULL
    );

    CREATE INDEX IX_fault_conditions_line_code
        ON dbo.fault_conditions (line, code, enabled);
END
GO

-- Named Query (Ignition): fault_conditions_for_line
-- Parameters: line (string)
/*
SELECT line, code, condition, sort_order
FROM dbo.fault_conditions
WHERE enabled = 1
  AND (line = :line OR line = '*')
ORDER BY sort_order, id
*/

-- Original PROM1004 / VA logic as two independent expressions (OR):
--   radio == 'na'      AND 'silence' in code_text
--   radio != 'na'      AND 'silence' not in code_text

INSERT INTO dbo.fault_conditions (line, code, condition, sort_order, note) VALUES
    ('PROM1004', 'VA', 'radio == ''na'' and ''silence'' in code_text', 10,
     'VA + na only when code field mentions silence'),
    ('PROM1004', 'VA', 'radio != ''na'' and ''silence'' not in code_text', 20,
     'VA + other radio only when code field does not mention silence');
GO
