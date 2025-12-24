import csv
from openpyxl import Workbook
from openpyxl.styles import Font

# Đọc file CSV mẫu
csv_path = r'assets/excel/donation_template.csv'
xlsx_path = r'assets/excel/donation_template.xlsx'

wb = Workbook()
ws = wb.active

with open(csv_path, encoding='utf-8') as f:
    reader = csv.reader(f)
    for row in reader:
        ws.append(row)

# Đặt font Times New Roman cho toàn bộ sheet
for row in ws.iter_rows():
    for cell in row:
        cell.font = Font(name='Times New Roman')

wb.save(xlsx_path)
print('Đã tạo file Excel mẫu với font Times New Roman.')
