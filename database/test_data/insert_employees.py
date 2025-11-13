#!/usr/bin/env python3
import psycopg2
import json

# Database connection
conn = psycopg2.connect(
    host="localhost",
    port=5432,
    database="orgtrakker_100000",
    user="workmatica_user",
    password="securepassword"
)
cur = conn.cursor()

# Read and execute SQL file
with open('insert_realistic_employees.sql', 'r') as f:
    sql = f.read()
    # Execute SQL commands
    cur.execute(sql)
    conn.commit()

cur.close()
conn.close()
print("Done!")

