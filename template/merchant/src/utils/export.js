import ExcelJS from 'exceljs';

/**
 * 创建边框样式
 * @param {string} color - ARGB颜色值
 * @param {string} style - 边框样式
 * @returns {Object} 边框样式对象
 */
function createBorderStyle(color = 'FF333333', style = 'thin') {
  return {
    top: { style, color: { argb: color }},
    left: { style, color: { argb: color }},
    bottom: { style, color: { argb: color }},
    right: { style, color: { argb: color }}
  };
}

/**
 * 设置标题样式
 * @param {Worksheet} worksheet - 工作表对象
 * @param {number} rowIndex - 标题行索引
 * @param {number} columnCount - 总列数
 */
function setTitleStyle(worksheet, rowIndex, columnCount) {
  const titleRow = worksheet.getRow(rowIndex);

  // 合并标题单元格（跨所有列）
  worksheet.mergeCells(rowIndex, 1, rowIndex, columnCount);

  const titleCell = titleRow.getCell(1);
  titleCell.value = worksheet.getRow(rowIndex).getCell(1).value;
  titleCell.style = {
    font: { name: '微软雅黑', size: 16, bold: true, color: { argb: 'FF000000' }},
    alignment: { horizontal: 'center', vertical: 'middle' },
    border: createBorderStyle('FF000000', 'medium')
  };

  // 设置标题行高
  titleRow.height = 30;
}

/**
 * 设置表头样式
 * @param {Worksheet} worksheet - 工作表对象
 * @param {number} rowIndex - 表头行索引
 * @param {number} columnCount - 总列数
 */
function setHeaderStyle(worksheet, rowIndex, columnCount) {
  const headerRow = worksheet.getRow(rowIndex);
  headerRow.height = 25;

  const headerBorder = createBorderStyle('FF333333', 'thin');

  // 为每个表头单元格设置样式
  for (let col = 1; col <= columnCount; col++) {
    headerRow.getCell(col).style = {
      font: { name: '微软雅黑', size: 11, bold: true, color: { argb: 'FF000000' }},
      fill: { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFC9E7C9' }},
      alignment: { horizontal: 'center', vertical: 'middle', wrapText: true },
      border: headerBorder
    };
  }
}

/**
 * 设置数据行样式
 * @param {Worksheet} worksheet - 工作表对象
 * @param {number} startRow - 数据起始行
 * @param {number} endRow - 数据结束行
 * @param {number} columnCount - 总列数
 */
function setDataRowStyle(worksheet, startRow, endRow, columnCount) {
  const dataBorder = createBorderStyle('FF999999', 'thin');

  for (let row = startRow; row <= endRow; row++) {
    const currentRow = worksheet.getRow(row);
    currentRow.height = 20;

    for (let col = 1; col <= columnCount; col++) {
      currentRow.getCell(col).style = {
        font: { name: '微软雅黑', size: 11, color: { argb: 'FF000000' }},
        alignment: { vertical: 'middle' },
        border: dataBorder
      };

      // 特殊处理金额列（右对齐）
      if (col === 11) { // 金额列是第11列（从1开始计数）
        currentRow.getCell(col).style.alignment.horizontal = 'right';
      }
    }
  }
}

/**
 * 自动调整列宽
 * @param {Worksheet} worksheet - 工作表对象
 * @param {Array} header - 表头数组
 * @param {Array} data - 数据数组
 */
function autoFitColumns(worksheet, header, data) {
  header.forEach((title, index) => {
    // 初始宽度设为表头文字长度
    let maxLength = title.length;

    // 检查所有数据行，找到最长内容
    data.forEach(row => {
      const cellValue = String(row[index] || '');
      maxLength = Math.max(maxLength, cellValue.length);
    });

    // 设置列宽（增加余量）
    worksheet.getColumn(index + 1).width = Math.max(maxLength * 1.2, 10);
  });
}

/**
 * 导出Excel文件（兼容header-title-data结构）
 * @param {string} fileName - 文件名
 * @param {Object} exportData - 导出数据，格式: {header: [], title: '', data: [[]]}
 */
export async function exportCustomExcel(fileName, exportData) {
  // 解构数据并校验
  const { header, title, data } = exportData;
  if (!header || !data || data.length === 0) {
    return  false;
  }

  try {
    // 创建工作簿和工作表
    const workbook = new ExcelJS.Workbook();
    const worksheet = workbook.addWorksheet('sheet1');

    const columnCount = header.length;

    // 添加标题行（如果有标题）
    let currentRow = 1;
    if (title) {
      worksheet.addRow([title]);
      setTitleStyle(worksheet, currentRow, columnCount);
      currentRow++;
    }

    // 添加表头行
    worksheet.addRow(header);
    setHeaderStyle(worksheet, currentRow, columnCount);
    currentRow++;

    // 添加数据行
    data.forEach(rowData => {
      worksheet.addRow(rowData);
    });

    // 设置数据行样式
    setDataRowStyle(worksheet, currentRow, currentRow + data.length - 1, columnCount);

    // 自动调整列宽
    autoFitColumns(worksheet, header, data);

    // 生成并下载文件
    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    });

    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = fileName || `${title || '数据'}.xlsx`;
    document.body.appendChild(link);
    link.click();

    // 清理资源
    setTimeout(() => {
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
    }, 100);
  } catch (error) {
    console.error('导出失败:', error);
    return false;
  }
}
