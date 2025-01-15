import ThreeGlobe from "three-globe";
import * as d3 from "d3";

import {
  Scene,
  PerspectiveCamera,
  WebGLRenderer,
  AmbientLight,
  DirectionalLight,
} from "three";
import { TrackballControls } from "three/examples/jsm/controls/TrackballControls";
import {
  BloomEffect,
  EffectComposer,
  EffectPass,
  RenderPass,
} from "postprocessing";

fetch(
  `https://vasturiano.github.io/three-globe/example/country-polygons/ne_110m_admin_0_countries.geojson`
)
  .then((res) => res.json())
  .then((countries) => {
    const Globe = new ThreeGlobe()
      .globeImageUrl(
        "//unpkg.com/three-globe/example/img/earth-blue-marble.jpg"
      )
      .bumpImageUrl("//unpkg.com/three-globe/example/img/earth-topology.png")
      .polygonsData(
        countries.features.filter((d) => d.properties.ISO_A2 !== "AQ")
      )
      .polygonCapColor(() => "rgba(150, 70, 0, 0.7)")
      .polygonSideColor(() => "rgba(30, 20, 50, 0.1)")
      .polygonStrokeColor(() => "#111");
    //    setTimeout(() => Globe.polygonAltitude(() => Math.random()), 0)
    const scene = new Scene();
    scene.add(Globe);
    scene.add(new AmbientLight(0xbbbbbb));
    scene.add(new DirectionalLight(0xffffff, 0.6));
    // scene.rotation.y += 0.009;

    const renderer = new WebGLRenderer({
      powerPreference: "high-performance",
      antialias: false,
      stencil: false,
      depth: true,
    });
    renderer.setSize(window.innerWidth, window.innerHeight);
    document.getElementById("globeViz").appendChild(renderer.domElement);

    const camera = new PerspectiveCamera();
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    camera.position.z = 300;

    const tbControls = new TrackballControls(camera, renderer.domElement);
    tbControls.minDistance = 101;
    tbControls.rotateSpeed = 5;
    tbControls.zoomSpeed = 0.8;

    const composer = new EffectComposer(renderer);
    composer.addPass(new RenderPass(scene, camera));
    const bloom = new BloomEffect();
    composer.addPass(new EffectPass(camera, bloom));

    function animate() {
      tbControls.update();
      composer.render();
      window.requestAnimationFrame(animate);
    }
    d3.timer(function (t) {
      // scene.rotation.x =
      //   (0.8 * (Math.sin(t / 11000) * Math.PI)) / 3 - Math.PI / 2;
      scene.rotation.y = t / 10000;
      renderer.render(scene, camera);
    });
    animate();
  })
  .catch(console.log);
