import mysql.connector
import psycopg2

MYSQL_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'bmb_tournaments'
}

NEON_CONFIG = {
    'host': 'ep-silent-base-a1guw7kr-pooler.ap-southeast-1.aws.neon.tech',
    'user': 'neondb_owner',
    'password': 'npg_cQzBkwW0h4KF',
    'database': 'neondb',
    'sslmode': 'require',
    'channel_binding': 'require'
}

def get_mysql_tables(cursor):
    cursor.execute("SHOW TABLES")
    return [list(row)[0] for row in cursor.fetchall()]

def get_mysql_schema(cursor, table):
    cursor.execute(f"SHOW CREATE TABLE {table}")
    return cursor.fetchone()[1]

def convert_type(mysql_type):
    mysql_type_orig = mysql_type.strip()
    mysql_type = mysql_type.upper().strip()
    
    if mysql_type.startswith('ENUM'):
        return 'VARCHAR(100)'
    
    type_mappings = {
        'INT': 'INTEGER',
        'TINYINT': 'SMALLINT',
        'SMALLINT': 'SMALLINT',
        'MEDIUMINT': 'INTEGER',
        'BIGINT': 'BIGINT',
        'FLOAT': 'REAL',
        'DOUBLE': 'DOUBLE PRECISION',
        'DECIMAL': 'DECIMAL',
        'CHAR': 'VARCHAR',
        'TEXT': 'TEXT',
        'DATE': 'DATE',
        'DATETIME': 'TIMESTAMP',
        'TIMESTAMP': 'TIMESTAMP',
        'TIME': 'TIME',
        'JSON': 'JSONB',
        'BOOL': 'BOOLEAN',
        'BOOLEAN': 'BOOLEAN',
    }
    
    for key, value in type_mappings.items():
        if mysql_type.startswith(key):
            if '(' in mysql_type:
                match = re.search(r'\((\d+)\)', mysql_type_orig)
                if match:
                    return value
                elif key in ('CHAR', 'VARCHAR'):
                    return f"VARCHAR(255)"
            else:
                if key == 'BOOL':
                    return 'BOOLEAN'
                return value
    
    if 'VARCHAR' in mysql_type:
        match = re.search(r'VARCHAR\((\d+)\)', mysql_type)
        if match:
            return f"VARCHAR({match.group(1)})"
        return 'VARCHAR(255)'
    
    return 'TEXT'

def convert_schema(mysql_schema, table_name):
    lines = [l.strip() for l in mysql_schema.split('\n') if l.strip()]
    
    pg_columns = []
    
    for line in lines[1:]:
        if line.startswith('PRIMARY KEY') or line.startswith('KEY') or line.startswith('UNIQUE KEY') or line.startswith('CONSTRAINT') or line.startswith('ENGINE') or line.startswith(')'):
            continue
        
        line = line.rstrip(',').strip()
        if not line or line.startswith('PRIMARY KEY'):
            continue
        
        col_name_match = re.match(r'`?(\w+)`?\s+', line)
        if not col_name_match:
            continue
        
        col_name = col_name_match.group(1)
        rest = line[col_name_match.end():]
        
        col_type_raw = rest.split('DEFAULT')[0].split('NOT NULL')[0].split('NULL')[0].strip()
        col_type = convert_type(col_type_raw)
        
        is_nullable = 'NOT NULL' not in rest
        
        default = ''
        if 'DEFAULT' in rest.upper():
            def_match = re.search(r'DEFAULT\s+([^\s,]+)', rest, re.IGNORECASE)
            if def_match:
                default_val = def_match.group(1).strip("'\"")
                if 'CURRENT_TIMESTAMP' in default_val.upper():
                    default = " DEFAULT CURRENT_TIMESTAMP"
                elif default_val.upper() != 'NULL':
                    default = f" DEFAULT '{default_val}'"
        
        null_str = ' NULL' if is_nullable else ''
        pg_columns.append(f"{col_name} {col_type}{default}{null_str}")
    
    pg_schema = f'CREATE TABLE {table_name} (\n  ' + ',\n  '.join(pg_columns) + '\n);'
    return pg_schema

def get_mysql_data(cursor, table):
    cursor.execute(f"SELECT * FROM {table}")
    columns = [desc[0] for desc in cursor.description]
    rows = cursor.fetchall()
    return columns, rows

def reconnect_pg():
    return psycopg2.connect(**NEON_CONFIG)

import re

def main():
    print("Connecting to MySQL...")
    mysql_conn = mysql.connector.connect(**MYSQL_CONFIG)
    mysql_cursor = mysql_conn.cursor()
    
    print("Connecting to Neon PostgreSQL...")
    pg_conn = psycopg2.connect(**NEON_CONFIG)
    pg_cursor = pg_conn.cursor()
    
    tables = get_mysql_tables(mysql_cursor)
    print(f"Found {len(tables)} tables: {tables}")
    
    for table in tables:
        print(f"\n--- Processing table: {table} ---")
        
        mysql_schema = get_mysql_schema(mysql_cursor, table)
        pg_schema = convert_schema(mysql_schema, table)
        
        try:
            pg_cursor.execute(f"DROP TABLE IF EXISTS {table} CASCADE")
            pg_conn.commit()
        except Exception as e:
            print(f"Error dropping: {e}")
            pg_conn = reconnect_pg()
            pg_cursor = pg_conn.cursor()
            try:
                pg_cursor.execute(f"DROP TABLE IF EXISTS {table} CASCADE")
                pg_conn.commit()
            except Exception as e2:
                print(f"Error dropping again: {e2}")
                continue
        
        try:
            pg_cursor.execute(pg_schema)
            pg_conn.commit()
            print(f"Created table {table}")
        except Exception as e:
            print(f"Error creating table: {e}")
            print(f"Schema: {pg_schema}")
            pg_conn = reconnect_pg()
            pg_cursor = pg_conn.cursor()
            continue
        
        columns, rows = get_mysql_data(mysql_cursor, table)
        
        if not rows:
            print(f"No data in {table}")
            continue
        
        print(f"Migrating {len(rows)} rows...")
        
        placeholders = ','.join(['%s'] * len(columns))
        insert_sql = f"INSERT INTO {table} ({','.join(columns)}) VALUES ({placeholders})"
        
        for row in rows:
            try:
                pg_cursor.execute(insert_sql, row)
            except Exception as e:
                print(f"Error inserting: {e}")
        
        try:
            pg_conn.commit()
            print(f"Completed {table}")
        except Exception as e:
            print(f"Error committing: {e}")
            pg_conn = reconnect_pg()
            pg_cursor = pg_conn.cursor()
    
    mysql_cursor.close()
    mysql_conn.close()
    pg_cursor.close()
    pg_conn.close()
    
    print("\n=== Migration completed! ===")

if __name__ == "__main__":
    main()
