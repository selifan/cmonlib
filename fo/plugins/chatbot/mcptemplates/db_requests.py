# Скрипт для MCP вызовов - работа с БД MySQL (GigaChat helps!)
import sys
import mysql.connector
import json

# Загрузка конфигурации из JSON-файла или переменных окружения
config = {
    'host': 'localhost',
    'user': 'your_user',
    'password': 'your_password',
    'database': 'your_database',
    'port': 3306
}

def execute_query(sql_query):
    try:
        conn = mysql.connector.connect(**config)
        cursor = conn.cursor(dictionary=True)
        cursor.execute(sql_query)
        result = cursor.fetchall()
        print(json.dumps(result, ensure_ascii=False, indent=2))
    except Exception as e:
        print(f"Ошибка: {e}")
    finally:
        if conn.is_connected():
            cursor.close()
            conn.close()

if __name__ == "__main__":
    if len(sys.argv) > 1:
        sql_query = sys.argv[1]
        execute_query(sql_query)
