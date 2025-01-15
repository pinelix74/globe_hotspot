<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="./modal.css" />
    <title>Globe</title>
    <style>
      body {
        margin: 0;
        overflow: hidden;
      }

      canvas {
        display: block;
      }

      #fileUpload {
        position: absolute;
        top: 10px;
        left: 10px;
        z-index: 10;
        background-color: #ffffff;
        padding: 10px;
        border-radius: 5px;
      }

      #tooltip {
        position: absolute;
        background-color: rgba(255, 251, 41, 0.6);
        color: white;
        padding: 10px 20px;
        border-radius: 5px 30px 5px 30px;
        font-size: 18px;
        pointer-events: none;
        display: none;
        /* Initially hidden */
        z-index: 10;
      }
    </style>
  </head>

  <body>
    <input type="file" id="fileUpload" accept=".csv" />
    <div id="tooltip"></div>
    <div class="modal" id="myModal">
      <div class="modal-content">
        <span class="close">&times;</span>
        <h2 id="modal_title"></h2>
        <p id="modal_description"></p>
      </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/0.157.0/three.min.js"></script>
    <script src="https://unpkg.com/three-globe"></script>
    <script src="https://cdn.jsdelivr.net/npm/three/examples/js/controls/OrbitControls.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.3.0/papaparse.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mobile-detect/1.4.5/mobile-detect.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three/examples/js/postprocessing/EffectComposer.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three/examples/js/shaders/LuminosityHighPassShader.js"></script>
    <!-- Added -->
    <script src="https://cdn.jsdelivr.net/npm/three/examples/js/shaders/CopyShader.js"></script>
    <!-- Added -->
    <script src="https://cdn.jsdelivr.net/npm/three/examples/js/postprocessing/ShaderPass.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three/examples/js/postprocessing/UnrealBloomPass.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/three/examples/js/postprocessing/RenderPass.js"></script>
    <script>
      const modal = document.getElementById("myModal");
      const closeBtn = document.querySelector(".close");
      const tooltip = document.getElementById("tooltip");

      closeBtn.addEventListener("click", () => {
        modal.style.display = "none";
      });

      window.addEventListener("click", (event) => {
        if (event.target === modal) {
          modal.style.display = "none";
        }
      });

      function openModal(title, description) {
        document.getElementById("modal_title").innerText = title;
        document.getElementById("modal_description").innerText = description;
        modal.style.display = "flex";
      }

      // Scene, Camera, Renderer
      const scene = new THREE.Scene();
      const camera = new THREE.PerspectiveCamera(
        75,
        window.innerWidth / window.innerHeight,
        0.1,
        1000
      );
      const renderer = new THREE.WebGLRenderer({
        antialias: true,
      });
      renderer.setSize(window.innerWidth, window.innerHeight);
      document.body.appendChild(renderer.domElement);

      // Globe Geometry and Material
      const globeRadius = new MobileDetect(window.navigator.userAgent).mobile()
        ? 3
        : 5;
      const globeGeometry = new THREE.SphereGeometry(globeRadius, 64, 64);
      const textureLoader = new THREE.TextureLoader();
      const globeMaterial = new THREE.MeshBasicMaterial({
        map: textureLoader.load(
          "./earth-blue-marble.jpg"
        ),
      });
      const globe = new THREE.Mesh(globeGeometry, globeMaterial);
      scene.add(globe);

      const pointsGroup = new THREE.Group();
      globe.add(pointsGroup);

      const atmosphereGeometry = new THREE.SphereGeometry(
        globeRadius * 1.07,
        64,
        64
      );
      const atmosphereMaterial = new THREE.ShaderMaterial({
        uniforms: {},
        vertexShader: `
        varying vec3 vertexNormal;
        void main() {
            vertexNormal = normalize(normalMatrix * normal);
            gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
        }
    `,
        fragmentShader: `
        varying vec3 vertexNormal;
        void main() {
            // Adjust the intensity computation for a wider glow radius
            float intensity = pow(1.2 - dot(vertexNormal, vec3(0.0, 0.0, 1.0)), 7.0); 
            vec3 atmosphereColor = vec3(0.2, 0.5, 1.0); // Atmosphere blue color (RGB: soft blue)
            gl_FragColor = vec4(atmosphereColor, 0.1) * intensity; // Adjust alpha to 0.2
        }
    `,
        blending: THREE.AdditiveBlending,
        side: THREE.BackSide,
        transparent: true,
      });

      const atmosphere = new THREE.Mesh(atmosphereGeometry, atmosphereMaterial);
      scene.add(atmosphere);

      // Post-Processing Setup
      const composer = new THREE.EffectComposer(renderer);
      const renderPass = new THREE.RenderPass(scene, camera);
      composer.addPass(renderPass);

      const bloomPass = new THREE.UnrealBloomPass(
        new THREE.Vector2(window.innerWidth, window.innerHeight),
        0.8, // Strength
        0.6, // Radius
        0.9 // Threshold
      );
      composer.addPass(bloomPass);

      const CLOUDS_IMG_URL = "./clouds.png"; // from https://github.com/turban/webgl-earth
      const CLOUDS_ALT = 0.008;
      const CLOUDS_ROTATION_SPEED = -0.006; // deg/frame

      new THREE.TextureLoader().load(CLOUDS_IMG_URL, (cloudsTexture) => {
        const clouds = new THREE.Mesh(
          new THREE.SphereGeometry(globeRadius * (1 + CLOUDS_ALT), 75, 75),
          new THREE.MeshPhysicalMaterial({
            map: cloudsTexture,
            transparent: true,
            emissive: 0xffffff,
            opacity: 0.5,
          })
        );
        scene.add(clouds);

        (function rotateClouds() {
          clouds.rotation.y += (CLOUDS_ROTATION_SPEED * Math.PI) / 180;
          requestAnimationFrame(rotateClouds);
        })();
      });

      // Function to Convert Lat/Lng to 3D Coordinates
      function latLngToVector3(lat, lng, radius) {
        const phi = (90 - lat) * (Math.PI / 180);
        const theta = (lng + 180) * (Math.PI / 180);
        const x = -(radius * Math.sin(phi) * Math.cos(theta));
        const z = radius * Math.sin(phi) * Math.sin(theta);
        const y = radius * Math.cos(phi);
        return new THREE.Vector3(x, y, z);
      }

      // Add Points from CSV
      function addPointsFromCSV(csvUrl) {
        fetch(csvUrl)
          .then((response) => response.text())
          .then((data) => {
            const parsedData = Papa.parse(data, {
              header: true,
            }).data;
            addPointsFromParsedCSV(parsedData);
          });
      }

      // Add Points from Parsed CSV
      function addPointsFromParsedCSV(parsedData) {
        const radius = 0.06; // Hole size
        const geometry = new THREE.SphereGeometry(radius);
        const material = new THREE.MeshMatcapMaterial({
          color: 0x4d5dff, // Blue color
          side: THREE.DoubleSide,
        });

        parsedData.forEach((row) => {
          const lat = parseFloat(row.Lat);
          const lng = parseFloat(row.Lng);
          const title = row.Title;
          const description = row.Description;

          const point = new THREE.Mesh(geometry, material);
          point.position.copy(latLngToVector3(lat, lng, globeRadius + 0.1));
          point.userData = {
            title,
            description,
          }; // Store title and description in userData
          pointsGroup.add(point);
        });
      }

      const fileUpload = document.getElementById("fileUpload");
      fileUpload.addEventListener("change", (event) => {
        const file = event.target.files[0];
        if (file && file.type === "text/csv") {
          const reader = new FileReader();
          reader.onload = function (e) {
            const csvData = e.target.result;

            console.log(pointsGroup.children);
            // Clear existing points
            // pointsGroup.children.forEach(point => {
            //     pointsGroup.remove(point); // Remove each point instead of clear()
            // });
            pointsGroup.children = [];
            // Parse and add points from the uploaded file
            const parsedData = Papa.parse(csvData, {
              header: true,
            }).data;
            addPointsFromParsedCSV(parsedData);

            // Reset file input value to allow re-uploading the same file
            fileUpload.value = "";
          };
          reader.readAsText(file);
        } else {
          alert("Please upload a valid CSV file.");
          // Reset file input value
          fileUpload.value = "";
        }
      });

      // Load CSV Data
      addPointsFromCSV("./globedata.csv"); // Replace with your CSV file path

      const raycaster = new THREE.Raycaster();
      const mouse = new THREE.Vector2();
      let currentIntersectedPoint = null;
      let isDraggingOnGlobe = false;
      const globeMesh = globe;

      function onPointerClick(event) {
        mouse.x = (event.clientX / window.innerWidth) * 2 - 1;
        mouse.y = -(event.clientY / window.innerHeight) * 2 + 1;
        raycaster.setFromCamera(mouse, camera);

        const intersects = raycaster.intersectObjects(pointsGroup.children);

        if (intersects.length > 0) {
          const intersectedPoint = intersects[0].object;
          openModal(
            intersectedPoint.userData.title,
            intersectedPoint.userData.description
          );
          if (currentIntersectedPoint !== intersectedPoint) {
            if (currentIntersectedPoint) {
            }
            currentIntersectedPoint = intersectedPoint;
          }
        } else {
          if (currentIntersectedPoint) {
            currentIntersectedPoint = null;
          }
        }
      }

      function onPointerMove(event) {
        mouse.x = (event.clientX / window.innerWidth) * 2 - 1;
        mouse.y = -(event.clientY / window.innerHeight) * 2 + 1;
        raycaster.setFromCamera(mouse, camera);

        const intersects = raycaster.intersectObjects(pointsGroup.children);

        if (intersects.length > 0) {
          const intersectedPoint = intersects[0].object;
          tooltip.style.display = "block";
          tooltip.style.left = `${event.clientX + 10}px`;
          tooltip.style.top = `${event.clientY + 10}px`;
          tooltip.textContent = intersectedPoint.userData.title; // Use userData
        } else {
          tooltip.style.display = "none";
        }
      }

      window.addEventListener("mousedown", (event) => {
        // Convert mouse position to normalized device coordinates
        mouse.x = (event.clientX / window.innerWidth) * 2 - 1;
        mouse.y = -(event.clientY / window.innerHeight) * 2 + 1;

        // Raycast from the camera to the mouse position
        raycaster.setFromCamera(mouse, camera);
        const intersects = raycaster.intersectObject(globeMesh);

        // If the mouse click intersects with the globe
        isDraggingOnGlobe = intersects.length > 0;
        if (!isDraggingOnGlobe) {
          controls.enabled = false; // Disable rotation if not clicking on the globe
        }
      });

      window.addEventListener("click", onPointerClick);
      window.addEventListener("mousemove", onPointerMove);
      window.addEventListener("mouseup", () => {
        isDraggingOnGlobe = false;
        controls.enabled = true; // Re-enable rotation after interaction
      });

      // Camera and Animation
      camera.position.z = 10;
      const controls = new THREE.OrbitControls(camera, renderer.domElement);
      controls.minPolarAngle = 0;
      controls.maxPolarAngle = Math.PI;
      controls.enableDamping = true; // Smooth rotation
      controls.dampingFactor = 0.05;
      controls.enablePan = false;
      controls.enableZoom = false; // Allow zooming with mouse wheel
      controls.minDistance = 6; // Minimum zoom distance
      controls.maxDistance = 20; // Maximum zoom distance
      controls.target.set(0, 0, 0); // Set target to the globe's center

      function animate() {
        requestAnimationFrame(animate);
        globe.rotation.y += 0.0001;
        controls.update();
        composer.render(); // Use composer to render for post-processing
      }
      animate();
    </script>
  </body>
</html>
