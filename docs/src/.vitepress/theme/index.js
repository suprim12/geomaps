import DefaultTheme from 'vitepress/theme'
import { nextTick, watch } from 'vue'
import './custom.css'

// Reveal home page sections as they reach the screen. A scroll check (not an observer) so sections
// skipped by a fast scroll or a jump are still revealed.
let stop = null

function revealHome() {
  stop?.()
  const targets = [...document.querySelectorAll('.home-hero-shot, .home section > *')]
  if (!targets.length) return

  document.documentElement.classList.add('geo-motion')

  let queued = false
  const check = () => {
    queued = false
    const limit = window.innerHeight * 0.92
    for (const el of targets) {
      if (!el.classList.contains('is-in') && el.getBoundingClientRect().top < limit) el.classList.add('is-in')
    }
  }
  const onScroll = () => {
    if (!queued) {
      queued = true
      requestAnimationFrame(check)
    }
  }

  check()
  window.addEventListener('scroll', onScroll, { passive: true })
  window.addEventListener('resize', onScroll)
  stop = () => {
    window.removeEventListener('scroll', onScroll)
    window.removeEventListener('resize', onScroll)
  }
}

export default {
  extends: DefaultTheme,
  enhanceApp({ router }) {
    if (typeof window === 'undefined') return
    watch(() => router.route.path, () => nextTick(revealHome), { immediate: true })
  },
}
