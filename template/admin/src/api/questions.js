import request from '@/utils/request'

// 题目管理
export function getQuestionsList(params) {
  return request({
    url: '/api/questions/questions',
    method: 'get',
    params
  })
}

export function deleteQuestion(data) {
  return request({
    url: '/api/questions/questions/delete',
    method: 'post',
    data
  })
}

export function saveQuestion(data) {
  return request({
    url: '/api/questions/questions/save',
    method: 'post',
    data
  })
}

export function getQuestionDetail(params) {
  return request({
    url: '/api/questions/questions/detail',
    method: 'get',
    params
  })
}

export function getSubjectList() {
  return request({
    url: '/api/questions/subject',
    method: 'get'
  })
}

// 题目分类管理
export function getCategoryList(params) {
  return request({
    url: '/api/questions/questions_categpry',
    method: 'get',
    params
  })
}

export function deleteCategory(data) {
  return request({
    url: '/api/questions/questions_categpry/delete',
    method: 'post',
    data
  })
}

export function saveCategory(data) {
  return request({
    url: '/api/questions/questions_categpry/save',
    method: 'post',
    data
  })
}

export function getCategoryDetail(params) {
  return request({
    url: '/api/questions/questions_categpry/detail',
    method: 'get',
    params
  })
}

// 试卷管理
export function getTestPaperList(params) {
  return request({
    url: '/api/questions/test_paper',
    method: 'get',
    params
  })
}

export function deleteTestPaper(data) {
  return request({
    url: '/api/questions/test_paper/delete',
    method: 'post',
    data
  })
}

export function saveTestPaper(data) {
  return request({
    url: '/api/questions/test_paper/save',
    method: 'post',
    data
  })
}

export function getTestPaperDetail(params) {
  return request({
    url: '/api/questions/test_paper/detail',
    method: 'get',
    params
  })
}

export function getPaperQuestionsList(paperId, params) {
  return request({
    url: `/api/questions/test_paper/questions/${paperId}`,
    method: 'get',
    params
  })
}

export function addQuestionsToPaper(data) {
  return request({
    url: '/api/questions/test_paper/add_questions',
    method: 'post',
    data
  })
}

export function removePaperQuestion(data) {
  return request({
    url: '/api/questions/test_paper/remove_question',
    method: 'post',
    data
  })
}

export function getTestPaperCreate(params) {
  return request({
    url: '/questions/test_paper/create',
    method: 'get',
    params
  })
}

export function getQuestionCreate(params) {
  return request({
    url: '/questions/questions/create',
    method: 'get',
    params
  })
}

export function getQuestionCategoryCreate(params) {
  return request({
    url: '/questions/questions_categpry/create',
    method: 'get',
    params
  })
}
