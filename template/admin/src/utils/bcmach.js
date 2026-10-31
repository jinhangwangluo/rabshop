/**
 * 高精度加法函数
 * @param num1
 * @param num2
 * @returns {string}
 */
export function add(arg1, arg2) {
  var r1, r2, m, n;
  try {
    r1 = arg1.toString().split('.')[1].length;
  } catch (e) {
    r1 = 0;
  }
  try {
    r2 = arg2.toString().split('.')[1].length;
  } catch (e) {
    r2 = 0;
  }
  m = Math.pow(10, Math.max(r1, r2));
  n = r1 >= r2 ? r1 : r2;
  return ((arg1 * m + arg2 * m) / m).toFixed(n);
}

/**
 * 高精度减法函数
 * @param num1
 * @param num2
 * @returns {string}
 */
export function subtract(num1, num2) {
  let borrow = 0;
  let result = '';
  let i = num1.length - 1;
  let j = num2.length - 1;
  while (i >= 0 || j >= 0) {
    const digit1 = i >= 0 ? parseInt(num1[i]) : 0;
    const digit2 = j >= 0 ? parseInt(num2[j]) : 0;
    let diff = digit1 - digit2 - borrow;
    if (diff < 0) {
      diff += 10;
      borrow = 1;
    } else {
      borrow = 0;
    }
    result = diff + result;
    i--;
    j--;
  }
  // 去除结果前面多余的零
  while (result[0] === '0' && result.length > 1) {
    result = result.slice(1);
  }
  return result;
}

/**
 * 高精度乘法函数
 * @param num1
 * @param num2
 * @returns {string}
 */
export function multiply(arg1, arg2) {
  let m = 0; const s1 = arg1.toString(); const s2 = arg2.toString();

  try {
    m += s1.split('.')[1].length
  } catch (e) {
    // 没有小数部分时无需增加位数
  }

  try {
    m += s2.split('.')[1].length
  } catch (e) {
    // 没有小数部分时无需增加位数
  }

  return Number(s1.replace('.', '')) * Number(s2.replace('.', '')) / Math.pow(10, m)
}

/**
 * 高精度除法函数，仅处理整数除法
 * @param num1
 * @param num2
 * @returns {string}
 */
export function divide(num1, num2) {
  if (num2 === '0') throw new Error('Division by zero');
  if (num1 === '0') return '0';
  let quotient = '';
  let remainder = '';
  for (let i = 0; i < num1.length; i++) {
    remainder += num1[i];
    let q = 0;
    while (compare(remainder, num2) >= 0) {
      remainder = subtract(remainder, num2);
      q++;
    }
    quotient += q;
  }
  // 去除结果前面多余的零
  while (quotient[0] === '0' && quotient.length > 1) {
    quotient = quotient.slice(1);
  }
  return quotient;
}

/**
 * 辅助函数，用于比较两个数的大小
 * @param num1
 * @param num2
 * @returns {number}
 */
export function compare(num1, num2) {
  if (num1.length > num2.length) return 1;
  if (num1.length < num2.length) return -1;
  for (let i = 0; i < num1.length; i++) {
    if (num1[i] > num2[i]) return 1;
    if (num1[i] < num2[i]) return -1;
  }
  return 0;
}
