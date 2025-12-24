from pathlib import Path
path = Path('admin/reports.php')
text = path.read_text(encoding='latin-1')
text = text.replace('BA\xad o cA\xad o th\xa0 \xaf  ng kA\xa6', 'Báo cáo th?ng kê')
text = text.replace('Tÿ_r ngA\u02dc y', 'T? ngày')
text = text.replace('Z?ÿ\x15\"n ngA\u02dc y', 'Ð?n ngày')
text = text.replace('Xem bA-o cA-o', 'Xem báo cáo')
text = text.replace('Xuat Excel', 'Xu?t Excel')
path.write_text(text, encoding='utf-8')
