SELECT pg_advisory_lock(72147);

SELECT 'CREATE DATABASE transactions_test OWNER transactions'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'transactions_test')
\gexec

SELECT pg_advisory_unlock(72147);
