# QUY TRÌNH ĐIỀU HÀNH GIẢI ĐẤU PICKLEBALL

## 1. QUY TRÌNH CHUẨN (BEST PRACTICE)

### Giai đoạn 1: CHUẨN BỊ (Pre-Tournament)
1. **Lên kế hoạch giải đấu**
   - Chọn địa điểm, ngày giờ
   - Xác định thể thức thi đấu
   - Xác định hạng mục thi đấu (Nam, Nữ, Mixed,...)
   - Xác định trình độ (skill level)
   - Xác định số lượng đội tối đa

2. **Đăng ký và thu phí**
   - Mở đăng ký
   - Thu phí đăng ký
   - Xác nhận cặp đấu
   - Danh sách chờ (waitlist)

3. **Chuẩn bị sân và trang thiết bị**
   - Số lượng sân
   - Trọng tài
   - Bảng tỷ số, bóng, vợt dự phòng

### Giai đoạn 2: TỔ CHỨC (Tournament Setup)
1. **Tạo giải đấu trong hệ thống**
   - Thông tin cơ bản: tên, mô tả, địa điểm
   - Thể thức: round_robin, knockout, combined, double_elimination
   - Ngày bắt đầu, kết thúc
   - Hạng mục: Men's Doubles, Women's Doubles, Mixed Doubles

2. **Nhập danh sách đội/VĐV**
   - Import CSV hoặc nhập thủ công
   - Thông tin: tên đội, VĐV 1, VĐV 2, trình độ

3. **Phân nhóm trình độ (nếu cần)**
   - Tự động hoặc thủ công phân loại theo skill level

### Giai đoạn 3: BỐC THĂM (Draw)
1. **Chia bảng (Group Stage)**
   - Số lượng bảng
   - Phương pháp bốc thăm: ngẫu nhiên hoặc seed

2. **Tạo lịch thi đấu vòng bảng**
   - Round-robin: mỗi đội gặp nhau 1 lần
   - Tính thời gian: ~30 phút/trận

3. **Tạo lịch thi đấu vòng loại trực tiếp (nếu có)**
   - Xác định số đội vào knockout
   - Tạo bracket tự động

### Giai đoạn 4: THI ĐẤU (Tournament Day)
1. **Check-in VĐV**
   - Xác nhận có mặt
   - Phát thẻ thi đấu (nếu có)

2. **Điều hành trận đấu**
   - Cập nhật tỷ số trực tiếp
   - Quản lý lịch sân
   - Phân công trọng tài
   - Xử lý tranh chấp

3. **Cập nhật kết quả**
   - Bảng xếp hạng tự động
   - Cập nhật bracket

### Giai đoạn 5: KẾT THÚC (Wrap-up)
1. **Công bố kết quả**
   - Xếp hạng các đội
   - Trao giải

2. **Thống kê giải đấu**
   - Số trận đấu
   - Thời gian trung bình/trận
   - VĐV xuất sắc

3. **Lưu trữ dữ liệu**
   - Lưu kết quả vào archive

---

## 2. QUY TRÌNH HIỆN TẠI TRONG HỆ THỐNG

### Các bước hiện có:
```
1. Tạo giải đấu (create_tournament.php / admin.php)
      ↓
2. Import danh sách đội (draw.php - Tab Import CSV)
      ↓
3. Bốc thăm chia bảng (draw.php - Tab Bốc thăm)
      ↓
4. Quản lý đội/bảng (draw.php - Tab Danh sách đội/Bảng đấu)
      ↓
5. Điều hành trận đấu (match-control.php)
      ↓
6. Cập nhật tỷ số (match-control.php)
```

### Các file quan trọng:
- `create_tournament.php` - Tạo giải đấu mới
- `draw.php` - Import đội, bốc thăm chia bảng
- `match-control.php` - Điều hành trận đấu trực tiếp
- `admin.php` - Quản lý toàn bộ hệ thống

### Các bảng database:
- `Tournaments` - Thông tin giải đấu
- `Teams` - Danh sách đội
- `Groups` - Các bảng đấu
- `Matches` - Các trận đấu
- `Arena` - Sân thi đấu

---

## 3. ĐỀ XUẤT CẢI THIỆN

### 3.1. BỔ SUNG TÍNH NĂNG QUẢN LÝ GIẢI ĐẤU

#### A. Quản lý Giai đoạn (Stage Management)
- **Thêm trường `stage` vào bảng Tournaments:**
  - `planning` - Lập kế hoạch
  - `registration` - Đăng ký
  - `setup` - Chuẩn bị
  - `group_stage` - Vòng bảng
  - `knockout_stage` - Vòng loại trực tiếp
  - `completed` - Hoàn thành

- **Tạo bảng TournamentStages:**
  ```sql
  CREATE TABLE TournamentStages (
      id INT AUTO_INCREMENT PRIMARY KEY,
      tournament_id INT,
      stage_name VARCHAR(50),
      stage_order INT,
      status ENUM('pending', 'active', 'completed') DEFAULT 'pending',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  );
  ```

#### B. Quản lý Hạng mục (Categories)
- Thêm bảng `TournamentCategories`:
  ```sql
  CREATE TABLE TournamentCategories (
      id INT AUTO_INCREMENT PRIMARY KEY,
      tournament_id INT,
      name VARCHAR(100), -- Men's Doubles, Women's Doubles, Mixed
      skill_min FLOAT,
      skill_max FLOAT,
      age_min INT,
      age_max INT,
      max_teams INT,
      status ENUM('open', 'closed', 'completed') DEFAULT 'open'
  );
  ```

