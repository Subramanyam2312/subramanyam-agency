/**
 * Hero 3D — a slowly rotating faceted crystal, hand-written WebGL.
 *
 * No library. Three.js would be ~600 KB for what this does in ~9 KB, and it would
 * undo the mobile performance budget the rest of the site is built around. This is
 * one draw call of ~80 flat-shaded facets — trivial for any GPU made this decade.
 *
 * It behaves. It attaches only after the load event so it never competes with the
 * LCP text, renders a single static frame under prefers-reduced-motion instead of
 * animating, pauses when the hero scrolls off-screen or the tab is hidden, caps
 * device-pixel-ratio for fill-rate, and if WebGL is unavailable it simply does
 * nothing — the CSS gradient layer behind it is the fallback, already on the page.
 */
(function () {
  'use strict';

  var canvas = document.getElementById('hero-canvas');
  if (!canvas) {
    return;
  }

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var gl = canvas.getContext('webgl', {
    alpha: true,
    antialias: true,
    premultipliedAlpha: false,
    depth: true,
    powerPreference: 'low-power'
  });

  if (!gl) {
    return; // No WebGL — the gradient layer stays, and nobody is worse off.
  }

  /* ---------------------------------------------------------------- math */
  // Column-major 4x4 helpers, matching WebGL's uniform layout.

  function multiply(a, b) {
    var out = new Float32Array(16);
    for (var c = 0; c < 4; c++) {
      for (var r = 0; r < 4; r++) {
        out[c * 4 + r] =
          a[0 * 4 + r] * b[c * 4 + 0] +
          a[1 * 4 + r] * b[c * 4 + 1] +
          a[2 * 4 + r] * b[c * 4 + 2] +
          a[3 * 4 + r] * b[c * 4 + 3];
      }
    }
    return out;
  }

  function perspective(fovy, aspect, near, far) {
    var f = 1 / Math.tan(fovy / 2);
    var nf = 1 / (near - far);
    var out = new Float32Array(16);
    out[0] = f / aspect;
    out[5] = f;
    out[10] = (far + near) * nf;
    out[11] = -1;
    out[14] = 2 * far * near * nf;
    return out;
  }

  function translation(x, y, z) {
    var out = new Float32Array(16);
    out[0] = out[5] = out[10] = out[15] = 1;
    out[12] = x;
    out[13] = y;
    out[14] = z;
    return out;
  }

  // Combined Y-then-X rotation, returned as a 4x4. The upper-left 3x3 doubles as
  // the normal matrix because the transform is pure rotation (no scale/shear).
  function rotationYX(ry, rx) {
    var cy = Math.cos(ry), sy = Math.sin(ry);
    var cx = Math.cos(rx), sx = Math.sin(rx);
    var out = new Float32Array(16);
    // Ry * Rx
    out[0] = cy;        out[1] = 0;   out[2] = -sy;       out[3] = 0;
    out[4] = sy * sx;   out[5] = cx;  out[6] = cy * sx;   out[7] = 0;
    out[8] = sy * cx;   out[9] = -sx; out[10] = cy * cx;  out[11] = 0;
    out[12] = 0;        out[13] = 0;  out[14] = 0;        out[15] = 1;
    return out;
  }

  function mat3FromMat4(m) {
    return new Float32Array([m[0], m[1], m[2], m[4], m[5], m[6], m[8], m[9], m[10]]);
  }

  /* ------------------------------------------------------------ geometry */
  // Icosahedron, subdivided once and re-projected to the sphere, then emitted as
  // non-indexed triangles with one flat normal per face — that flatness is what
  // gives the crisp faceted "cut gem" look rather than a smooth ball.

  function buildCrystal(subdivisions) {
    var t = (1 + Math.sqrt(5)) / 2;
    var verts = [
      [-1, t, 0], [1, t, 0], [-1, -t, 0], [1, -t, 0],
      [0, -1, t], [0, 1, t], [0, -1, -t], [0, 1, -t],
      [t, 0, -1], [t, 0, 1], [-t, 0, -1], [-t, 0, 1]
    ].map(normalize);

    var faces = [
      [0, 11, 5], [0, 5, 1], [0, 1, 7], [0, 7, 10], [0, 10, 11],
      [1, 5, 9], [5, 11, 4], [11, 10, 2], [10, 7, 6], [7, 1, 8],
      [3, 9, 4], [3, 4, 2], [3, 2, 6], [3, 6, 8], [3, 8, 9],
      [4, 9, 5], [2, 4, 11], [6, 2, 10], [8, 6, 7], [9, 8, 1]
    ];

    for (var s = 0; s < subdivisions; s++) {
      var next = [];
      for (var f = 0; f < faces.length; f++) {
        var a = faces[f][0], b = faces[f][1], c = faces[f][2];
        var ab = midpoint(verts[a], verts[b], verts);
        var bc = midpoint(verts[b], verts[c], verts);
        var ca = midpoint(verts[c], verts[a], verts);
        next.push([a, ab, ca], [b, bc, ab], [c, ca, bc], [ab, bc, ca]);
      }
      faces = next;
    }

    var positions = [];
    var normals = [];

    for (var i = 0; i < faces.length; i++) {
      var p0 = verts[faces[i][0]];
      var p1 = verts[faces[i][1]];
      var p2 = verts[faces[i][2]];
      var n = faceNormal(p0, p1, p2);
      positions.push(p0[0], p0[1], p0[2], p1[0], p1[1], p1[2], p2[0], p2[1], p2[2]);
      normals.push(n[0], n[1], n[2], n[0], n[1], n[2], n[0], n[1], n[2]);
    }

    return {
      positions: new Float32Array(positions),
      normals: new Float32Array(normals),
      count: faces.length * 3
    };
  }

  function normalize(v) {
    var l = Math.hypot(v[0], v[1], v[2]) || 1;
    return [v[0] / l, v[1] / l, v[2] / l];
  }

  function midpoint(a, b, verts) {
    verts.push(normalize([(a[0] + b[0]) / 2, (a[1] + b[1]) / 2, (a[2] + b[2]) / 2]));
    return verts.length - 1;
  }

  function faceNormal(a, b, c) {
    var ux = b[0] - a[0], uy = b[1] - a[1], uz = b[2] - a[2];
    var vx = c[0] - a[0], vy = c[1] - a[1], vz = c[2] - a[2];
    return normalize([uy * vz - uz * vy, uz * vx - ux * vz, ux * vy - uy * vx]);
  }

  /* -------------------------------------------------------------- shaders */

  var vertexSrc =
    'attribute vec3 aPos;' +
    'attribute vec3 aNormal;' +
    'uniform mat4 uMVP;' +
    'uniform mat4 uModelView;' +
    'uniform mat3 uNormal;' +
    'varying vec3 vN;' +
    'varying vec3 vView;' +
    'void main(){' +
    '  vec4 mv = uModelView * vec4(aPos, 1.0);' +
    '  vView = mv.xyz;' +
    '  vN = normalize(uNormal * aNormal);' +
    '  gl_Position = uMVP * vec4(aPos, 1.0);' +
    '}';

  var fragmentSrc =
    'precision mediump float;' +
    'varying vec3 vN;' +
    'varying vec3 vView;' +
    'uniform vec3 uAccent;' +
    'void main(){' +
    '  vec3 N = normalize(vN);' +
    '  vec3 V = normalize(-vView);' +
    // A warm champagne key light and a cooler back-fill, so adjacent facets read
    // at different brightnesses and the gem turns rather than flattening out.
    '  vec3 L1 = normalize(vec3(0.45, 0.75, 0.55));' +
    '  vec3 L2 = normalize(vec3(-0.5, -0.25, 0.35));' +
    '  float d1 = max(dot(N, L1), 0.0);' +
    '  float d2 = max(dot(N, L2), 0.0);' +
    // near-black facet body with a faint warm cast, so it reads as obsidian, not grey
    '  vec3 base = vec3(0.07, 0.062, 0.052);' +
    '  vec3 warm = uAccent * 0.5 + vec3(0.02);' +
    '  vec3 col = base + warm * d1 * 1.6 + base * d2 * 0.7;' +
    // champagne fresnel rim — the gilded edge glow, wide and bright
    '  float fres = pow(1.0 - max(dot(N, V), 0.0), 2.1);' +
    '  col += uAccent * fres * 1.35;' +
    // sharp per-facet gold glints that catch as it turns
    '  col += uAccent * pow(d1, 18.0) * 0.5;' +
    '  gl_FragColor = vec4(col, 0.96);' +
    '}';

  function compile(type, src) {
    var sh = gl.createShader(type);
    gl.shaderSource(sh, src);
    gl.compileShader(sh);
    if (!gl.getShaderParameter(sh, gl.COMPILE_STATUS)) {
      gl.deleteShader(sh);
      return null;
    }
    return sh;
  }

  var vs = compile(gl.VERTEX_SHADER, vertexSrc);
  var fs = compile(gl.FRAGMENT_SHADER, fragmentSrc);
  if (!vs || !fs) {
    return;
  }

  var program = gl.createProgram();
  gl.attachShader(program, vs);
  gl.attachShader(program, fs);
  gl.linkProgram(program);
  if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
    return;
  }

  /* --------------------------------------------------------------- buffers */

  var mesh = buildCrystal(1); // 80 facets — faceted, not smooth, not heavy

  var posBuffer = gl.createBuffer();
  gl.bindBuffer(gl.ARRAY_BUFFER, posBuffer);
  gl.bufferData(gl.ARRAY_BUFFER, mesh.positions, gl.STATIC_DRAW);
  var aPos = gl.getAttribLocation(program, 'aPos');

  var normBuffer = gl.createBuffer();
  gl.bindBuffer(gl.ARRAY_BUFFER, normBuffer);
  gl.bufferData(gl.ARRAY_BUFFER, mesh.normals, gl.STATIC_DRAW);
  var aNormal = gl.getAttribLocation(program, 'aNormal');

  var uMVP = gl.getUniformLocation(program, 'uMVP');
  var uModelView = gl.getUniformLocation(program, 'uModelView');
  var uNormal = gl.getUniformLocation(program, 'uNormal');
  var uAccent = gl.getUniformLocation(program, 'uAccent');

  gl.enable(gl.DEPTH_TEST);
  gl.enable(gl.BLEND);
  gl.blendFunc(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA);

  /* --------------------------------------------------------------- state */

  var dpr = Math.min(window.devicePixelRatio || 1, 1.5);
  var proj = new Float32Array(16);
  var pointerX = 0, pointerY = 0;   // target, -1..1
  var driftX = 0, driftY = 0;       // eased actual
  var running = false;
  var startTime = 0;

  function resize() {
    var rect = canvas.getBoundingClientRect();
    var w = Math.max(1, Math.round(rect.width * dpr));
    var h = Math.max(1, Math.round(rect.height * dpr));
    if (canvas.width !== w || canvas.height !== h) {
      canvas.width = w;
      canvas.height = h;
    }
    gl.viewport(0, 0, canvas.width, canvas.height);
    proj = perspective(0.62, canvas.width / canvas.height, 0.1, 100);
  }

  // On a wide viewport the gem sits upper-right, out of the way of the bottom-left
  // headline; on narrow screens it centres and drops back.
  function objectPlacement() {
    var landscape = window.innerWidth >= 1024;
    // On narrow screens the gem floats higher and further back, so it reads as a
    // backdrop behind the headline rather than competing with it for the eyebrow.
    return {
      x: landscape ? 1.15 : 0,
      y: landscape ? 0.35 : 0.9,
      dist: landscape ? 4.6 : 6.6
    };
  }

  function drawFrame(rotY, rotX, scale) {
    var place = objectPlacement();

    var model = rotationYX(rotY, rotX);

    // Uniform breathing scale. Safe to bake into the model because the shader
    // re-normalises the transformed normal, so a uniform scale cancels there.
    if (scale && scale !== 1) {
      for (var i = 0; i < 3; i++) {
        model[i] *= scale; model[4 + i] *= scale; model[8 + i] *= scale;
      }
    }
    var view = translation(place.x, place.y, -place.dist);
    var modelView = multiply(view, model);
    var mvp = multiply(proj, modelView);

    gl.clear(gl.COLOR_BUFFER_BIT | gl.DEPTH_BUFFER_BIT);
    gl.useProgram(program);

    gl.bindBuffer(gl.ARRAY_BUFFER, posBuffer);
    gl.enableVertexAttribArray(aPos);
    gl.vertexAttribPointer(aPos, 3, gl.FLOAT, false, 0, 0);

    gl.bindBuffer(gl.ARRAY_BUFFER, normBuffer);
    gl.enableVertexAttribArray(aNormal);
    gl.vertexAttribPointer(aNormal, 3, gl.FLOAT, false, 0, 0);

    gl.uniformMatrix4fv(uMVP, false, mvp);
    gl.uniformMatrix4fv(uModelView, false, modelView);
    gl.uniformMatrix3fv(uNormal, false, mat3FromMat4(model));
    // Champagne gold, linearised to sit right against the dark facets — the gem's
    // edges and glints read as gilt rather than the earlier bone.
    gl.uniform3f(uAccent, 0.86, 0.66, 0.36);

    gl.drawArrays(gl.TRIANGLES, 0, mesh.count);
  }

  function loop(now) {
    if (!running) {
      return;
    }
    if (!startTime) {
      startTime = now;
    }
    var t = (now - startTime) / 1000;

    // Ease the pointer influence so it feels weighted, not twitchy.
    driftX += (pointerX - driftX) * 0.04;
    driftY += (pointerY - driftY) * 0.04;

    var rotY = t * 0.22 + driftX * 0.5;
    var rotX = Math.sin(t * 0.35) * 0.18 + driftY * 0.4;
    var scale = 1 + Math.sin(t * 0.5) * 0.03; // gentle 3% breathe

    drawFrame(rotY, rotX, scale);
    window.requestAnimationFrame(loop);
  }

  function start() {
    if (running) {
      return;
    }
    running = true;
    startTime = 0;
    window.requestAnimationFrame(loop);
  }

  function stop() {
    running = false;
  }

  /* ------------------------------------------------------------- wiring */

  resize();
  window.addEventListener('resize', function () {
    resize();
    if (!running) {
      // Keep the static frame correct after an orientation change.
      drawFrame(0.6, 0.1);
    }
  });

  if (reduceMotion) {
    // One considered still frame, no loop.
    drawFrame(0.6, 0.12);
    return;
  }

  // Gentle parallax from the cursor, desktop only. Touch devices never fire this,
  // so the gem just rotates on its own there.
  window.addEventListener('pointermove', function (event) {
    if (event.pointerType === 'touch') {
      return;
    }
    pointerX = (event.clientX / window.innerWidth) * 2 - 1;
    pointerY = (event.clientY / window.innerHeight) * 2 - 1;
  }, { passive: true });

  // Pause when the hero is not on screen — no point spinning a gem nobody can see.
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      if (entries[0].isIntersecting) {
        start();
      } else {
        stop();
      }
    }, { threshold: 0.01 });
    io.observe(canvas);
  } else {
    start();
  }

  // And pause with the tab.
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
      stop();
    } else if (isOnScreen()) {
      start();
    }
  });

  function isOnScreen() {
    var r = canvas.getBoundingClientRect();
    return r.bottom > 0 && r.top < window.innerHeight;
  }

  // Only begin once the page has loaded, so the crystal never delays first paint.
  if (document.readyState === 'complete') {
    if (isOnScreen()) { start(); }
  } else {
    window.addEventListener('load', function () {
      if (isOnScreen()) { start(); }
    });
  }
})();
