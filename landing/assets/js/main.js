/* Лендинг «ИИ-агенты для повседневных задач»: меню, прокрутка, анимации, форма, Яндекс Метрика. */
(function () {
  'use strict';

  document.documentElement.classList.add('js');

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- Яндекс Метрика ----------
     ID счётчика хранится только в config.php и приходит из send.php?action=config.
     Счётчик грузится после первого действия посетителя или через 3 с после загрузки страницы,
     чтобы не тормозить первый экран. Цели до этого момента копятся в очереди. */
  var metrikaId = null;
  var metrikaState = 'pending'; // pending → ready | off
  var pendingGoals = [];

  function goal(name) {
    if (metrikaId && typeof window.ym === 'function') window.ym(metrikaId, 'reachGoal', name);
    else if (metrikaState === 'pending') pendingGoals.push(name);
  }

  // Официальный код счётчика, только ID берётся из config.php
  function loadMetrika(id) {
    var src = 'https://mc.yandex.ru/metrika/tag.js?id=' + id;
    window.ym = window.ym || function () { (window.ym.a = window.ym.a || []).push(arguments); };
    window.ym.l = Date.now();
    if (!document.querySelector('script[src="' + src + '"]')) {
      var script = document.createElement('script');
      script.async = true;
      script.src = src;
      document.head.appendChild(script);
    }
    window.ym(id, 'init', {
      ssr: true,
      webvisor: true,
      clickmap: true,
      ecommerce: 'dataLayer',
      referrer: document.referrer,
      url: location.href,
      accurateTrackBounce: true,
      trackLinks: true
    });
    metrikaId = id;
    metrikaState = 'ready';
    pendingGoals.forEach(function (name) { window.ym(id, 'reachGoal', name); });
    pendingGoals = [];
  }

  function disableMetrika() {
    metrikaState = 'off';
    pendingGoals = [];
  }

  function initMetrika() {
    if (!window.fetch) return disableMetrika();
    var started = false;
    var triggers = ['scroll', 'pointerdown', 'keydown', 'touchstart', 'mousemove'];

    function start() {
      if (started) return;
      started = true;
      triggers.forEach(function (type) { window.removeEventListener(type, start, { passive: true }); });
      fetch('send.php?action=config', { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (cfg) {
          if (cfg && /^\d+$/.test(String(cfg.metrikaId || ''))) loadMetrika(Number(cfg.metrikaId));
          else disableMetrika();
        })
        .catch(disableMetrika); // без Метрики сайт работает как обычно
    }

    triggers.forEach(function (type) { window.addEventListener(type, start, { passive: true }); });
    if (document.readyState === 'complete') setTimeout(start, 3000);
    else window.addEventListener('load', function () { setTimeout(start, 3000); });
  }

  document.addEventListener('click', function (e) {
    if (e.target.closest('.js-cta')) goal('click_cta');
    if (e.target.closest('.js-tg')) goal('click_telegram');
  });

  /* ---------- Контакты ----------
     Ссылки хранят адрес в data-href. Пока там плейсхолдер {{...}}, ссылка ведёт к форме. */
  function initContacts() {
    document.querySelectorAll('[data-href]').forEach(function (link) {
      var href = link.getAttribute('data-href');
      if (href && href.indexOf('{{') === -1) {
        link.href = href;
        if (/^https?:/.test(href)) { link.target = '_blank'; link.rel = 'noopener'; }
      }
    });
  }

  /* ---------- Меню ---------- */
  function initMenu() {
    var toggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('site-nav');
    if (!toggle || !nav) return;

    function setOpen(open) {
      nav.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
    }

    toggle.addEventListener('click', function () {
      setOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });
    nav.addEventListener('click', function (e) {
      if (e.target.closest('a')) setOpen(false);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && nav.classList.contains('is-open')) {
        setOpen(false);
        toggle.focus();
      }
    });
    window.matchMedia('(min-width: 1024px)').addEventListener('change', function () { setOpen(false); });
  }

  /* ---------- Плавная прокрутка к якорям ---------- */
  function initAnchors() {
    document.addEventListener('click', function (e) {
      var link = e.target.closest('a[href^="#"]');
      if (!link || e.defaultPrevented) return;
      var id = link.getAttribute('href').slice(1);
      var target = id && document.getElementById(id);
      if (!target) return;
      e.preventDefault();
      target.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
      if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
      target.focus({ preventScroll: true });
      if (history.replaceState) history.replaceState(null, '', '#' + id);
    });
  }

  /* ---------- Появление блоков при прокрутке ---------- */
  function initReveal() {
    var items = document.querySelectorAll('.reveal');
    if (reduceMotion || !('IntersectionObserver' in window)) {
      items.forEach(function (el) { el.classList.add('is-visible'); });
      return;
    }
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.1 });
    items.forEach(function (el) { observer.observe(el); });
  }

  /* ---------- Форма заявки ---------- */
  var validators = {
    name: function (v) {
      v = v.trim();
      if (!v) return 'Укажите, как к вам обращаться.';
      if (v.length < 2) return 'Имя слишком короткое.';
      return '';
    },
    contact: function (v) {
      v = v.trim();
      if (!v) return 'Укажите телефон или ник в Telegram.';
      var digits = v.replace(/\D/g, '');
      var isPhone = /^[\d\s()+\-.]+$/.test(v) && digits.length >= 10 && digits.length <= 15;
      var isTelegram = /^(@|(https?:\/\/)?t\.me\/)?[A-Za-z][A-Za-z0-9_]{4,31}$/.test(v);
      return isPhone || isTelegram ? '' : 'Укажите телефон (например, +7 900 000-00-00) или ник в Telegram (@username).';
    },
    task: function (v) {
      v = v.trim();
      if (!v) return 'Опишите задачу хотя бы в двух словах.';
      if (v.length < 5) return 'Опишите задачу чуть подробнее.';
      return '';
    },
    consent: function (v, field) {
      return field.checked ? '' : 'Нужно согласие на обработку персональных данных.';
    }
  };

  function setFieldError(form, name, message) {
    var field = form.elements[name];
    var error = document.getElementById(field.id + '-error');
    if (message) field.setAttribute('aria-invalid', 'true');
    else field.removeAttribute('aria-invalid');
    if (error) error.textContent = message || '';
  }

  function validateField(form, name) {
    var field = form.elements[name];
    var message = validators[name](field.value, field);
    setFieldError(form, name, message);
    return !message;
  }

  function setStatus(el, type, text) {
    el.className = 'form-status' + (type ? ' is-' + type : '');
    el.textContent = text || '';
  }

  function initForm() {
    var form = document.getElementById('lead-form');
    if (!form || !window.fetch || !window.FormData) return;
    var status = document.getElementById('form-status');
    var submit = form.querySelector('[type="submit"]');
    var names = Object.keys(validators);

    form.noValidate = true;

    names.forEach(function (name) {
      var field = form.elements[name];
      var eventName = field.type === 'checkbox' ? 'change' : 'blur';
      field.addEventListener(eventName, function () { validateField(form, name); });
      field.addEventListener('input', function () {
        if (field.getAttribute('aria-invalid') === 'true') validateField(form, name);
      });
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      setStatus(status, '', '');

      var firstInvalid = null;
      names.forEach(function (name) {
        if (!validateField(form, name) && !firstInvalid) firstInvalid = form.elements[name];
      });
      if (firstInvalid) {
        firstInvalid.focus();
        return;
      }

      submit.disabled = true;
      submit.textContent = 'Отправляем…';

      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json' }
      })
        .then(function (r) {
          return r.json().catch(function () { return { ok: false }; });
        })
        .then(function (data) {
          if (data && data.ok) {
            form.reset();
            setStatus(status, 'success', data.message || 'Спасибо! Заявка отправлена — скоро свяжемся с вами.');
            goal('form_submit');
            return;
          }
          if (data && data.errors) {
            Object.keys(data.errors).forEach(function (name) {
              if (form.elements[name]) setFieldError(form, name, data.errors[name]);
            });
          }
          setStatus(status, 'error', (data && data.message) || 'Не удалось отправить заявку. Попробуйте ещё раз чуть позже.');
        })
        .catch(function () {
          setStatus(status, 'error', 'Нет связи с сервером. Проверьте интернет и попробуйте ещё раз или напишите нам в Telegram.');
        })
        .then(function () {
          submit.disabled = false;
          submit.textContent = 'Получить консультацию';
        });
    });
  }

  function init() {
    initContacts();
    initMenu();
    initAnchors();
    initReveal();
    initForm();
    initMetrika();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
