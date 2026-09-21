import settings from '@/settings';

const prefix = '/' + (settings.prefix ? settings.prefix + '/' : '')
const educationalRouter = {
  path: prefix + 'educational',
  component: () => import('@/layout'),
  name: 'Educational',
  meta: {
    title: '教育管理',
    icon: 'el-icon-education'
  },
  children: [
    {
      path: 'classes',
      component: () => import('@/views/educational/classes/index'),
      name: 'Classes',
      meta: {
        title: '班级管理',
        noCache: true
      }
    },
    {
      path: 'student',
      component: () => import('@/views/educational/student/index'),
      name: 'Student',
      meta: {
        title: '学员管理',
        noCache: true
      }
    },
    {
      path: 'teacher',
      component: () => import('@/views/educational/teacher/index'),
      name: 'Teacher',
      meta: {
        title: '教师管理',
        noCache: true
      }
    },
    {
      path: 'teacher_categpry',
      component: () => import('@/views/educational/teacher_categpry/index'),
      name: 'TeacherCategory',
      meta: {
        title: '教师分类',
        noCache: true
      }
    }
  ]
}

export default educationalRouter