#### C. Đăng ký Online cho VĐV
- Tạo trang `register.php` cho phép VĐV đăng ký
- Xác nhận qua email
- Thanh toán phí đăng ký

#### D. Quản lý Trọng tài
- Phân công trọng tài cho từng trận
- Thông báo cho trọng tài
- Theo dõi lịch sử trọng tài

### 3.2. CẢI THIỆN THỂ THỨC THI ĐẤU

#### A. Double Elimination
- Hiện tại hệ thống hỗ trợ: `round_robin`, `knockout`, `combined`
- **Cần bổ sung:** `double_elimination`

- Logic Double Elimination:
  - Winners Bracket (WB)
  - Losers Bracket (LB)
  - Grand Finals (có/không bracket reset)

#### B. Bảng xếp hạng tự động
- Tính điểm: thắng 3 điểm, hòa 1 điểm, thua 0 điểm
- Hiệu số bàn thắng/thua
- Thành tích đối đầu
- Xếp hạng theo nhóm

### 3.3. CẢI THIỆN TRẢI NGHIỆM NGƯỜI DÙNG

#### A. Wizard tạo giải đấu
- Bước 1: Thông tin cơ bản
- Bước 2: Thể thức & hạng mục
- Bước 3: Import đội
- Bước 4: Bốc thăm
- Bước 5: Xác nhận

#### B. Dashboard điều hành giải đấu
- Hiển thị tất cả trận đấu trong ngày
- Trạng thái từng sân
- Thông báo trận tiếp theo
- Nút cập nhật nhanh

#### C. Trang công khai kết quả
- Link công khai cho người xem
- Cập nhật real-time
- Bảng xếp hạng

### 3.4. THỐNG KÊ VÀ BÁO CÁO

#### A. Thống kê VĐV
- Số trận đã đấu
- Tỷ lệ thắng/thua
- Thời gian thi đấu trung bình

#### B. Thống kê giải đấu
- Tổng thời gian
- Số trận/ngày
- Số sân sử dụng
- Doanh thu/chi phí

---

## 4. CÁC BƯỚC CẦN THỰC HIỆN ĐỂ REBUILD

### Ưu tiên cao (Phase 1):
1. Thêm trường `stage` vào Tournaments
2. Tạo bảng TournamentCategories
3. Cải thiện Wizard tạo giải đấu
4. Dashboard điều hành trận đấu

### Ưu tiên trung bình (Phase 2):
1. Bổ sung Double Elimination
2. Trang đăng ký online
3. Bảng xếp hạng tự động
4. Quản lý trọng tài

### Ưu tiên thấp (Phase 3):
1. Thống kê và báo cáo
2. Trang công khai kết quả
3. Tích hợp thanh toán
4. Gửi email thông báo

---

## 5. QUY TRÌNH ĐIỀU HÀNH GIẢI ĐẤU ĐỀ XUẤT

```
┌─────────────────────────────────────────────────────────────────┐
│  GIAI ĐOẠN 1: CHUẨN BỊ                                         │
├─────────────────────────────────────────────────────────────────┤
│  1.1. Tạo giải đấu (Admin)                                     │
│       → Chọn thể thức, ngày giờ, địa điểm                     │
│  1.2. Tạo hạng mục (Categories)                                │
│       → Men's, Women's, Mixed theo skill level                 │
│  1.3. Mở đăng ký (hoặc import danh sách)                      │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│  GIAI ĐOẠN 2: TỔ CHỨC                                          │
├─────────────────────────────────────────────────────────────────┤
│  2.1. Xác nhận đội tham dự                                     │
│  2.2. Phân nhóm ( chia bảng theo skill level)                  │
│  2.3. Bốc thăm ngẫu nhiên hoặc seed                            │
│  2.4. Tạo lịch thi đấu vòng bảng                               │
│  2.5. Tạo lịch thi đấu vòng loại (nếu có)                      │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│  GIAI ĐOẠN 3: THI ĐẤU                                          │
├─────────────────────────────────────────────────────────────────┤
│  3.1. Check-in VĐV                                             │
│  3.2. Dashboard điều hành                                      │
│       → Hiển thị: lịch sân, trận đang đấu, trận tiếp theo     │
│  3.3. Cập nhật tỷ số trực tiếp                                 │
│  3.4. Phân công trọng tài                                       │
│  3.5. Cập nhật bảng xếp hạng                                   │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│  GIAI ĐOẠN 4: KẾT THÚC                                         │
├─────────────────────────────────────────────────────────────────┤
│  4.1. Hoàn tất tất cả trận đấu                                 │
│  4.2. Công bố kết quả và xếp hạng                              │
│  4.3. Lưu trữ dữ liệu                                          │
│  4.4. Thống kê giải đấu                                        │
└─────────────────────────────────────────────────────────────────┘
```

---

## 6. CHECKLIST ĐIỀU HÀNH GIẢI ĐẤU

### Trước ngày thi đấu:
- [ ] Xác nhận sân và trang thiết bị
- [ ] In danh sách đội và lịch thi đấu
- [ ] Chuẩn bị bảng tỷ số
- [ ] Phân công trọng tài
- [ ] Chuẩn bị nước uống, chỗ ngồi

### Trong ngày thi đấu:
- [ ] Check-in VĐV
- [ ] Kiểm tra danh sách trận đấu
- [ ] Điều phối sân thi đấu
- [ ] Cập nhật kết quả kịp thời
- [ ] Xử lý các tình huống phát sinh

### Sau ngày thi đấu:
- [ ] Lưu trữ kết quả
- [ ] Thống kê giải đấu
- [ ] Cập nhật kết quả vào hệ thống
