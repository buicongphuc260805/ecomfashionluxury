/**
 * Định dạng ngày theo chuẩn ngày / tháng / năm (dd/MM/yyyy)
 */
export const formatDate = (dateStr) => {
  if (!dateStr) return '';
  const str = String(dateStr).trim();
  
  // Nếu đã có format dd/MM/yyyy thì giữ nguyên
  if (/^\d{2}\/\d{2}\/\d{4}/.test(str)) {
    return str.split(' ')[0];
  }

  // Xử lý chuỗi định dạng YYYY-MM-DD
  if (/^\d{4}-\d{2}-\d{2}/.test(str)) {
    const datePart = str.split('T')[0].split(' ')[0];
    const parts = datePart.split('-');
    if (parts.length === 3) {
      return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
  }

  try {
    const d = new Date(str);
    if (isNaN(d.getTime())) return str;
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    return `${day}/${month}/${year}`;
  } catch {
    return str;
  }
};

/**
 * Định dạng ngày giờ theo chuẩn dd/MM/yyyy HH:mm
 */
export const formatDateTime = (dateStr) => {
  if (!dateStr) return '';
  const str = String(dateStr).trim();

  try {
    const d = new Date(str);
    if (isNaN(d.getTime())) return str;
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    return `${day}/${month}/${year} ${hours}:${minutes}`;
  } catch {
    return str;
  }
};

/**
 * Định dạng tiền tệ VND
 */
export const formatCurrency = (value) => {
  const numericValue = Number(value);
  if (isNaN(numericValue)) return value || '0 ₫';
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
  }).format(numericValue);
};
