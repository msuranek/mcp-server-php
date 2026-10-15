REVOKE CREATE ON SCHEMA public FROM PUBLIC;

CREATE TABLE customers (
    id BIGSERIAL PRIMARY KEY,
    company_name TEXT NOT NULL,
    region TEXT NOT NULL,
    segment TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL
);

CREATE TABLE orders (
    id BIGSERIAL PRIMARY KEY,
    customer_id BIGINT NOT NULL REFERENCES customers (id),
    status TEXT NOT NULL CHECK (status IN ('pending', 'processing', 'completed', 'cancelled')),
    ordered_at TIMESTAMPTZ NOT NULL,
    total_amount NUMERIC(12, 2) NOT NULL CHECK (total_amount >= 0),
    currency CHAR(3) NOT NULL
);

CREATE TABLE order_items (
    id BIGSERIAL PRIMARY KEY,
    order_id BIGINT NOT NULL REFERENCES orders (id),
    item_code TEXT NOT NULL,
    quantity INTEGER NOT NULL CHECK (quantity > 0),
    unit_price NUMERIC(12, 2) NOT NULL CHECK (unit_price >= 0)
);

INSERT INTO customers (company_name, region, segment, created_at) VALUES
    ('Northwind Labs', 'EU', 'technology', '2026-01-12T09:00:00Z'),
    ('Blue Oak Studio', 'NA', 'creative', '2026-02-03T14:30:00Z'),
    ('Summit Works', 'EU', 'manufacturing', '2026-02-18T11:15:00Z'),
    ('Harbor Analytics', 'APAC', 'professional-services', '2026-03-01T08:45:00Z');

INSERT INTO orders (customer_id, status, ordered_at, total_amount, currency) VALUES
    (1, 'completed', '2026-03-02T10:10:00Z', 1240.00, 'EUR'),
    (2, 'processing', '2026-03-04T16:25:00Z', 875.50, 'USD'),
    (1, 'pending', '2026-03-05T08:20:00Z', 219.90, 'EUR'),
    (3, 'completed', '2026-03-06T12:00:00Z', 3420.00, 'EUR'),
    (4, 'cancelled', '2026-03-07T07:35:00Z', 640.00, 'USD');

INSERT INTO order_items (order_id, item_code, quantity, unit_price) VALUES
    (1, 'ANALYTICS-SEAT', 10, 124.00),
    (2, 'DESIGN-PACK', 5, 175.10),
    (3, 'SUPPORT-HOUR', 3, 73.30),
    (4, 'FACTORY-SENSOR', 20, 171.00),
    (5, 'REPORT-BUNDLE', 8, 80.00);

CREATE ROLE mcp_reader LOGIN PASSWORD 'mcp_reader_demo_password';
ALTER ROLE mcp_reader SET default_transaction_read_only = on;
ALTER ROLE mcp_reader SET statement_timeout = '3s';
GRANT CONNECT ON DATABASE mcp_demo TO mcp_reader;
GRANT USAGE ON SCHEMA public TO mcp_reader;
GRANT SELECT ON customers, orders, order_items TO mcp_reader;
