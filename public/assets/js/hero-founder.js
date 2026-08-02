/**
 * Hero founder scene — scroll parallax + pointer tilt for the founder portrait.
 *
 * No library. The chart draws and the motifs float via CSS; this file only adds
 * the two things CSS cannot: vertical parallax tied to scroll position, and a
 * subtle 3D tilt toward the pointer. Everything is transform-only (GPU), runs
 * inside a single rAF, pauses when the hero leaves the viewport, and does nothing
 * at all under prefers-reduced-motion (the CSS already lands each motif in its
 * finished, static state).
 */
(function () {
  'use strict';

  var scene = document.querySelector('[data-founder]');
  if (!scene) {
    return;
  }

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return; // CSS holds the finished frame; no scroll/tilt motion.
  }

  var stage = scene.querySelector('[data-founder-stage]');
  if (!stage) {
    return;
  }

  var raf = 0;
  var onScreen = true;
  var tiltX = 0, tiltY = 0;      // current, eased
  var targetX = 0, targetY = 0;  // pointer target

  function schedule() {
    if (!raf) {
      raf = window.requestAnimationFrame(frame);
    }
  }

  function frame() {
    raf = 0;

    var rect = scene.getBoundingClientRect();
    var vh = window.innerHeight || 1;

    // Progress rises as the scene travels up through the viewport.
    var prog = 1 - (rect.top + rect.height * 0.5) / vh;
    if (prog < 0) { prog = 0; } else if (prog > 1) { prog = 1; }

    // Vertical parallax + a slight backward lean as you scroll down.
    stage.style.setProperty('--fr-py', (prog * -26).toFixed(2));
    var lean = prog * 3.5;

    // Ease the pointer tilt so it glides rather than snaps.
    tiltX += (targetX - tiltX) * 0.12;
    tiltY += (targetY - tiltY) * 0.12;

    stage.style.setProperty('--fr-x', (tiltX + lean).toFixed(2));
    stage.style.setProperty('--fr-y', tiltY.toFixed(2));

    // Keep animating while the tilt is still settling.
    if (onScreen && (Math.abs(targetX - tiltX) > 0.01 || Math.abs(targetY - tiltY) > 0.01)) {
      schedule();
    }
  }

  window.addEventListener('scroll', schedule, { passive: true });
  window.addEventListener('resize', schedule);

  if (window.matchMedia('(pointer: fine)').matches) {
    scene.addEventListener('pointermove', function (e) {
      var rect = scene.getBoundingClientRect();
      var nx = (e.clientX - (rect.left + rect.width / 2)) / (rect.width / 2);
      var ny = (e.clientY - (rect.top + rect.height / 2)) / (rect.height / 2);
      targetY = Math.max(-1, Math.min(1, nx)) * 6;    // rotateY follows cursor X
      targetX = Math.max(-1, Math.min(1, ny)) * -5;   // rotateX follows cursor Y
      schedule();
    });
    scene.addEventListener('pointerleave', function () {
      targetX = 0; targetY = 0; schedule();
    });
  }

  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (entries) {
      onScreen = entries[0].isIntersecting;
      if (onScreen) { schedule(); }
    }, { threshold: 0 }).observe(scene);
  }

  frame();
})();
