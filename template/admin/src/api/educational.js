import request from '@/utils/request'

// 获取班级列表
export function getClassList(params) {
  return request({
    url: '/admin/educational/classes/getClassesList',
    method: 'get',
    params
  })
}

// 删除班级
export function deleteClass(params) {
  return request({
    url: '/admin/educational/classes/delete',
    method: 'get',
    params
  })
}

// 获取学员列表
export function getStudentList(params) {
  return request({
    url: '/admin/educational/student/getStudentList',
    method: 'get',
    params
  })
}

// 删除学员
export function deleteStudent(params) {
  return request({
    url: '/admin/educational/student/delete',
    method: 'get',
    params
  })
}

// 获取教师列表
export function getTeacherList(params) {
  return request({
    url: '/admin/educational/teacher/index',
    method: 'get',
    params
  })
}

// 删除教师
export function deleteTeacher(params) {
  return request({
    url: '/admin/educational/teacher/delete',
    method: 'get',
    params
  })
}

// 获取教师分类
export function getTeacherCategory() {
  return request({
    url: '/admin/educational/teacher_categpry/index',
    method: 'get'
  })
}

// 获取教师分类列表
export function getTeacherCategoryList(params) {
  return request({
    url: '/admin/educational/teacher_categpry/index',
    method: 'get',
    params
  })
}

// 删除教师分类
export function deleteTeacherCategory(params) {
  return request({
    url: '/admin/educational/teacher_categpry/delete',
    method: 'get',
    params
  })
}

export function getTeacherCreate(params) {
  return request({
    url: '/educational/teacher/create',
    method: 'get',
    params
  })
}

export function getStudentCreate(params) {
  return request({
    url: '/educational/student/create',
    method: 'get',
    params
  })
}

export function getClassCreate(params) {
  return request({
    url: '/educational/classes/create',
    method: 'get',
    params
  })
}

export function getTeacherCategoryCreate(params) {
  return request({
    url: '/educational/teacher_categpry/create',
    method: 'get',
    params
  })
}
