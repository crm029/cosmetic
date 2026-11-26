import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
const reduced=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
function createRenderer(container,interactive=false){
 const renderer=new THREE.WebGLRenderer({alpha:true,antialias:true});
 renderer.setPixelRatio(Math.min(window.devicePixelRatio||1,1.5));renderer.setSize(container.clientWidth,container.clientHeight);renderer.outputColorSpace=THREE.SRGBColorSpace;
 renderer.domElement.setAttribute('aria-hidden','true');renderer.domElement.style.cssText='position:absolute;inset:0;width:100%;height:100%;pointer-events:'+(interactive?'auto':'none')+';z-index:1';
 container.appendChild(renderer.domElement);return renderer;
}
function addLights(scene){
 scene.add(new THREE.HemisphereLight(0xfff7ef,0x765c4b,2.1));
 const key=new THREE.PointLight(0xffdfc4,32,25);key.position.set(-3,4,6);scene.add(key);
 const rim=new THREE.PointLight(0xe6c9b1,18,18);rim.position.set(4,-2,4);scene.add(rim);
}
function makeLabelTexture(){
 const canvas=document.createElement('canvas');canvas.width=512;canvas.height=280;
 const ctx=canvas.getContext('2d');ctx.fillStyle='#f4e9de';ctx.fillRect(0,0,512,280);
 ctx.strokeStyle='#b18b65';ctx.lineWidth=3;ctx.strokeRect(18,18,476,244);
 ctx.textAlign='center';ctx.fillStyle='#5b463b';ctx.font='italic 34px Georgia';ctx.fillText('THE BEAUTY EDIT',256,100);
 ctx.fillStyle='#927564';ctx.font='500 23px Arial';ctx.fillText('SKINCARE',256,155);
 ctx.strokeStyle='#b18b65';ctx.lineWidth=2;ctx.beginPath();ctx.moveTo(150,180);ctx.lineTo(362,180);ctx.stroke();
 ctx.fillStyle='#8b7668';ctx.font='18px Arial';ctx.fillText('BOTANICAL SERUM  •  30 ML',256,218);
 const texture=new THREE.CanvasTexture(canvas);texture.colorSpace=THREE.SRGBColorSpace;return texture;
}
const labelTexture=makeLabelTexture();
function makeBottle(scene,{x=0,y=0,scale=1,tint=0xd6b7a8}={}){
 const group=new THREE.Group();group.position.set(x,y,0);group.scale.setScalar(scale);scene.add(group);
 const glass=new THREE.MeshPhysicalMaterial({color:tint,metalness:.12,roughness:.2,transparent:true,opacity:.94,clearcoat:1,clearcoatRoughness:.12});
 const gold=new THREE.MeshStandardMaterial({color:0xb18b65,metalness:.78,roughness:.23});
 const label=new THREE.MeshStandardMaterial({map:labelTexture,roughness:.8});
 const body=new THREE.Mesh(new THREE.CylinderGeometry(.62,.58,2.15,48,1,false),glass);body.position.y=-.1;group.add(body);
 const shoulder=new THREE.Mesh(new THREE.CylinderGeometry(.28,.58,.38,48),glass);shoulder.position.y=1.16;group.add(shoulder);
 const neck=new THREE.Mesh(new THREE.CylinderGeometry(.22,.22,.28,32),gold);neck.position.y=1.47;group.add(neck);
 const cap=new THREE.Mesh(new THREE.CylinderGeometry(.25,.25,.42,40),gold);cap.position.y=1.82;group.add(cap);
 const band=new THREE.Mesh(new THREE.CylinderGeometry(.625,.625,.055,48),gold);band.position.y=-.82;group.add(band);
 const plaque=new THREE.Mesh(new THREE.PlaneGeometry(.78,.42),label);plaque.position.set(0,-.12,.585);group.add(plaque);
 const line=new THREE.Mesh(new THREE.PlaneGeometry(.38,.018),gold);line.position.set(0,-.12,.592);group.add(line);
 return group;
}
function mountHero3D(container){
 let renderer,frame,observer,visibilityHandler;
 try{
  const scene=new THREE.Scene();const camera=new THREE.PerspectiveCamera(36,container.clientWidth/container.clientHeight,.1,100);camera.position.set(0,0,9);
  renderer=createRenderer(container);addLights(scene);
  const orbitGroup=new THREE.Group();scene.add(orbitGroup);
  const orbit=new THREE.Mesh(new THREE.TorusGeometry(2.25,.012,8,180),new THREE.MeshBasicMaterial({color:0xb18b77,transparent:true,opacity:.55}));orbit.rotation.set(1.05,.18,-.35);orbitGroup.add(orbit);
  const orbit2=new THREE.Mesh(new THREE.TorusGeometry(1.65,.009,8,150),new THREE.MeshBasicMaterial({color:0xc9a98f,transparent:true,opacity:.42}));orbit2.rotation.set(.55,-.7,.5);orbitGroup.add(orbit2);
  const main=makeBottle(scene,{x:-.55,y:-.1,scale:.78,tint:0xc9a99a});const mini=makeBottle(scene,{x:1.25,y:-.65,scale:.52,tint:0xe0c9b9});
  const pearl=new THREE.Mesh(new THREE.SphereGeometry(.12,24,24),new THREE.MeshPhysicalMaterial({color:0xf2e5d8,metalness:.35,roughness:.12,clearcoat:1}));pearl.position.set(2,.95,.2);scene.add(pearl);
  const resize=()=>{const w=container.clientWidth,h=container.clientHeight;if(!w||!h)return;camera.aspect=w/h;camera.updateProjectionMatrix();renderer.setSize(w,h)};observer=new ResizeObserver(resize);observer.observe(container);
  const animate=()=>{if(document.hidden){frame=null;return;}frame=requestAnimationFrame(animate);const t=performance.now()*.001;main.position.y=-.1+Math.sin(t*.8)*.09;main.rotation.y=Math.sin(t*.35)*.12;mini.position.y=-.65+Math.sin(t*.8+1.2)*.12;mini.rotation.y=-.2+Math.sin(t*.4)*.15;orbitGroup.rotation.z+=.0015;pearl.position.y=.95+Math.sin(t*1.1)*.13;renderer.render(scene,camera)};const start=()=>{if(!frame&&!document.hidden)animate()};visibilityHandler=()=>{if(document.hidden){if(frame)cancelAnimationFrame(frame);frame=null}else start()};document.addEventListener('visibilitychange',visibilityHandler);start();
 }catch(error){if(frame)cancelAnimationFrame(frame);if(observer)observer.disconnect();if(visibilityHandler)document.removeEventListener('visibilitychange',visibilityHandler);if(renderer)renderer.dispose();console.info('3D enhancement unavailable; CSS artwork remains visible.')}
}
function mountProductModel(container){
 let renderer,observer,frame,controls,visibilityHandler;
 try{
  const scene=new THREE.Scene();const camera=new THREE.PerspectiveCamera(35,container.clientWidth/container.clientHeight,.1,100);camera.position.set(0,0,5.5);
  renderer=createRenderer(container,true);addLights(scene);
  controls=new OrbitControls(camera,renderer.domElement);controls.enableDamping=true;controls.dampingFactor=.06;controls.enablePan=false;controls.minDistance=2.2;controls.maxDistance=10;controls.autoRotate=!reduced;controls.autoRotateSpeed=.7;
  const render=()=>renderer.render(scene,camera);controls.addEventListener('change',render);
  const resize=()=>{const w=container.clientWidth,h=container.clientHeight;if(!w||!h)return;camera.aspect=w/h;camera.updateProjectionMatrix();renderer.setSize(w,h);render()};observer=new ResizeObserver(resize);observer.observe(container);
  new GLTFLoader().load(container.dataset.modelUrl,(gltf)=>{
   const model=gltf.scene;const bounds=new THREE.Box3().setFromObject(model);const size=bounds.getSize(new THREE.Vector3());const center=bounds.getCenter(new THREE.Vector3());const factor=3.3/Math.max(size.x,size.y,size.z,1);
   model.scale.setScalar(factor);model.position.sub(center.multiplyScalar(factor));scene.add(model);render();
  },undefined,()=>{if(frame)cancelAnimationFrame(frame);if(observer)observer.disconnect();if(visibilityHandler)document.removeEventListener('visibilitychange',visibilityHandler);if(controls)controls.dispose();if(renderer){renderer.dispose();renderer.domElement.remove()}mountHero3D(container)});
  const animate=()=>{if(document.hidden){frame=null;return;}frame=requestAnimationFrame(animate);controls.update();render()};const start=()=>{if(!reduced&&!frame&&!document.hidden)animate()};visibilityHandler=()=>{if(document.hidden){if(frame)cancelAnimationFrame(frame);frame=null}else start()};document.addEventListener('visibilitychange',visibilityHandler);if(!reduced)start();else render();
 }catch(error){if(frame)cancelAnimationFrame(frame);if(observer)observer.disconnect();if(visibilityHandler)document.removeEventListener('visibilitychange',visibilityHandler);if(controls)controls.dispose();if(renderer)renderer.dispose();mountHero3D(container)}
}
const hero=document.querySelector('.hero-art');if(hero&&!reduced)mountHero3D(hero);
const productViewer=document.querySelector('.product-3d-viewer');if(productViewer)mountProductModel(productViewer);
const productStage=document.querySelector('[data-3d-placeholder="true"]');if(productStage&&!productViewer&&!reduced)mountHero3D(productStage);
if(window.gsap&&!reduced){
 window.gsap.from('.hero-copy > *',{y:22,opacity:0,duration:.8,stagger:.12,ease:'power2.out'});
 window.gsap.utils.toArray('.category-card,.product-card').forEach(card=>{
  card.addEventListener('mouseenter',()=>window.gsap.to(card,{y:-5,duration:.25}));
  card.addEventListener('mouseleave',()=>window.gsap.to(card,{y:0,duration:.25}));
 });
}